<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';
require_once __DIR__ . '/../app/sidebar.php';

$user = require_login();
$token = csrf_token();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$adminUser = is_admin();
$pendingInvites = list_pending_invites($user['id']);
$sentInvites = list_sent_invites($user['id']);
$pendingResets = $adminUser ? list_pending_password_resets() : [];
$recentResets = $adminUser ? list_recent_password_resets() : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAS</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 512'%3E%3Cpath fill='%23991B32' d='M0 336c0 79.5 64.5 144 144 144l368 0c70.7 0 128-57.3 128-128c0-61.9-44-113.6-102.4-125.4c4.1-10.7 6.4-22.4 6.4-34.6c0-53-43-96-96-96c-19.7 0-38.1 6-53.3 16.2C367 64.2 315.3 32 256 32C167.6 32 96 103.6 96 192c0 2.7 .1 5.4 .2 8.1C40.2 219.8 0 273.2 0 336z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <div class="layout">
        <?php render_sidebar($user, 'inbox', $token); ?>

        <main class="main">
            <div class="topbar">
                <h1><i class="fa-solid fa-inbox"></i> Buzón</h1>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <div class="alert-body">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <span><?= h($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($adminUser): ?>
                <h2 class="section-title"><i class="fa-solid fa-key"></i> Solicitudes de cambio de contraseña (<?= count($pendingResets) ?>)</h2>
                <?php if (empty($pendingResets)): ?>
                    <div class="card">
                        <div class="empty-state" style="padding:28px 20px;">
                            <p>No hay solicitudes pendientes de cambio de contraseña.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingResets as $req): ?>
                        <div class="invite-card">
                            <div class="invite-info">
                                <div class="folder-icon-wrap"><i class="fa-solid fa-key"></i></div>
                                <div>
                                    <div style="font-size:0.95rem;">
                                        <strong><?= h($req['username']) ?></strong>
                                        <span style="color:var(--muted);">(<?= h($req['email']) ?>)</span>
                                        solicitó permiso para restablecer su contraseña
                                    </div>
                                    <div style="font-size:0.78rem;color:var(--muted);margin-top:4px;">
                                        Solicitado: <?= h($req['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="invite-actions">
                                <form method="POST" action="/action.php">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="action" value="review_password_reset">
                                    <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-solid fa-check"></i> Dar permiso
                                    </button>
                                </form>
                                <form method="POST" action="/action.php">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="action" value="review_password_reset">
                                    <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="btn btn-danger-outline">
                                        <i class="fa-solid fa-xmark"></i> Rechazar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <h2 class="section-title"><i class="fa-solid fa-folder-open"></i> Invitaciones pendientes (<?= count($pendingInvites) ?>)</h2>
            <?php if (empty($pendingInvites)): ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fa-regular fa-envelope-open"></i>
                        <p>No tienes invitaciones pendientes en este momento.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pendingInvites as $inv): ?>
                    <div class="invite-card">
                        <div class="invite-info">
                            <div class="folder-icon-wrap"><i class="fa-solid fa-folder-plus"></i></div>
                            <div>
                                <div style="font-size:0.95rem;">
                                    <strong><?= h($inv['owner_username']) ?></strong>
                                    <span style="color:var(--muted);">(<?= h($inv['owner_email']) ?>)</span>
                                    te invitó a colaborar en la carpeta
                                    <strong><?= h($inv['folder_name']) ?></strong>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap;">
                                    <?php if ($inv['permission'] === 'write'): ?>
                                        <span class="pill pill-green"><i class="fa-solid fa-pen"></i> Permiso: Modificar</span>
                                    <?php else: ?>
                                        <span class="pill pill-blue"><i class="fa-regular fa-eye"></i> Permiso: Solo ver y descargar</span>
                                    <?php endif; ?>
                                    <span style="font-size:0.78rem;color:var(--muted);"><?= h($inv['created_at']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="invite-actions">
                            <form method="POST" action="/action.php">
                                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                <input type="hidden" name="action" value="respond_invite">
                                <input type="hidden" name="share_id" value="<?= (int) $inv['id'] ?>">
                                <input type="hidden" name="decision" value="accept">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa-solid fa-check"></i> Aceptar
                                </button>
                            </form>
                            <form method="POST" action="/action.php">
                                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                <input type="hidden" name="action" value="respond_invite">
                                <input type="hidden" name="share_id" value="<?= (int) $inv['id'] ?>">
                                <input type="hidden" name="decision" value="decline">
                                <button type="submit" class="btn btn-danger-outline">
                                    <i class="fa-solid fa-xmark"></i> Rechazar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($sentInvites)): ?>
                <h2 class="section-title" style="margin-top:8px;">Invitaciones enviadas por ti (<?= count($sentInvites) ?>)</h2>
                <div class="card">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>Carpeta</th>
                                <th>Invitado</th>
                                <th>Permiso</th>
                                <th>Estado</th>
                                <th style="text-align:right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sentInvites as $s): ?>
                                <tr>
                                    <td><a href="/folders.php?id=<?= (int) $s['folder_id'] ?>"><strong><?= h($s['folder_name']) ?></strong></a></td>
                                    <td><?= h($s['invited_username']) ?> <span style="color:var(--muted);font-size:0.82rem;">(<?= h($s['invited_email']) ?>)</span></td>
                                    <td><?= $s['permission'] === 'write' ? 'Puede modificar' : 'Solo lectura' ?></td>
                                    <td>
                                        <?php if ($s['status'] === 'accepted'): ?>
                                            <span class="pill pill-green">Aceptada</span>
                                        <?php elseif ($s['status'] === 'pending'): ?>
                                            <span class="pill pill-warn">Pendiente</span>
                                        <?php else: ?>
                                            <span class="pill pill-red">Rechazada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <form method="POST" action="/action.php" onsubmit="return confirm('¿Revocar o cancelar esta invitación?');">
                                                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                                <input type="hidden" name="action" value="revoke_share">
                                                <input type="hidden" name="return_to" value="/inbox.php">
                                                <input type="hidden" name="share_id" value="<?= (int) $s['id'] ?>">
                                                <button type="submit" class="btn btn-danger" title="Revocar">
                                                    <i class="fa-solid fa-user-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($adminUser && !empty($recentResets)): ?>
                <h2 class="section-title" style="margin-top:8px;">Historial de solicitudes de contraseña</h2>
                <div class="card">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th class="hide-mobile">Correo</th>
                                <th>Estado</th>
                                <th class="hide-mobile">Atendido por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentResets as $rr): ?>
                                <tr>
                                    <td><strong><?= h($rr['username']) ?></strong></td>
                                    <td class="hide-mobile"><?= h($rr['email']) ?></td>
                                    <td>
                                        <?php if ($rr['status'] === 'approved'): ?>
                                            <span class="pill pill-blue">Permiso activo</span>
                                        <?php elseif ($rr['status'] === 'used'): ?>
                                            <span class="pill pill-green">Contraseña cambiada</span>
                                        <?php else: ?>
                                            <span class="pill pill-red">Rechazada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hide-mobile"><?= h((string) ($rr['reviewer_username'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
