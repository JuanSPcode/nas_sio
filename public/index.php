<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';

$user = current_user();
if ($user === null) {
    require_once __DIR__ . '/../app/landing.php';
    render_landing_page();
    exit;
}

require_once __DIR__ . '/../app/sidebar.php';
require_once __DIR__ . '/../app/modals.php';
$search = trim((string) ($_GET['q'] ?? ''));
$files = list_user_files($user['id'], $search);
$pendingCount = count_pending_invites($user['id']);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$token = csrf_token();
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
        <?php render_sidebar($user, 'drive', $token); ?>

        <main class="main">
            <div class="topbar">
                <h1>Mi unidad (<?= count($files) ?>)</h1>
                <div class="topbar-actions">
                    <form class="search-form" method="GET" action="/index.php">
                        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Buscar archivos...">
                        <button type="submit" class="btn" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <?php if ($search !== ''): ?>
                            <a href="/index.php" class="btn" title="Limpiar filtro"><i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </form>
                    <?php render_new_dropdown(); ?>
                </div>
            </div>

            <?php if ($pendingCount > 0): ?>
                <div class="alert alert-info">
                    <div class="alert-body">
                        <i class="fa-solid fa-envelope-open-text"></i>
                        <span>Tienes <strong><?= $pendingCount ?></strong> invitación(es) pendiente(s).</span>
                    </div>
                    <a href="/inbox.php" class="btn btn-primary">Ver buzón</a>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <div class="alert-body">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <span><?= h($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-folder-open"></i>
                        <p><?= $search !== '' ? 'No hay archivos que coincidan con tu búsqueda.' : 'Aún no has subido archivos. Toca "Nuevo" para subir uno o crear una carpeta.' ?></p>
                    </div>
                <?php else: ?>
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th class="hide-mobile">Ubicación</th>
                                <th class="hide-mobile">Tamaño</th>
                                <th class="hide-mobile">Subido</th>
                                <th style="text-align:right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $f): ?>
                                <?php
                                $previewType = file_preview_type($f['mime_type'], $f['original_name']);
                                $canEdit = ($f['folder_id'] === null)
                                    || ((int) $f['folder_owner_id'] === (int) $user['id'])
                                    || (($f['share_permission'] ?? '') === 'write');
                                ?>
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
                                    <td class="hide-mobile">
                                        <?php if ($f['folder_id'] !== null): ?>
                                            <a href="/folders.php?id=<?= (int) $f['folder_id'] ?>" class="pill pill-blue">
                                                <i class="fa-solid fa-folder"></i> <?= h((string) $f['folder_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="pill pill-gray">Raíz</span>
                                        <?php endif; ?>
                                    </td>
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
                                            <?php if ($canEdit): ?>
                                                <button type="button"
                                                    class="btn btn-icon edit-trigger"
                                                    title="Actualizar / Renombrar"
                                                    data-id="<?= (int) $f['id'] ?>"
                                                    data-name="<?= h($f['original_name']) ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <form method="POST" action="/action.php" onsubmit="return confirm('¿Eliminar permanentemente este archivo?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="return_to" value="/index.php">
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
        </main>
    </div>

    <?php render_file_modals($token, '/index.php', true, null, 'Mi unidad', true, null); ?>
</body>
</html>
