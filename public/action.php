<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';

$user = require_login();
$action = $_REQUEST['action'] ?? '';

function safe_return_url(?string $raw, string $default = '/index.php'): string {
    if (!is_string($raw) || $raw === '') {
        return $default;
    }
    if (preg_match('#^/(index\.php|folders\.php|inbox\.php|admin\.php)(\?id=\d+)?$#', $raw)) {
        return $raw;
    }
    return $default;
}

function redirect_with_flash(string $type, string $message, string $to = '/index.php'): never {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $to);
    exit;
}

if ($action === 'preview' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $fileId = (int) ($_GET['id'] ?? 0);
    $file = get_accessible_file($user['id'], $fileId);
    if ($file === null) {
        http_response_code(404);
        exit('Archivo no encontrado o sin permisos de acceso.');
    }

    $previewType = file_preview_type($file['mime_type'], $file['original_name']);
    if ($previewType === null) {
        http_response_code(415);
        exit('Este tipo de archivo no admite vista previa.');
    }

    $safeName = str_replace(['"', "\r", "\n", '/', '\\'], '_', $file['original_name']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if ($previewType === 'text') {
        $maxBytes = 512 * 1024;
        $size = (int) filesize($file['full_path']);
        $fp = fopen($file['full_path'], 'rb');
        $content = ($fp !== false) ? (string) fread($fp, $maxBytes) : '';
        if ($fp !== false) {
            fclose($fp);
        }
        if ($size > $maxBytes) {
            $content .= "\n\n--- [Vista previa limitada a 512 KB. Descarga el archivo para verlo completo] ---";
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    $contentType = ($previewType === 'pdf') ? 'application/pdf' : $file['mime_type'];
    $size = (int) filesize($file['full_path']);
    $start = 0;
    $end = max(0, $size - 1);
    $status = 200;

    if ($size > 0 && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', (string) $_SERVER['HTTP_RANGE'], $m)) {
        $rStart = $m[1] !== '' ? (int) $m[1] : null;
        $rEnd = $m[2] !== '' ? (int) $m[2] : null;
        if ($rStart === null && $rEnd !== null) {
            $start = max(0, $size - $rEnd);
            $end = $size - 1;
        } else {
            $start = $rStart ?? 0;
            $end = $rEnd !== null ? min($rEnd, $size - 1) : ($size - 1);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $status = 206;
    }

    $length = ($size > 0) ? ($end - $start + 1) : 0;
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    header('Content-Disposition: inline; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
    if ($status === 206) {
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    }

    if ($length > 0) {
        $fp = fopen($file['full_path'], 'rb');
        if ($fp !== false) {
            fseek($fp, $start);
            $remaining = $length;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, min(65536, $remaining));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }
            fclose($fp);
        }
    }
    exit;
}

if ($action === 'download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $fileId = (int) ($_GET['id'] ?? 0);
    $file = get_accessible_file($user['id'], $fileId);
    if ($file === null) {
        http_response_code(404);
        exit('Archivo no encontrado o sin permisos de acceso.');
    }

    $safeName = str_replace(['"', "\r", "\n", '/', '\\'], '_', $file['original_name']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $file['mime_type']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($file['full_path']));
    header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
    readfile($file['full_path']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud rechazada: token de seguridad inválido.');
}

$returnTo = safe_return_url($_POST['return_to'] ?? null);

if ($action === 'change_password') {
    $res = change_own_password(
        (int) $user['id'],
        (string) ($_POST['current_password'] ?? ''),
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );
    redirect_with_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error'], $returnTo);
}

if ($action === 'review_password_reset') {
    require_admin();
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $res = review_password_reset((int) $user['id'], $requestId, $decision);
    redirect_with_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error'], '/inbox.php');
}

if ($action === 'upload') {
    $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int) $_POST['folder_id'] : null;
    $res = upload_user_file($user['id'], $_FILES['file'] ?? [], $folderId);
    if ($res['ok']) {
        redirect_with_flash('success', 'Archivo "' . $res['name'] . '" subido correctamente.', $returnTo);
    }
    redirect_with_flash('error', $res['error'], $returnTo);
}

if ($action === 'update_file') {
    $fileId = (int) ($_POST['id'] ?? 0);
    $res = update_user_file($user['id'], $fileId, (string) ($_POST['new_name'] ?? ''), $_FILES['file'] ?? []);
    redirect_with_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error'], $returnTo);
}

if ($action === 'delete') {
    $fileId = (int) ($_POST['id'] ?? 0);
    if (delete_user_file($user['id'], $fileId)) {
        redirect_with_flash('success', 'Archivo eliminado correctamente.', $returnTo);
    }
    redirect_with_flash('error', 'No tienes permiso para eliminar este archivo.', $returnTo);
}

if ($action === 'create_folder') {
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
    $dest = ($parentId !== null && $parentId > 0) ? ('/folders.php?id=' . $parentId) : '/folders.php';
    $res = create_folder($user['id'], (string) ($_POST['name'] ?? ''), $parentId);
    if ($res['ok']) {
        redirect_with_flash('success', 'Carpeta "' . $res['name'] . '" creada.', $dest);
    }
    redirect_with_flash('error', $res['error'], $dest);
}

if ($action === 'delete_folder') {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $dest = safe_return_url($_POST['return_to'] ?? null, '/folders.php');
    if (delete_folder($user['id'], $folderId)) {
        redirect_with_flash('success', 'Carpeta y su contenido eliminados.', $dest);
    }
    redirect_with_flash('error', 'No se pudo eliminar la carpeta.', $dest);
}

if ($action === 'invite_folder') {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $dest = '/folders.php?id=' . $folderId;
    $res = invite_user_to_folder($user['id'], $folderId, (string) ($_POST['email'] ?? ''), (string) ($_POST['permission'] ?? 'read'));
    redirect_with_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error'], $dest);
}

if ($action === 'update_share_permission') {
    $shareId = (int) ($_POST['share_id'] ?? 0);
    $perm = (string) ($_POST['permission'] ?? 'read');
    $ok = update_share_permission($user['id'], $shareId, $perm);
    redirect_with_flash($ok ? 'success' : 'error', $ok ? 'Permiso del colaborador actualizado.' : 'No se pudo actualizar el permiso.', $returnTo);
}

if ($action === 'revoke_share') {
    $shareId = (int) ($_POST['share_id'] ?? 0);
    $ok = revoke_folder_share($user['id'], $shareId);
    redirect_with_flash($ok ? 'success' : 'error', $ok ? 'Acceso revocado correctamente.' : 'No se pudo revocar el acceso.', $returnTo);
}

if ($action === 'respond_invite') {
    $shareId = (int) ($_POST['share_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $res = respond_to_invite($user['id'], $shareId, $decision);
    if ($res['ok']) {
        $msg = $res['status'] === 'accepted'
            ? 'Invitación aceptada. Ya puedes abrir "' . $res['folder_name'] . '" en Carpetas.'
            : 'Invitación a "' . $res['folder_name'] . '" rechazada.';
        redirect_with_flash('success', $msg, '/inbox.php');
    }
    redirect_with_flash('error', $res['error'], '/inbox.php');
}

if ($action === 'leave_folder') {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $ok = leave_shared_folder($user['id'], $folderId);
    redirect_with_flash($ok ? 'success' : 'error', $ok ? 'Has salido de la carpeta compartida.' : 'No se pudo salir de la carpeta.', '/folders.php');
}

if ($action === 'logout') {
    logout_user();
    header('Location: /login.php');
    exit;
}

http_response_code(400);
exit('Acción no reconocida.');
