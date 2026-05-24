<?php

declare(strict_types=1);

use App\Core\Security;

$message = isset($message) ? (string) $message : 'Доступ закрыт.';
?>
<section class="admin-panel admin-empty">
    <p class="eyebrow">403</p>
    <h1>Сюда пока нельзя</h1>
    <p><?= Security::escape($message) ?></p>
    <div class="button-row">
        <a class="button" href="/login">Войти</a>
        <a class="button is-secondary" href="/">На главную</a>
    </div>
</section>
