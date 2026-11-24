<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';
require_once __DIR__ . '/../app/sidebar.php';

$admin = require_admin();
$token = csrf_token();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Solicitud rechazada: token de seguridad inválido.');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'create_user') {
        $quota = parse_quota_bytes((string) ($_POST['quota_amount'] ?? '1'), (string) ($_POST['quota_unit'] ?? 'GB'));
        if ($quota === null) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ingresa un límite de espacio válido (mayor a 0).'];
            header('Location: /admin.php');
            exit;
        }
        $res = register_user(
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['role'] ?? 'user'),
            $quota
        );
        $_SESSION['flash'] = $res['ok']
            ? ['type' => 'success', 'message' => 'Usuario creado con su carpeta aislada correctamente.']
            : ['type' => 'error', 'message' => $res['error']];
        header('Location: /admin.php');
        exit;
    }

    if ($action === 'manage_user') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $quota = parse_quota_bytes((string) ($_POST['quota_amount'] ?? ''), (string) ($_POST['quota_unit'] ?? 'GB'));
        if ($quota === null) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ingresa un límite de espacio válido (mayor a 0).'];
            header('Location: /admin.php');
            exit;
        }
        $res = update_user_account_admin($targetId, $quota, (string) ($_POST['new_password'] ?? ''));
        $_SESSION['flash'] = ['type' => $res['ok'] ? 'success' : 'error', 'message' => $res['ok'] ? $res['message'] : $res['error']];
        header('Location: /admin.php');
        exit;
    }

    if ($action === 'toggle_role') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $newRole = isset($_POST['is_admin']) ? 'admin' : 'user';
        $res = set_user_role($targetId, $newRole, $admin['id']);
        $_SESSION['flash'] = $res['ok']
            ? ['type' => 'success', 'message' => $res['role'] === 'admin'
                ? 'Ahora "' . $res['username'] . '" tiene permisos de administrador.'
                : 'Se quitaron los permisos de administrador a "' . $res['username'] . '".']
            : ['type' => 'error', 'message' => $res['error']];
        header('Location: /admin.php');
        exit;
    }

    if ($action === 'delete_user') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $res = delete_user_account($targetId, $admin['id']);
        $_SESSION['flash'] = $res['ok']
            ? ['type' => 'success', 'message' => 'Usuario "' . $res['username'] . '" y sus archivos eliminados.']
            : ['type' => 'error', 'message' => $res['error']];
        header('Location: /admin.php');
        exit;
    }
}

$users = list_all_users();
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
        <?php render_sidebar($admin, 'admin', $token); ?>

        <main class="main">
            <div class="topbar topbar-row">
                <h1><i class="fa-solid fa-users-gear"></i> Usuarios del NAS (<?= count($users) ?>)</h1>
                <button type="button" class="btn btn-primary" id="openCreateUserBtn">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Nuevo<span class="hide-xs"> usuario</span></span>
                </button>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <div class="alert-body">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <span><?= h($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th class="hide-mobile">Correo</th>
                            <th>Admin</th>
                            <th class="hide-mobile">Uso / Límite</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $isSelf = ((int) $u['id'] === (int) $admin['id']);
                            $uIsAdmin = (($u['role'] ?? 'user') === 'admin');
                            $qBytes = (int) ($u['storage_quota_bytes'] ?? DEFAULT_QUOTA_BYTES);
                            if ($qBytes >= 1073741824 && ($qBytes % 1073741824) === 0) {
                                $qAmount = (string) ($qBytes / 1073741824);
                                $qUnit = 'GB';
                            } elseif ($qBytes >= 1073741824) {
                                $qAmount = rtrim(rtrim(number_format($qBytes / 1073741824, 2, '.', ''), '0'), '.');
                                $qUnit = 'GB';
                            } else {
                                $qAmount = rtrim(rtrim(number_format($qBytes / 1048576, 2, '.', ''), '0'), '.');
                                $qUnit = 'MB';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="file-name">
                                        <i class="fa-solid <?= $uIsAdmin ? 'fa-user-shield' : 'fa-user' ?>"></i>
                                        <span><?= h($u['username']) ?></span>
                                    </div>
                                </td>
                                <td class="hide-mobile"><?= h($u['email']) ?></td>
                                <td>
                                    <?php if ($isSelf): ?>
                                        <div class="role-toggle" title="No puedes quitar tu propio rol de administrador">
                                            <label class="switch">
                                                <input type="checkbox" checked disabled>
                                                <span class="slider"></span>
                                            </label>
                                            <span>Admin</span>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" action="/admin.php" class="role-toggle">
                                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                            <input type="hidden" name="action" value="toggle_role">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <label class="switch" title="<?= $uIsAdmin ? 'Quitar permisos de administrador' : 'Hacer administrador' ?>">
                                                <input type="checkbox" name="is_admin" value="1" <?= $uIsAdmin ? 'checked' : '' ?> onchange="this.form.submit()">
                                                <span class="slider"></span>
                                            </label>
                                            <span<?= $uIsAdmin ? '' : ' style="color:var(--muted);"' ?>>Admin</span>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile">
                                    <strong><?= h(format_bytes((int) $u['used_bytes'])) ?></strong> / <?= h(format_bytes($qBytes)) ?>
                                    <span style="color:var(--muted);font-size:0.8rem;">(<?= (int) $u['file_count'] ?> arch.)</span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button type="button"
                                            class="btn btn-icon manage-user-trigger"
                                            title="Gestionar cuota y contraseña"
                                            data-id="<?= (int) $u['id'] ?>"
                                            data-username="<?= h($u['username']) ?>"
                                            data-amount="<?= h($qAmount) ?>"
                                            data-unit="<?= h($qUnit) ?>">
                                            <i class="fa-solid fa-sliders"></i>
                                        </button>
                                        <?php if (!$isSelf): ?>
                                            <form method="POST" action="/admin.php" onsubmit="return confirm('¿Eliminar este usuario y todos sus archivos permanentemente?');">
                                                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="btn btn-danger" title="Eliminar usuario">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal-backdrop" id="createUserModal" aria-hidden="true">
        <div class="modal-box modal-box-sm" role="dialog" aria-modal="true" aria-labelledby="createUserTitle">
            <div class="modal-header">
                <div class="modal-title" id="createUserTitle"><i class="fa-solid fa-user-plus"></i> Crear nuevo usuario</div>
                <button type="button" class="btn btn-icon" id="closeCreateUserBtn" title="Cerrar (Esc)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="/admin.php" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="create_user">

                <div class="form-group" style="margin:0;">
                    <label for="newUsername">Usuario</label>
                    <input type="text" id="newUsername" name="username" minlength="3" maxlength="30" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="newEmail">Correo</label>
                    <input type="email" id="newEmail" name="email" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="newPassword">Contraseña (mín. 8 caracteres)</label>
                    <input type="password" id="newPassword" name="password" minlength="8" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="createQuotaAmount">Límite de espacio (por defecto 1 GB)</label>
                    <div style="display:grid;grid-template-columns:1fr 90px;gap:8px;">
                        <input type="number" id="createQuotaAmount" name="quota_amount" step="any" min="0.1" max="10240" value="1" required>
                        <select name="quota_unit">
                            <option value="GB" selected>GB</option>
                            <option value="MB">MB</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="newRole">Rol</label>
                    <select id="newRole" name="role">
                        <option value="user">Usuario estándar</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

                <div class="modal-footer-actions">
                    <button type="button" class="btn" id="cancelCreateUserBtn">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> Crear cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="manageUserModal" aria-hidden="true">
        <div class="modal-box modal-box-sm" role="dialog" aria-modal="true" aria-labelledby="manageUserTitle">
            <div class="modal-header">
                <div class="modal-title" id="manageUserTitle"><i class="fa-solid fa-sliders"></i> Gestionar usuario</div>
                <button type="button" class="btn btn-icon" id="closeManageUserBtn" title="Cerrar (Esc)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="/admin.php" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="manage_user">
                <input type="hidden" name="id" id="manageUserId" value="">

                <div class="form-group" style="margin:0;">
                    <label for="manageQuotaAmount">Límite de almacenamiento</label>
                    <div style="display:grid;grid-template-columns:1fr 90px;gap:8px;">
                        <input type="number" id="manageQuotaAmount" name="quota_amount" step="any" min="0.1" max="10240" required>
                        <select id="manageQuotaUnit" name="quota_unit">
                            <option value="GB">GB</option>
                            <option value="MB">MB</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin:0;">
                    <label for="manageNewPwd">Nueva contraseña (opcional)</label>
                    <input type="password" id="manageNewPwd" name="new_password" minlength="8" placeholder="Dejar vacío para mantener la actual">
                </div>

                <div class="modal-footer-actions">
                    <button type="button" class="btn" id="cancelManageUserBtn">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (() => {
        const cModal = document.getElementById('createUserModal');
        const openCBtn = document.getElementById('openCreateUserBtn');
        const closeCBtn = document.getElementById('closeCreateUserBtn');
        const cancelCBtn = document.getElementById('cancelCreateUserBtn');
        const userInput = document.getElementById('newUsername');

        function closeCreateModal() {
            cModal.classList.remove('open');
            cModal.setAttribute('aria-hidden', 'true');
        }
        openCBtn.addEventListener('click', () => {
            cModal.classList.add('open');
            cModal.setAttribute('aria-hidden', 'false');
            userInput.focus();
        });
        closeCBtn.addEventListener('click', closeCreateModal);
        cancelCBtn.addEventListener('click', closeCreateModal);
        cModal.addEventListener('click', (e) => { if (e.target === cModal) closeCreateModal(); });

        const mModal = document.getElementById('manageUserModal');
        const mTitle = document.getElementById('manageUserTitle');
        const mId = document.getElementById('manageUserId');
        const mAmount = document.getElementById('manageQuotaAmount');
        const mUnit = document.getElementById('manageQuotaUnit');
        const mPwd = document.getElementById('manageNewPwd');
        const closeMBtn = document.getElementById('closeManageUserBtn');
        const cancelMBtn = document.getElementById('cancelManageUserBtn');

        function closeManageModal() {
            mModal.classList.remove('open');
            mModal.setAttribute('aria-hidden', 'true');
            mPwd.value = '';
        }

        document.querySelectorAll('.manage-user-trigger').forEach((btn) => {
            btn.addEventListener('click', () => {
                mTitle.innerHTML = '<i class="fa-solid fa-sliders"></i> Gestionar a ' + btn.dataset.username;
                mId.value = btn.dataset.id;
                mAmount.value = btn.dataset.amount;
                mUnit.value = btn.dataset.unit;
                mPwd.value = '';
                mModal.classList.add('open');
                mModal.setAttribute('aria-hidden', 'false');
                mAmount.focus();
            });
        });

        closeMBtn.addEventListener('click', closeManageModal);
        cancelMBtn.addEventListener('click', closeManageModal);
        mModal.addEventListener('click', (e) => { if (e.target === mModal) closeManageModal(); });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (cModal.classList.contains('open')) closeCreateModal();
                if (mModal.classList.contains('open')) closeManageModal();
            }
        });
    })();
    </script>
</body>
</html>
