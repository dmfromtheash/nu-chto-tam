<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\GuestService;
use App\Services\RateLimiter;
use InvalidArgumentException;
use Throwable;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly GuestService $guest,
        private readonly RateLimiter $rateLimiter,
        private readonly AnalyticsService $analytics
    ) {
    }

    public function showLogin(): void
    {
        if ($this->auth->currentUser() !== null) {
            Response::redirect('/');
        }

        Response::view('auth/login', [
            'title' => 'Вход',
            'csrfToken' => Security::csrfToken(),
            'errors' => Session::consumeFlash('error'),
            'messages' => Session::consumeFlash('success'),
        ]);
    }

    public function showRegister(): void
    {
        if ($this->auth->currentUser() !== null) {
            Response::redirect('/');
        }

        Response::view('auth/register', [
            'title' => 'Регистрация',
            'csrfToken' => Security::csrfToken(),
            'errors' => Session::consumeFlash('error'),
            'messages' => Session::consumeFlash('success'),
        ]);
    }

    public function loginHtml(): never
    {
        if (!Security::verifyCsrfToken($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Сессия формы устарела. Попробуй ещё раз.');
            Response::redirect('/login');
        }

        $email = (string) ($_POST['email'] ?? '');
        $limit = $this->rateLimiter->hit('html', $this->authIdentifier($email), 'login', 10, 600);

        if (!$limit['allowed']) {
            Session::flash('error', 'Слишком много попыток. Попробуй чуть позже.');
            Response::redirect('/login');
        }

        try {
            $user = $this->auth->login($email, (string) ($_POST['password'] ?? ''));
            $this->analytics->bindUser((int) $user['id']);
            $this->analytics->track('login', [
                'page' => '/login',
                'user_id' => (int) $user['id'],
            ]);
            Session::flash('success', 'Вход выполнен.');
            Response::redirect('/');
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            Response::redirect('/login');
        }
    }

    public function registerHtml(): never
    {
        if (!Security::verifyCsrfToken($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Сессия формы устарела. Попробуй ещё раз.');
            Response::redirect('/register');
        }

        $email = (string) ($_POST['email'] ?? '');
        $limit = $this->rateLimiter->hit('html', $this->authIdentifier($email), 'register', 10, 600);

        if (!$limit['allowed']) {
            Session::flash('error', 'Слишком много попыток. Попробуй чуть позже.');
            Response::redirect('/register');
        }

        try {
            $user = $this->auth->register(
                (string) ($_POST['username'] ?? ''),
                $email,
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirm'] ?? '')
            );
            $this->analytics->bindUser((int) $user['id']);
            $this->analytics->track('register', [
                'page' => '/register',
                'user_id' => (int) $user['id'],
            ]);
            Session::flash('success', 'Регистрация готова. Ты вошёл в аккаунт.');
            Response::redirect('/');
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            Response::redirect('/register');
        }
    }

    public function logoutHtml(): never
    {
        if (!Security::verifyCsrfToken($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Сессия формы устарела. Попробуй ещё раз.');
            Response::redirect('/');
        }

        $user = $this->auth->currentUser();
        $this->analytics->track('logout', [
            'page' => '/',
            'user_id' => is_array($user) ? (int) $user['id'] : null,
        ]);
        $this->auth->logout();
        Session::flash('success', 'Выход выполнен. Гостевой режим остался на месте.');
        Response::redirect('/');
    }

    public function registerApi(): never
    {
        $input = $this->jsonInput();
        $email = (string) ($input['email'] ?? '');
        $limit = $this->rateLimiter->hit('api', $this->authIdentifier($email), 'register', 10, 600);

        if (!$limit['allowed']) {
            $this->tooManyAttempts();
        }

        try {
            $user = $this->auth->register(
                (string) ($input['username'] ?? ''),
                $email,
                (string) ($input['password'] ?? ''),
                (string) ($input['password_confirm'] ?? '')
            );
            $this->analytics->bindUser((int) $user['id']);
            $this->analytics->track('register', [
                'page' => '/api/auth/register',
                'user_id' => (int) $user['id'],
            ]);

            Response::json([
                'ok' => true,
                'user' => User::publicData($user),
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось зарегистрироваться.'], 500);
        }
    }

    public function loginApi(): never
    {
        $input = $this->jsonInput();
        $email = (string) ($input['email'] ?? '');
        $limit = $this->rateLimiter->hit('api', $this->authIdentifier($email), 'login', 10, 600);

        if (!$limit['allowed']) {
            $this->tooManyAttempts();
        }

        try {
            $user = $this->auth->login($email, (string) ($input['password'] ?? ''));
            $this->analytics->bindUser((int) $user['id']);
            $this->analytics->track('login', [
                'page' => '/api/auth/login',
                'user_id' => (int) $user['id'],
            ]);

            Response::json([
                'ok' => true,
                'user' => User::publicData($user),
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 401);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось выполнить вход.'], 500);
        }
    }

    public function logoutApi(): never
    {
        $user = $this->auth->currentUser();
        $this->analytics->track('logout', [
            'page' => '/api/auth/logout',
            'user_id' => is_array($user) ? (int) $user['id'] : null,
        ]);
        $this->auth->logout();

        Response::json([
            'ok' => true,
            'guest_id' => $this->guest->getOrCreateGuestId(),
            'authenticated' => false,
        ]);
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
                'error' => 'Для API-запроса нужен Content-Type: application/json.',
            ], 415);
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            Response::json([
                'ok' => false,
                'error' => 'Некорректный JSON.',
            ], 400);
        }

        return $decoded;
    }

    private function authIdentifier(string $email): string
    {
        return $this->rateLimiter->clientIp() . ':' . strtolower(trim($email));
    }

    private function tooManyAttempts(): never
    {
        Response::json([
            'ok' => false,
            'error' => 'Слишком много попыток. Попробуй чуть позже.',
        ], 429);
    }
}
