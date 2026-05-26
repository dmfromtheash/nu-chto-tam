<?php

declare(strict_types=1);

use App\Core\Security;

$errors = $errors ?? [];
$messages = $messages ?? [];
?>
<main class="page-shell">
    <section class="glass-panel narrow-panel" aria-labelledby="register-title">
        <p class="eyebrow">Аккаунт</p>
        <h1 id="register-title">Регистрация</h1>
        <p class="lead">Аккаунт хранит историю открытий, коллекцию сохранённых карточек и заметки — из этого позже сможет вырасти “Портрет Судьбы” / “Лор Личности”. Если ты уже открывал карточки как гость, после регистрации эти открытия сохранятся в истории аккаунта.</p>

        <?php foreach ($messages as $message): ?>
            <p class="notice is-success"><?= Security::escape($message) ?></p>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <p class="notice is-error"><?= Security::escape($error) ?></p>
        <?php endforeach; ?>

        <form class="simple-form" method="post" action="/register">
            <input type="hidden" name="_csrf_token" value="<?= Security::escape($csrfToken) ?>">

            <label>
                Имя
                <input type="text" name="username" autocomplete="username" maxlength="60" required>
            </label>

            <label>
                Email
                <input type="email" name="email" autocomplete="email" required>
            </label>

            <label>
                Пароль
                <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            </label>

            <label>
                Повтор пароля
                <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
            </label>

            <div class="button-row">
                <button class="button" type="submit">Создать аккаунт</button>
                <a class="button is-secondary" href="/login">Уже есть вход</a>
                <a class="button is-secondary" href="/">На главную</a>
            </div>
        </form>
    </section>
</main>
