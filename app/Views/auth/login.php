<?php

declare(strict_types=1);

use App\Core\Security;

$errors = $errors ?? [];
$messages = $messages ?? [];
?>
<main class="page-shell">
    <section class="glass-panel narrow-panel" aria-labelledby="login-title">
        <p class="eyebrow">Аккаунт</p>
        <h1 id="login-title">Вход</h1>
        <p class="lead">Для сохранения карточек в коллекцию нужен аккаунт. Без аккаунта сайт тоже работает в гостевом режиме.</p>

        <?php foreach ($messages as $message): ?>
            <p class="notice is-success"><?= Security::escape($message) ?></p>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <p class="notice is-error"><?= Security::escape($error) ?></p>
        <?php endforeach; ?>

        <form class="simple-form" method="post" action="/login">
            <input type="hidden" name="_csrf_token" value="<?= Security::escape($csrfToken) ?>">

            <label>
                Email
                <input type="email" name="email" autocomplete="email" required>
            </label>

            <label>
                Пароль
                <input type="password" name="password" autocomplete="current-password" required>
            </label>

            <div class="button-row">
                <button class="button" type="submit">Войти</button>
                <a class="button is-secondary" href="/register">Регистрация</a>
            </div>
        </form>
    </section>
</main>
