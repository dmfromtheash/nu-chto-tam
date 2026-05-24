<?php

declare(strict_types=1);

use App\Core\Security;

$currentUserId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
?>
<header class="admin-page-head">
    <div>
        <p class="eyebrow">Аккаунты</p>
        <h1>Users</h1>
        <p>Пароли не показываем, пользователей не удаляем. Только роль и блокировка.</p>
    </div>
</header>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Last login</th>
                    <th>Openings</th>
                    <th>Saved</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $isSelf = (int) $user['id'] === $currentUserId; ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= Security::escape((string) $user['username']) ?><?= $isSelf ? ' <span class="muted-mini">(ты)</span>' : '' ?></td>
                        <td><?= Security::escape((string) ($user['email'] ?? '')) ?></td>
                        <td>
                            <form class="inline-form" data-admin-form data-endpoint="/admin/api/users/update-role" data-success-action="reload" data-confirm="Изменить роль пользователя?">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <select name="role" aria-label="role">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                </select>
                                <button class="text-button" type="submit">Save</button>
                            </form>
                        </td>
                        <td><?= Security::escape((string) $user['created_at']) ?></td>
                        <td><?= Security::escape((string) ($user['last_login_at'] ?? '—')) ?></td>
                        <td><?= (int) $user['openings_count'] ?></td>
                        <td><?= (int) $user['saved_count'] ?></td>
                        <td>
                            <span class="status-pill <?= (int) $user['is_blocked'] === 1 ? 'is-off' : 'is-on' ?>">
                                <?= (int) $user['is_blocked'] === 1 ? 'blocked' : 'active' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isSelf): ?>
                                <span class="muted-mini">себя не блокируем</span>
                            <?php else: ?>
                                <form data-admin-form data-endpoint="/admin/api/users/toggle-block" data-success-action="reload" data-confirm="Переключить блокировку пользователя?">
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button class="text-button" type="submit"><?= (int) $user['is_blocked'] === 1 ? 'Unblock' : 'Block' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
