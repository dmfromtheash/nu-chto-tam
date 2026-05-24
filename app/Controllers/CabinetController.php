<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\CabinetService;
use App\Services\UiTextService;
use InvalidArgumentException;
use Throwable;

final class CabinetController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CabinetService $cabinet,
        private readonly AnalyticsService $analytics,
        private readonly UiTextService $uiTexts
    ) {
    }

    public function show(): void
    {
        $user = $this->auth->currentUser();
        $this->analytics->pageView('/cabinet', is_array($user) ? (int) $user['id'] : null);

        Response::view('cabinet', [
            'title' => 'Кабинет',
            'currentUser' => $user,
            'csrfToken' => Security::csrfToken(),
            'pageScripts' => ['js/cabinet.js'],
            'uiTexts' => $this->uiTexts->publicMap(),
            'messages' => Session::consumeFlash('success'),
            'errors' => Session::consumeFlash('error'),
        ]);
    }

    public function summary(): never
    {
        $user = $this->requireUser();
        $summary = $this->cabinet->summary($user);

        Response::json([
            'ok' => true,
            'user' => $summary['user'],
            'stats' => $summary['stats'],
        ]);
    }

    public function saved(): never
    {
        $user = $this->requireUser();

        Response::json([
            'ok' => true,
            'cards' => $this->cabinet->savedCards((int) $user['id']),
        ]);
    }

    public function updateNote(): never
    {
        $user = $this->requireUser();
        $input = $this->jsonInput();
        $savedId = $this->intFromInput($input['saved_id'] ?? null, 'saved_id');

        try {
            $this->cabinet->updateNote((int) $user['id'], $savedId, (string) ($input['note'] ?? ''));

            Response::json([
                'ok' => true,
                'message' => 'Заметка сохранена. Теперь карточка выглядит чуть более твоей.',
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось сохранить заметку. Попробуй ещё раз.'], 500);
        }
    }

    public function deleteSaved(): never
    {
        $user = $this->requireUser();
        $input = $this->jsonInput();
        $savedId = $this->intFromInput($input['saved_id'] ?? null, 'saved_id');

        try {
            $this->cabinet->deleteSaved((int) $user['id'], $savedId);

            Response::json([
                'ok' => true,
                'message' => 'Карточка убрана из коллекции. Без драматичной музыки.',
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось удалить карточку. Попробуй ещё раз.'], 500);
        }
    }

    public function history(): never
    {
        $user = $this->requireUser();

        try {
            Response::json([
                'ok' => true,
                'history' => $this->cabinet->history((int) $user['id'], $_GET),
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'История не загрузилась. Рандом оставил кружку не там.'], 500);
        }
    }

    public function updateProfile(): never
    {
        $user = $this->requireUser();
        $input = $this->jsonInput();

        try {
            $updatedUser = $this->cabinet->updateUsername((int) $user['id'], (string) ($input['username'] ?? ''));
            $this->analytics->track('profile_update', [
                'page' => '/api/cabinet/profile/update',
                'user_id' => (int) $user['id'],
                'entity_type' => 'user',
                'entity_id' => (int) $user['id'],
            ]);

            Response::json([
                'ok' => true,
                'message' => 'Имя обновлено. Табличку на двери поменяли.',
                'user' => User::publicData($updatedUser),
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось обновить профиль. Попробуй ещё раз.'], 500);
        }
    }

    public function changePassword(): never
    {
        $user = $this->requireUser();
        $input = $this->jsonInput();

        try {
            $this->cabinet->changePassword(
                (int) $user['id'],
                (string) ($input['current_password'] ?? ''),
                (string) ($input['new_password'] ?? ''),
                (string) ($input['new_password_confirm'] ?? '')
            );
            $this->analytics->track('password_change', [
                'page' => '/api/cabinet/profile/change-password',
                'user_id' => (int) $user['id'],
                'entity_type' => 'user',
                'entity_id' => (int) $user['id'],
            ]);

            Response::json([
                'ok' => true,
                'message' => 'Пароль обновлён. Старый можно мысленно проводить до двери.',
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось сменить пароль. Попробуй ещё раз.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requireUser(): array
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            Response::json([
                'ok' => false,
                'error' => 'Кабинет доступен после входа.',
            ], 401);
        }

        return $user;
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
            Response::json(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        return $decoded;
    }

    private function intFromInput(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        Response::json(['ok' => false, 'error' => 'Укажи ' . $field . '.'], 422);
    }
}
