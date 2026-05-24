<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CabinetController;
use App\Core\Database;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Services\AdminService;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\CabinetService;
use App\Services\GuestService;
use App\Services\RandomPredictionService;
use App\Services\RateLimiter;
use App\Services\UiTextService;

$config = require dirname(__DIR__) . '/app/Core/bootstrap.php';

$authService = new AuthService();
$guestService = new GuestService();
$rateLimiter = new RateLimiter();
$predictionService = new RandomPredictionService();
$cabinetService = new CabinetService();
$adminService = new AdminService();
$analyticsService = new AnalyticsService();
$uiTextService = new UiTextService();

$authController = new AuthController($authService, $guestService, $rateLimiter, $analyticsService);
$apiController = new ApiController($authService, $guestService, $predictionService, $rateLimiter, $analyticsService);
$cabinetController = new CabinetController($authService, $cabinetService, $analyticsService, $uiTextService);
$adminController = new AdminController($authService, $adminService, $analyticsService, $uiTextService);

$router = new Router();

$router->get('/', static function () use ($config, $authService, $analyticsService, $uiTextService): void {
    $dbStatus = [
        'ok' => false,
        'label' => 'База данных не создана',
        'details' => 'Запустите php database/seed.php --fresh, чтобы создать SQLite-файл и демо-данные.',
        'packs_count' => null,
        'predictions_count' => null,
    ];

    try {
        $pdo = Database::connect($config);
        $dbStatus['ok'] = true;
        $dbStatus['label'] = 'SQLite подключена';
        $dbStatus['details'] = 'Файл базы найден, соединение через PDO работает.';
        $dbStatus['packs_count'] = (int) $pdo->query('SELECT COUNT(*) FROM packs')->fetchColumn();
        $dbStatus['predictions_count'] = (int) $pdo->query('SELECT COUNT(*) FROM predictions')->fetchColumn();
    } catch (Throwable $exception) {
        $dbStatus['details'] = $exception->getMessage();
    }

    $currentUser = $authService->currentUser();
    $analyticsService->pageView('/', is_array($currentUser) ? (int) $currentUser['id'] : null);

    Response::view('home', [
        'title' => (string) $config['APP_NAME'],
        'pageStyles' => ['css/game-cards.css'],
        'appName' => (string) $config['APP_NAME'],
        'appEnv' => (string) $config['APP_ENV'],
        'dbStatus' => $dbStatus,
        'currentUser' => $currentUser,
        'csrfToken' => Security::csrfToken(),
        'uiTexts' => $uiTextService->publicMap(),
        'messages' => Session::consumeFlash('success'),
        'errors' => Session::consumeFlash('error'),
    ]);
});

$router->get('/open', static function () use ($config, $authService, $analyticsService, $uiTextService): void {
    $packSlug = trim((string) ($_GET['pack'] ?? ''));

    if ($packSlug === '') {
        Response::redirect('/#packs');
    }

    if (!preg_match('/^[a-z0-9-]+$/', $packSlug)) {
        Response::redirect('/#packs');
    }

    $currentUser = $authService->currentUser();
    $analyticsService->pageView('/open', is_array($currentUser) ? (int) $currentUser['id'] : null, null, [
        'pack' => $packSlug,
    ]);

    Response::view('open_pack', [
        'title' => 'Открыть пак — ' . (string) $config['APP_NAME'],
        'pageStyles' => ['css/game-cards.css?v=open-card-fix-20260523', 'css/open-pack.css?v=open-card-fix-20260523'],
        'pageScripts' => ['js/open-pack.js?v=open-card-fix-20260523'],
        'appName' => (string) $config['APP_NAME'],
        'currentUser' => $currentUser,
        'initialPackSlug' => $packSlug,
        'csrfToken' => Security::csrfToken(),
        'uiTexts' => $uiTextService->publicMap(),
    ]);
});

$router->get('/login', [$authController, 'showLogin']);
$router->get('/register', [$authController, 'showRegister']);
$router->get('/cabinet', [$cabinetController, 'show']);
$router->get('/profile', static function (): void {
    Response::redirect('/cabinet');
});
$router->get('/admin', [$adminController, 'dashboard']);
$router->get('/admin/packs', [$adminController, 'packsPage']);
$router->get('/admin/packs/create', [$adminController, 'createPackPage']);
$router->get('/admin/packs/edit', [$adminController, 'editPackPage']);
$router->get('/admin/predictions', [$adminController, 'predictionsPage']);
$router->get('/admin/predictions/create', [$adminController, 'createPredictionPage']);
$router->get('/admin/predictions/edit', [$adminController, 'editPredictionPage']);
$router->get('/admin/users', [$adminController, 'usersPage']);
$router->get('/admin/analytics', [$adminController, 'analyticsPage']);
$router->get('/admin/texts', [$adminController, 'textsPage']);
$router->get('/admin/logs', [$adminController, 'logsPage']);
$router->post('/login', [$authController, 'loginHtml']);
$router->post('/register', [$authController, 'registerHtml']);
$router->post('/logout', [$authController, 'logoutHtml']);

$router->get('/api/health', [$apiController, 'health']);
$router->get('/api/packs', [$apiController, 'packs']);
$router->get('/api/auth/me', [$apiController, 'me']);
$router->post('/api/auth/register', [$authController, 'registerApi']);
$router->post('/api/auth/login', [$authController, 'loginApi']);
$router->post('/api/auth/logout', [$authController, 'logoutApi']);
$router->post('/api/open-pack', [$apiController, 'openPack']);
$router->post('/api/save-card', [$apiController, 'saveCard']);
$router->get('/api/history', [$apiController, 'history']);
$router->get('/api/stats', [$apiController, 'stats']);
$router->get('/api/ui-texts', static fn (): never => Response::json(['ok' => true, 'texts' => $uiTextService->publicMap()]));
$router->get('/api/cabinet/summary', [$cabinetController, 'summary']);
$router->get('/api/cabinet/saved', [$cabinetController, 'saved']);
$router->post('/api/cabinet/saved/update-note', [$cabinetController, 'updateNote']);
$router->post('/api/cabinet/saved/delete', [$cabinetController, 'deleteSaved']);
$router->get('/api/cabinet/history', [$cabinetController, 'history']);
$router->post('/api/cabinet/profile/update', [$cabinetController, 'updateProfile']);
$router->post('/api/cabinet/profile/change-password', [$cabinetController, 'changePassword']);

$router->get('/admin/api/summary', [$adminController, 'summaryApi']);
$router->get('/admin/api/packs', [$adminController, 'packsApi']);
$router->post('/admin/api/packs/create', [$adminController, 'createPackApi']);
$router->post('/admin/api/packs/update', [$adminController, 'updatePackApi']);
$router->post('/admin/api/packs/toggle', [$adminController, 'togglePackApi']);
$router->post('/admin/api/packs/reorder', [$adminController, 'reorderPacksApi']);
$router->get('/admin/api/predictions', [$adminController, 'predictionsApi']);
$router->post('/admin/api/predictions/create', [$adminController, 'createPredictionApi']);
$router->post('/admin/api/predictions/update', [$adminController, 'updatePredictionApi']);
$router->post('/admin/api/predictions/toggle', [$adminController, 'togglePredictionApi']);
$router->get('/admin/api/users', [$adminController, 'usersApi']);
$router->post('/admin/api/users/toggle-block', [$adminController, 'toggleUserBlockApi']);
$router->post('/admin/api/users/update-role', [$adminController, 'updateUserRoleApi']);
$router->get('/admin/api/analytics', [$adminController, 'analyticsApi']);
$router->get('/admin/api/texts', [$adminController, 'textsApi']);
$router->post('/admin/api/texts/update', [$adminController, 'updateTextApi']);
$router->get('/admin/api/logs', [$adminController, 'logsApi']);

$router->dispatch();
