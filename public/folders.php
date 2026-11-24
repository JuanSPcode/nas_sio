<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';
require_once __DIR__ . '/../app/sidebar.php';
require_once __DIR__ . '/../app/modals.php';

$user = require_login();
$token = csrf_token();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$folderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$folder = null;
$breadcrumbs = [];
$childFolders = [];
$folderFiles = [];
$collaborators = [];
$search = trim((string) ($_GET['q'] ?? ''));

if ($folderId > 0) {
    $folder = get_folder_for_user($user['id'], $folderId);
    if ($folder === null) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Carpeta no encontrada o sin permisos de acceso.'];
        header('Location: /folders.php');
        exit;
    }
    $breadcrumbs = get_visible_breadcrumbs($user['id'], $folderId);
    $childFolders = list_child_folders($user['id'], $folderId);
    if ($search !== '') {
        $childFolders = array_values(array_filter(
            $childFolders,
            static fn(array $c): bool => stripos($c['name'], $search) !== false
        ));
    }
    $folderFiles = list_folder_files($user['id'], $folderId, $search);
    if ($folder['access'] === 'owner') {
        $collaborators = list_folder_collaborators($user['id'], $folderId);
    }
} else {
    $ownedFolders = list_owned_folders($user['id']);
    $sharedFolders = list_shared_with_me_folders($user['id']);
}

$pendingCount = count_pending_invites($user['id']);
$canWrite = $folder !== null && in_array($folder['access'], ['owner', 'write'], true);
$returnTo = $folder !== null ? ('/folders.php?id=' . (int) $folder['id']) : '/folders.php';
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
        <?php render_sidebar($user, 'folders', $token); ?>

        <main class="main">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <div class="alert-body">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <span><?= h($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($folder === null): ?>
                <div class="topbar topbar-row">
                    <h1><i class="fa-solid fa-folder-open"></i> Carpetas</h1>
                    <button type="button" class="btn btn-primary create-folder-trigger-btn">
                        <i class="fa-solid fa-folder-plus"></i> Nueva carpeta
                    </button>
                </div>

                <?php if ($pendingCount > 0): ?>
                    <div class="alert alert-info">
                        <div class="alert-body">
                            <i class="fa-solid fa-envelope-open-text"></i>
                            <span>Tienes <strong><?= $pendingCount ?></strong> invitación(es) pendiente(s) en tu buzón.</span>
                        </div>
                        <a href="/inbox.php" class="btn btn-primary">Revisar invitaciones</a>
                    </div>
                <?php endif; ?>

                <h2 class="section-title"><i class="fa-solid fa-folder"></i> Mis carpetas (<?= count($ownedFolders) ?>)</h2>
                <?php if (empty($ownedFolders)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <i class="fa-regular fa-folder"></i>
                            <p>Aún no has creado carpetas. Haz clic en <strong>Nueva carpeta</strong> para crear una.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="folder-grid">
                        <?php foreach ($ownedFolders as $fo): ?>
                            <div class="folder-card">
                                <div class="folder-card-top">
                                    <div class="folder-icon-wrap"><i class="fa-solid fa-folder"></i></div>
                                    <?php if ((int) $fo['shared_count'] > 0): ?>
                                        <span class="pill pill-green"><i class="fa-solid fa-users"></i> <?= (int) $fo['shared_count'] ?> colab.</span>
                                    <?php elseif ((int) $fo['pending_count'] > 0): ?>
                                        <span class="pill pill-warn"><i class="fa-regular fa-clock"></i> <?= (int) $fo['pending_count'] ?> pend.</span>
                                    <?php else: ?>
                                        <span class="pill pill-gray"><i class="fa-solid fa-lock"></i> Privada</span>
                                    <?php endif; ?>
                                </div>
                                <div class="folder-title"><?= h($fo['name']) ?></div>
                                <div class="folder-meta">
                                    <?php if ((int) $fo['subfolder_count'] > 0): ?>
                                        <span><i class="fa-regular fa-folder"></i> <?= (int) $fo['subfolder_count'] ?> subcarp.</span>
                                    <?php endif; ?>
                                    <span><i class="fa-regular fa-file"></i> <?= (int) $fo['file_count'] ?> archivos</span>
                                    <span><i class="fa-solid fa-database"></i> <?= h(format_bytes((int) $fo['total_bytes'])) ?></span>
                                </div>
                                <div class="folder-footer">
                                    <a href="/folders.php?id=<?= (int) $fo['id'] ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-folder-open"></i> Abrir
                                    </a>
                                    <form method="POST" action="/action.php" onsubmit="return confirm('¿Eliminar esta carpeta y todo su contenido permanentemente?');">
                                        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="action" value="delete_folder">
                                        <input type="hidden" name="return_to" value="/folders.php">
                                        <input type="hidden" name="folder_id" value="<?= (int) $fo['id'] ?>">
                                        <button type="submit" class="btn btn-danger" title="Eliminar carpeta">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2 class="section-title" style="margin-top:8px;"><i class="fa-solid fa-share-nodes"></i> Compartidas conmigo (<?= count($sharedFolders) ?>)</h2>
                <?php if (empty($sharedFolders)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <i class="fa-solid fa-users-slash"></i>
                            <p>Todavía no has aceptado carpetas compartidas. Cuando alguien te invite por correo, aparecerá en tu <strong>Buzón</strong>.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="folder-grid">
                        <?php foreach ($sharedFolders as $sf): ?>
                            <div class="folder-card">
                                <div class="folder-card-top">
                                    <div class="folder-icon-wrap shared"><i class="fa-solid fa-folder-open"></i></div>
                                    <?php if ($sf['permission'] === 'write'): ?>
                                        <span class="pill pill-green"><i class="fa-solid fa-pen"></i> Puede modificar</span>
                                    <?php else: ?>
                                        <span class="pill pill-blue"><i class="fa-regular fa-eye"></i> Solo lectura</span>
                                    <?php endif; ?>
                                </div>
                                <div class="folder-title"><?= h($sf['name']) ?></div>
                                <div class="folder-meta">
                                    <span><i class="fa-regular fa-user"></i> De: <strong><?= h($sf['owner_username']) ?></strong></span>
                                    <?php if ((int) $sf['subfolder_count'] > 0): ?>
                                        <span><i class="fa-regular fa-folder"></i> <?= (int) $sf['subfolder_count'] ?> subcarp.</span>
                                    <?php endif; ?>
                                    <span><i class="fa-regular fa-file"></i> <?= (int) $sf['file_count'] ?> archivos</span>
                                </div>
                                <div class="folder-footer">
                                    <a href="/folders.php?id=<?= (int) $sf['id'] ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-folder-open"></i> Abrir
                                    </a>
                                    <form method="POST" action="/action.php" onsubmit="return confirm('¿Salir de esta carpeta compartida?');">
                                        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="action" value="leave_folder">
                                        <input type="hidden" name="folder_id" value="<?= (int) $sf['id'] ?>">
                                        <button type="submit" class="btn btn-danger" title="Salir de la carpeta">
                                            <i class="fa-solid fa-right-from-bracket"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <nav class="breadcrumb" aria-label="Ruta de carpetas" style="flex-wrap:wrap;">
                    <a href="/folders.php"><i class="fa-solid fa-folder-open"></i> Carpetas</a>
                    <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                        <span>/</span>
                        <?php if ($idx < count($breadcrumbs) - 1): ?>
                            <a href="/folders.php?id=<?= (int) $crumb['id'] ?>"><?= h($crumb['name']) ?></a>
                        <?php else: ?>
                            <strong style="color:var(--text);"><?= h($crumb['name']) ?></strong>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <div class="topbar">
                    <div class="folder-heading">
                        <h1>
                            <i class="fa-solid fa-folder-open"></i>
                            <?= h($folder['name']) ?>
                            <?php if ($folder['access'] === 'owner'): ?>
                                <span class="pill pill-blue">Propietario</span>
                            <?php elseif ($folder['access'] === 'write'): ?>
                                <span class="pill pill-green"><i class="fa-solid fa-pen"></i> Puedes modificar</span>
                            <?php else: ?>
                                <span class="pill pill-gray"><i class="fa-regular fa-eye"></i> Solo lectura</span>
                            <?php endif; ?>
                        </h1>
                        <?php if ($folder['access'] !== 'owner'): ?>
                            <p style="font-size:0.82rem;color:var(--muted);margin-top:2px;">
                                Propietario: <strong><?= h($folder['owner_username']) ?></strong> (<?= h($folder['owner_email']) ?>)
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="topbar-actions">
                        <form class="search-form" method="GET" action="/folders.php">
                            <input type="hidden" name="id" value="<?= (int) $folder['id'] ?>">
                            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Buscar...">
                            <button type="submit" class="btn" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
                            <?php if ($search !== ''): ?>
                                <a href="/folders.php?id=<?= (int) $folder['id'] ?>" class="btn"><i class="fa-solid fa-xmark"></i></a>
                            <?php endif; ?>
                        </form>
                        <?php if ($canWrite): ?>
                            <?php render_new_dropdown(); ?>
                        <?php endif; ?>
                        <?php if ($folder['access'] === 'owner'): ?>
                            <button type="button" class="btn" id="openInviteBtn" title="Invitar colaboradores">
                                <i class="fa-solid fa-user-plus"></i>
                                <span class="hide-xs">Invitar</span><?= count($collaborators) > 0 ? ' (' . count($collaborators) . ')' : '' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($childFolders)): ?>
                    <h2 class="section-title"><i class="fa-solid fa-folder"></i> Subcarpetas (<?= count($childFolders) ?>)</h2>
                    <div class="folder-grid">
                        <?php foreach ($childFolders as $sub): ?>
                            <div class="folder-card">
                                <div class="folder-card-top">
                                    <div class="folder-icon-wrap"><i class="fa-solid fa-folder"></i></div>
                                    <?php if ((int) $sub['shared_count'] > 0): ?>
                                        <span class="pill pill-green"><i class="fa-solid fa-users"></i> <?= (int) $sub['shared_count'] ?> colab.</span>
                                    <?php endif; ?>
                                </div>
                                <div class="folder-title"><?= h($sub['name']) ?></div>
                                <div class="folder-meta">
                                    <?php if ((int) $sub['subfolder_count'] > 0): ?>
                                        <span><i class="fa-regular fa-folder"></i> <?= (int) $sub['subfolder_count'] ?> subcarp.</span>
                                    <?php endif; ?>
                                    <span><i class="fa-regular fa-file"></i> <?= (int) $sub['file_count'] ?> archivos</span>
                                    <span><i class="fa-solid fa-database"></i> <?= h(format_bytes((int) $sub['total_bytes'])) ?></span>
                                </div>
                                <div class="folder-footer">
                                    <a href="/folders.php?id=<?= (int) $sub['id'] ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-folder-open"></i> Abrir
                                    </a>
                                    <?php if ($canWrite): ?>
                                        <form method="POST" action="/action.php" onsubmit="return confirm('¿Eliminar esta subcarpeta y todo su contenido permanentemente?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                            <input type="hidden" name="action" value="delete_folder">
                                            <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                                            <input type="hidden" name="folder_id" value="<?= (int) $sub['id'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Eliminar subcarpeta">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($folderFiles)): ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <p><?= $canWrite ? 'No hay archivos en este nivel. Usa "Nuevo" para subir un archivo o crear una subcarpeta.' : 'Esta carpeta aún no tiene archivos.' ?></p>
                        </div>
                    <?php else: ?>
                        <table class="file-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th class="hide-mobile">Subido por</th>
                                    <th class="hide-mobile">Tamaño</th>
                                    <th class="hide-mobile">Actualizado</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($folderFiles as $f): ?>
                                    <?php $previewType = file_preview_type($f['mime_type'], $f['original_name']); ?>
                                    <tr>
                                        <td>
                                            <div class="file-name">
                                                <?php if ($previewType !== null): ?>
                                                    <button type="button"
                                                        class="file-link-btn preview-trigger"
                                                        data-id="<?= (int) $f['id'] ?>"
                                                        data-name="<?= h($f['original_name']) ?>"
                                                        data-type="<?= h($previewType) ?>">
                                                        <i class="<?= h(file_icon_class($f['mime_type'], $f['original_name'])) ?>"></i>
                                                        <span><?= h($f['original_name']) ?></span>
                                                    </button>
                                                <?php else: ?>
                                                    <i class="<?= h(file_icon_class($f['mime_type'], $f['original_name'])) ?>"></i>
                                                    <span><?= h($f['original_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="hide-mobile"><?= h($f['uploader_username']) ?></td>
                                        <td class="hide-mobile"><?= h(format_bytes((int) $f['size_bytes'])) ?></td>
                                        <td class="hide-mobile"><?= h($f['uploaded_at']) ?></td>
                                        <td>
                                            <div class="actions">
                                                <?php if ($previewType !== null): ?>
                                                    <button type="button"
                                                        class="btn btn-icon preview-trigger"
                                                        title="Vista previa"
                                                        data-id="<?= (int) $f['id'] ?>"
                                                        data-name="<?= h($f['original_name']) ?>"
                                                        data-type="<?= h($previewType) ?>">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a class="btn btn-icon" href="/action.php?action=download&id=<?= (int) $f['id'] ?>" title="Descargar">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                                <?php if ($canWrite): ?>
                                                    <button type="button"
                                                        class="btn btn-icon edit-trigger"
                                                        title="Actualizar / Reemplazar"
                                                        data-id="<?= (int) $f['id'] ?>"
                                                        data-name="<?= h($f['original_name']) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <form method="POST" action="/action.php" onsubmit="return confirm('¿Eliminar permanentemente este archivo?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                                        <button type="submit" class="btn btn-danger" title="Eliminar">
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
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($folder !== null && $folder['access'] === 'owner'): ?>
        <div class="modal-backdrop" id="inviteModal" aria-hidden="true">
            <div class="modal-box modal-box-md" role="dialog" aria-modal="true" aria-labelledby="inviteModalTitle">
                <div class="modal-header">
                    <div class="modal-title" id="inviteModalTitle">
                        <i class="fa-solid fa-user-plus"></i> Compartir "<?= h($folder['name']) ?>"
                    </div>
                    <button type="button" class="btn btn-icon" id="closeInviteBtn" title="Cerrar (Esc)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-scroll-body">
                    <div class="card-pad" style="border-bottom:1px solid var(--border);">
                        <form method="POST" action="/action.php" class="invite-form">
                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                            <input type="hidden" name="action" value="invite_folder">
                            <input type="hidden" name="folder_id" value="<?= (int) $folder['id'] ?>">
                            <div class="form-group" style="margin:0;">
                                <label for="inviteEmail">Correo registrado en el NAS</label>
                                <input type="email" id="inviteEmail" name="email" placeholder="ej. sp23002@ues.edu.sv" required>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label for="invitePerm">Permisos del invitado</label>
                                <select id="invitePerm" name="permission">
                                    <option value="read">Solo ver y descargar</option>
                                    <option value="write">Modificar (subir, actualizar y eliminar)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i> Invitar
                            </button>
                        </form>
                    </div>

                    <?php if (empty($collaborators)): ?>
                        <div class="empty-state" style="padding:32px 20px;">
                            <p>Aún no has compartido esta carpeta con nadie.</p>
                        </div>
                    <?php else: ?>
                        <table class="file-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th class="hide-mobile">Correo</th>
                                    <th>Estado</th>
                                    <th>Permiso</th>
                                    <th style="text-align:right;">Quitar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($collaborators as $c): ?>
                                    <tr>
                                        <td><strong><?= h($c['username']) ?></strong></td>
                                        <td class="hide-mobile"><?= h($c['email']) ?></td>
                                        <td>
                                            <?php if ($c['status'] === 'accepted'): ?>
                                                <span class="pill pill-green">Aceptada</span>
                                            <?php elseif ($c['status'] === 'pending'): ?>
                                                <span class="pill pill-warn">Pendiente</span>
                                            <?php else: ?>
                                                <span class="pill pill-red">Rechazada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="/action.php">
                                                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                                <input type="hidden" name="action" value="update_share_permission">
                                                <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                                                <input type="hidden" name="share_id" value="<?= (int) $c['id'] ?>">
                                                <select name="permission" onchange="this.form.submit()" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:0.84rem;background:var(--surface);">
                                                    <option value="read" <?= $c['permission'] === 'read' ? 'selected' : '' ?>>Solo lectura</option>
                                                    <option value="write" <?= $c['permission'] === 'write' ? 'selected' : '' ?>>Modificar</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <form method="POST" action="/action.php" onsubmit="return confirm('¿Revocar acceso a este usuario?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                                    <input type="hidden" name="action" value="revoke_share">
                                                    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                                                    <input type="hidden" name="share_id" value="<?= (int) $c['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" title="Revocar acceso">
                                                        <i class="fa-solid fa-user-xmark"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (() => {
            const modal = document.getElementById('inviteModal');
            const openBtn = document.getElementById('openInviteBtn');
            const closeBtn = document.getElementById('closeInviteBtn');
            const emailInput = document.getElementById('inviteEmail');

            function closeModal() {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }
            openBtn.addEventListener('click', () => {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                emailInput.focus();
            });
            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
            });
        })();
        </script>
    <?php endif; ?>

    <?php
    render_file_modals(
        $token,
        $returnTo,
        $canWrite,
        $folder !== null ? (int) $folder['id'] : null,
        $folder !== null ? $folder['name'] : 'Carpetas',
        $folder === null || $canWrite,
        $folder !== null ? (int) $folder['id'] : null
    );
    ?>
</body>
</html>
