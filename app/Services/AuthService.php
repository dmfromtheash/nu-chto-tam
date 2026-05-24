<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

final class AuthService
{
    /**
     * @return array<string, mixed>
     */
    public function register(string $username, string $email, string $password, string $passwordConfirm): array
    {
        $username = trim($username);
        $email = strtolower(trim($email));

        if (!Validator::required($username)) {
            throw new InvalidArgumentException('Укажи имя пользователя.');
        }

        if (!Validator::maxLength($username, 60)) {
            throw new InvalidArgumentException('Имя пользователя слишком длинное.');
        }

        if (!Validator::required($email) || !Validator::email($email)) {
            throw new InvalidArgumentException('Укажи корректный email.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Пароль должен быть не короче 8 символов.');
        }

        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('Пароль и подтверждение не совпадают.');
        }

        if (User::emailExists($email)) {
            throw new InvalidArgumentException('Пользователь с таким email уже есть.');
        }

        $userId = User::create($username, $email, password_hash($password, PASSWORD_DEFAULT));
        $user = User::findById($userId);

        if ($user === null) {
            throw new RuntimeException('Не удалось создать пользователя.');
        }

        $this->loginUser($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if (!Validator::required($email) || !Validator::email($email) || !Validator::required($password)) {
            throw new InvalidArgumentException('Укажи email и пароль.');
        }

        $user = User::findByEmail($email);

        if ($user === null || !is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('Email или пароль не подошли.');
        }

        if ((int) $user['is_blocked'] === 1) {
            throw new InvalidArgumentException('Аккаунт заблокирован.');
        }

        User::updateLastLoginAt((int) $user['id']);
        $freshUser = User::findById((int) $user['id']) ?? $user;
        $this->loginUser($freshUser);

        return $freshUser;
    }

    public function logout(): void
    {
        $guestId = Session::get('guest_id');

        Session::forget('user_id');
        Session::forget('role');
        Session::regenerate();

        if (is_string($guestId) && $guestId !== '') {
            Session::set('guest_id', $guestId);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        $userId = Session::userId();

        if ($userId === null) {
            return null;
        }

        $user = User::findById($userId);

        if ($user === null || (int) $user['is_blocked'] === 1) {
            $this->logout();
            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function loginUser(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('role', (string) $user['role']);
    }
}
