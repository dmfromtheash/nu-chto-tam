<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Security;
use App\Services\AdminService;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\UiTextService;
use InvalidArgumentException;
use Throwable;

final class AdminController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AdminService $admin,
        private readonly AnalyticsService $analytics,
        private readonly UiTextService $uiTexts
    ) {
    }

    public function dashboard(): void
    {
        $user = $this->requireAdminHtml();
        $this->analytics->pageView('/admin', (int) $user['id']);

        Response::view('admin/dashboard', [
            ...$this->viewData($user, 'Dashboard', 'dashboard'),
            'summary' => $this->admin->summary($_GET),
            'filters' => $_GET,
        ], 'admin/layout');
    }

    public function packsPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/packs', [
            ...$this->viewData($user, 'Packs', 'packs'),
            'packs' => $this->admin->packs(),
        ], 'admin/layout');
    }

    public function createPackPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/pack_form', [
            ...$this->viewData($user, 'Создать пак', 'packs'),
            'mode' => 'create',
            'pack' => null,
            'packTypes' => $this->admin->packTypes(),
        ], 'admin/layout');
    }

    public function editPackPage(): void
    {
        $user = $this->requireAdminHtml();
        $pack = $this->admin->findPack($this->queryId());

        if ($pack === null) {
            http_response_code(404);
            Response::view('admin/forbidden', [
                ...$this->viewData($user, 'Пак не найден', 'packs'),
                'message' => 'Пак не найден. Возможно, он уже уехал в другой список.',
            ], 'admin/layout');
            return;
        }

        Response::view('admin/pack_form', [
            ...$this->viewData($user, 'Редактировать пак', 'packs'),
            'mode' => 'edit',
            'pack' => $pack,
            'packTypes' => $this->admin->packTypes(),
        ], 'admin/layout');
    }

    public function predictionsPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/predictions', [
            ...$this->viewData($user, 'Predictions', 'predictions'),
            'predictions' => $this->admin->predictions($_GET),
            'packs' => $this->admin->packOptions(),
            'rarities' => $this->admin->rarities(),
            'filters' => $_GET,
        ], 'admin/layout');
    }

    public function createPredictionPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/prediction_form', [
            ...$this->viewData($user, 'Создать карточку', 'predictions'),
            'mode' => 'create',
            'prediction' => null,
            'packs' => $this->admin->packOptions(),
            'rarities' => $this->admin->rarities(),
        ], 'admin/layout');
    }

    public function editPredictionPage(): void
    {
        $user = $this->requireAdminHtml();
        $prediction = $this->admin->findPrediction($this->queryId());

        if ($prediction === null) {
            http_response_code(404);
            Response::view('admin/forbidden', [
                ...$this->viewData($user, 'Карточка не найдена', 'predictions'),
                'message' => 'Карточка не найдена. Может, она ушла за кофе.',
            ], 'admin/layout');
            return;
        }

        Response::view('admin/prediction_form', [
            ...$this->viewData($user, 'Редактировать карточку', 'predictions'),
            'mode' => 'edit',
            'prediction' => $prediction,
            'packs' => $this->admin->packOptions(),
            'rarities' => $this->admin->rarities(),
        ], 'admin/layout');
    }

    public function usersPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/users', [
            ...$this->viewData($user, 'Users', 'users'),
            'users' => $this->admin->users(),
        ], 'admin/layout');
    }

    public function logsPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/logs', [
            ...$this->viewData($user, 'Logs', 'logs'),
            'logs' => $this->admin->logs(100),
        ], 'admin/layout');
    }

    public function analyticsPage(): void
    {
        $user = $this->requireAdminHtml();
        $this->analytics->pageView('/admin/analytics', (int) $user['id']);

        Response::view('admin/analytics', [
            ...$this->viewData($user, 'Analytics', 'analytics'),
            'analytics' => $this->admin->analyticsOverview($_GET),
            'events' => $this->admin->analyticsEvents($_GET),
            'eventTypes' => $this->admin->eventTypes(),
            'filters' => $_GET,
        ], 'admin/layout');
    }

    public function textsPage(): void
    {
        $user = $this->requireAdminHtml();

        Response::view('admin/texts', [
            ...$this->viewData($user, 'Texts', 'texts'),
            'groups' => $this->uiTexts->groups(),
        ], 'admin/layout');
    }

    public function summaryApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, 'summary' => $this->admin->summary($_GET)]);
    }

    public function packsApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, 'packs' => $this->admin->packs()]);
    }

    public function createPackApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $id = $this->admin->createPack($input, (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'create_pack', 'pack', $id);

            return ['message' => 'Пак создан. Можно делать вид, что так и было задумано.', 'id' => $id];
        });
    }

    public function updatePackApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $this->admin->updatePack($input, (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'update_pack', 'pack', $this->intFromInput($input['id'] ?? null, 'id'));

            return ['message' => 'Пак обновлён. Без конфетти, но по делу.'];
        });
    }

    public function togglePackApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $status = $this->admin->togglePack($this->intFromInput($input['id'] ?? null, 'id'), (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'toggle_pack', 'pack', $this->intFromInput($input['id'] ?? null, 'id'), [
                'is_active' => $status,
            ]);

            return ['message' => $status === 1 ? 'Пак включён.' : 'Пак выключен.', 'is_active' => $status];
        });
    }

    public function reorderPacksApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $items = $input['items'] ?? [];

            if (!is_array($items)) {
                throw new InvalidArgumentException('Передай items для сортировки.');
            }

            $this->admin->reorderPacks(array_values($items), (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'reorder_pack', 'pack', null, [
                'items_count' => count($items),
            ]);

            return ['message' => 'Порядок паков сохранён. Таблички переставлены.'];
        });
    }

    public function predictionsApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, 'predictions' => $this->admin->predictions($_GET)]);
    }

    public function createPredictionApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $id = $this->admin->createPrediction($input, (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'create_prediction', 'prediction', $id);

            return ['message' => 'Карточка создана. Рандом получил новую реплику.', 'id' => $id];
        });
    }

    public function updatePredictionApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $this->admin->updatePrediction($input, (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'update_prediction', 'prediction', $this->intFromInput($input['id'] ?? null, 'id'));

            return ['message' => 'Карточка обновлена.'];
        });
    }

    public function togglePredictionApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $status = $this->admin->togglePrediction($this->intFromInput($input['id'] ?? null, 'id'), (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'toggle_prediction', 'prediction', $this->intFromInput($input['id'] ?? null, 'id'), [
                'is_active' => $status,
            ]);

            return ['message' => $status === 1 ? 'Карточка включена.' : 'Карточка выключена.', 'is_active' => $status];
        });
    }

    public function usersApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, 'users' => $this->admin->users()]);
    }

    public function toggleUserBlockApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $status = $this->admin->toggleUserBlock($this->intFromInput($input['id'] ?? null, 'id'), (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], $status === 1 ? 'block_user' : 'unblock_user', 'user', $this->intFromInput($input['id'] ?? null, 'id'), [
                'is_blocked' => $status,
            ]);

            return ['message' => $status === 1 ? 'Пользователь заблокирован.' : 'Пользователь разблокирован.', 'is_blocked' => $status];
        });
    }

    public function updateUserRoleApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $this->admin->updateUserRole(
                $this->intFromInput($input['id'] ?? null, 'id'),
                (string) ($input['role'] ?? ''),
                (int) $user['id']
            );
            $this->trackAdminAction((int) $user['id'], 'update_user_role', 'user', $this->intFromInput($input['id'] ?? null, 'id'), [
                'role' => (string) ($input['role'] ?? ''),
            ]);

            return ['message' => 'Роль обновлена. Главное, что кто-то всё ещё admin.'];
        });
    }

    public function logsApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, 'logs' => $this->admin->logs(100)]);
    }

    public function analyticsApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, ...$this->admin->analyticsOverview($_GET)]);
    }

    public function textsApi(): never
    {
        $this->requireAdminApi();
        Response::json(['ok' => true, 'groups' => $this->uiTexts->groups()]);
    }

    public function updateTextApi(): never
    {
        $user = $this->requireAdminApi();
        $input = $this->jsonInput();
        $this->verifyCsrf($input);
        $this->jsonAction(function () use ($input, $user): array {
            $key = isset($input['key']) ? trim((string) $input['key']) : '';
            $value = isset($input['value']) ? (string) $input['value'] : '';
            $this->uiTexts->update($key, $value, (int) $user['id']);
            $this->trackAdminAction((int) $user['id'], 'update_ui_text', 'ui_text', null, ['key' => $key]);

            return ['message' => 'Текст сохранён. Обнови нужную страницу и проверь, как он лёг в интерфейс.'];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAdminHtml(): array
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            Response::redirect('/login');
        }

        if (($user['role'] ?? null) !== 'admin') {
            http_response_code(403);
            Response::view('admin/forbidden', [
                ...$this->viewData($user, 'Доступ закрыт', ''),
                'message' => 'Админка доступна только пользователю с ролью admin.',
            ], 'admin/layout');
            exit;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAdminApi(): array
    {
        $user = $this->auth->currentUser();

        if ($user === null || ($user['role'] ?? null) !== 'admin') {
            Response::json([
                'ok' => false,
                'error' => 'Админка доступна только пользователю с ролью admin.',
            ], 403);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function verifyCsrf(array $input): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['_csrf_token'] ?? $input['csrf_token'] ?? null;

        if (!Security::verifyCsrfToken(is_string($token) ? $token : null)) {
            Response::json([
                'ok' => false,
                'error' => 'Сессия формы устарела. Обнови страницу и попробуй ещё раз.',
            ], 419);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonInput(): array
    {
        $raw = (string) file_get_contents('php://input');
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if ($raw !== '' && !str_contains($contentType, 'application/json')) {
            Response::json([
                'ok' => false,
                'error' => 'Для admin API нужен Content-Type: application/json.',
            ], 415);
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            Response::json(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        return $decoded;
    }

    /**
     * @param callable(): array<string, mixed> $callback
     */
    private function jsonAction(callable $callback): never
    {
        try {
            Response::json(['ok' => true, ...$callback()]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Админ-действие не выполнилось. Попробуй ещё раз.'], 500);
        }
    }

    private function queryId(): int
    {
        try {
            return $this->intFromInput($_GET['id'] ?? null, 'id');
        } catch (InvalidArgumentException $exception) {
            return 0;
        }
    }

    private function intFromInput(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Укажи ' . $field . '.');
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function viewData(array $user, string $title, string $activeNav): array
    {
        return [
            'title' => $title . ' · Admin',
            'currentUser' => $user,
            'csrfToken' => Security::csrfToken(),
            'activeNav' => $activeNav,
            'uiTexts' => $this->uiTexts->publicMap(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function trackAdminAction(int $adminId, string $action, ?string $entityType, ?int $entityId, array $payload = []): void
    {
        $this->analytics->track('admin_action', [
            'page' => '/admin/api',
            'user_id' => $adminId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => [
                'action' => $action,
                ...$payload,
            ],
        ]);
    }
}
