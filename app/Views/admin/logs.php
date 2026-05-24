<?php

declare(strict_types=1);

use App\Core\Security;
?>
<header class="admin-page-head">
    <div>
        <p class="eyebrow">Журнал</p>
        <h1>Admin logs</h1>
        <p>Последние 100 действий. Секреты и пароли сюда не пишем.</p>
    </div>
</header>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Когда</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Payload</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= (int) $log['id'] ?></td>
                        <td><?= Security::escape((string) $log['created_at']) ?></td>
                        <td><?= Security::escape((string) ($log['admin_username'] ?? 'system')) ?></td>
                        <td><code><?= Security::escape((string) $log['action']) ?></code></td>
                        <td><?= Security::escape((string) ($log['entity_type'] ?? '-')) ?> #<?= Security::escape((string) ($log['entity_id'] ?? '-')) ?></td>
                        <td><pre class="payload-preview"><?= Security::escape((string) ($log['payload_json'] ?? '')) ?></pre></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($logs === []): ?>
                    <tr><td colspan="6">Логов пока нет. Блокнот чистый, даже немного подозрительно.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
