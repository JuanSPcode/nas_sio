<?php
declare(strict_types=1);

require_once __DIR__ . '/folders.php';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB', 'MB', 'GB', 'TB'];
    $size = $bytes / 1024;
    foreach ($units as $unit) {
        if ($size < 1024 || $unit === 'TB') {
            return number_format($size, 2) . ' ' . $unit;
        }
        $size /= 1024;
    }
    return number_format($size, 2) . ' TB';
}

function file_icon_class(string $mime, string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (str_starts_with($mime, 'image/')) return 'fa-regular fa-file-image';
    if ($mime === 'application/pdf' || $ext === 'pdf') return 'fa-regular fa-file-pdf';
    if (str_starts_with($mime, 'video/')) return 'fa-regular fa-file-video';
    if (str_starts_with($mime, 'audio/')) return 'fa-regular fa-file-audio';
    if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true)) return 'fa-regular fa-file-zipper';
    if (in_array($ext, ['doc', 'docx', 'odt'], true)) return 'fa-regular fa-file-word';
    if (in_array($ext, ['xls', 'xlsx', 'csv'], true)) return 'fa-regular fa-file-excel';
    if (in_array($ext, ['php', 'js', 'ts', 'html', 'css', 'py', 'java', 'json', 'sql', 'sh'], true)) return 'fa-regular fa-file-code';
    if (str_starts_with($mime, 'text/')) return 'fa-regular fa-file-lines';
    return 'fa-regular fa-file';
}

function file_preview_type(string $mime, string $name): ?string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'], true)) {
        return 'image';
    }
    if ($mime === 'application/pdf' || $ext === 'pdf') {
        return 'pdf';
    }
    if (in_array($mime, ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'], true)) {
        return 'video';
    }
    if (in_array($mime, ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/webm', 'audio/aac'], true)) {
        return 'audio';
    }

    $textExts = ['txt', 'md', 'csv', 'json', 'xml', 'log', 'ini', 'conf', 'yaml', 'yml', 'php', 'js', 'ts', 'css', 'html', 'htm', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'sh', 'svg'];
    if (str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/xml', 'application/javascript'], true) || in_array($ext, $textExts, true)) {
        return 'text';
    }

    return null;
}

function list_user_files(int $userId, string $search = ''): array {
    $pdo = get_db();
    $search = trim($search);
    $sql = "
        SELECT f.id, f.user_id, f.folder_id, f.original_name, f.mime_type, f.size_bytes, f.uploaded_at,
               fo.name AS folder_name, fo.user_id AS folder_owner_id, fs.permission AS share_permission
        FROM files f
        LEFT JOIN folders fo ON fo.id = f.folder_id
        LEFT JOIN folder_shares fs ON fs.folder_id = f.folder_id
              AND fs.invited_user_id = ?
              AND fs.status = 'accepted'
        WHERE f.user_id = ?
          AND (f.folder_id IS NULL OR fo.user_id = ? OR fs.id IS NOT NULL)
    ";
    $params = [$userId, $userId, $userId];
    if ($search !== '') {
        $sql .= ' AND f.original_name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY f.uploaded_at DESC, f.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function list_folder_files(int $userId, int $folderId, string $search = ''): array {
    if (get_folder_access($userId, $folderId) === null) {
        return [];
    }
    $pdo = get_db();
    $search = trim($search);
    $sql = '
        SELECT f.id, f.user_id, f.folder_id, f.original_name, f.mime_type, f.size_bytes, f.uploaded_at,
               u.username AS uploader_username
        FROM files f
        INNER JOIN users u ON u.id = f.user_id
        WHERE f.folder_id = ?
    ';
    $params = [$folderId];
    if ($search !== '') {
        $sql .= ' AND f.original_name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY f.uploaded_at DESC, f.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_user_storage_usage(int $userId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function get_user_quota_bytes(int $userId): int {
    $stmt = get_db()->prepare('SELECT storage_quota_bytes FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (int) $val : DEFAULT_QUOTA_BYTES;
}

function detect_file_mime(string $tmpPath): string {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }
    }
    return 'application/octet-stream';
}

function upload_user_file(int $userId, array $file, ?int $folderId = null): array {
    if ($folderId !== null && $folderId > 0) {
        $perm = get_folder_access($userId, $folderId);
        if (!in_array($perm, ['owner', 'write'], true)) {
            return ['ok' => false, 'error' => 'No tienes permiso para subir archivos en esta carpeta.'];
        }
    } else {
        $folderId = null;
    }

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Solicitud de subida inválida.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Selecciona un archivo antes de subir.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'El archivo supera el límite permitido de 100 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al transferir el archivo (código ' . $file['error'] . ').'];
    }

    $fileSize = (int) $file['size'];
    if ($fileSize <= 0 || $fileSize > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'El archivo está vacío o supera los 100 MB.'];
    }

    $usedBytes = get_user_storage_usage($userId);
    $quotaBytes = get_user_quota_bytes($userId);
    if ($usedBytes + $fileSize > $quotaBytes) {
        $freeBytes = max(0, $quotaBytes - $usedBytes);
        return ['ok' => false, 'error' => 'Espacio insuficiente en tu cuenta (límite: ' . format_bytes($quotaBytes) . ', libre: ' . format_bytes($freeBytes) . ').'];
    }

    $originalName = basename((string) $file['name']);
    $originalName = preg_replace('/[\r\n\x00]/', '', $originalName) ?: 'archivo';
    $storedName = bin2hex(random_bytes(16));
    $mime = detect_file_mime((string) $file['tmp_name']);

    $targetDir = user_storage_dir($userId);
    $targetPath = $targetDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor.'];
    }
    chmod($targetPath, 0600);

    $pdo = get_db();
    $stmt = $pdo->prepare('
        INSERT INTO files (user_id, folder_id, original_name, stored_name, mime_type, size_bytes)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $folderId, $originalName, $storedName, $mime, $fileSize]);

    return ['ok' => true, 'name' => $originalName];
}

function get_accessible_file(int $actorId, int $fileId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('
        SELECT id, user_id, folder_id, original_name, stored_name, mime_type, size_bytes
        FROM files
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$fileId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $folderId = $row['folder_id'] !== null ? (int) $row['folder_id'] : null;
    $access = null;
    if ($folderId === null) {
        if ((int) $row['user_id'] !== $actorId) {
            return null;
        }
        $access = 'owner';
    } else {
        $access = get_folder_access($actorId, $folderId);
        if ($access === null) {
            return null;
        }
    }

    $ownerDir = user_storage_dir((int) $row['user_id']);
    $realFile = realpath($ownerDir . '/' . $row['stored_name']);
    if ($realFile === false || !str_starts_with($realFile, $ownerDir . '/') || !is_file($realFile)) {
        return null;
    }

    $row['full_path'] = $realFile;
    $row['access'] = $access;
    return $row;
}

function can_modify_file(array $fileRow): bool {
    return in_array($fileRow['access'] ?? null, ['owner', 'write'], true);
}

function update_user_file(int $actorId, int $fileId, string $newName, array $uploadedFile = []): array {
    $file = get_accessible_file($actorId, $fileId);
    if ($file === null || !can_modify_file($file)) {
        return ['ok' => false, 'error' => 'No tienes permisos para modificar este archivo.'];
    }

    $cleanName = basename(trim($newName));
    $cleanName = preg_replace('/[\r\n\x00]/', '', $cleanName) ?? '';
    $hasReplacement = isset($uploadedFile['error']) && !is_array($uploadedFile['error']) && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$hasReplacement) {
        if ($cleanName === '') {
            return ['ok' => false, 'error' => 'Indica un nombre válido o selecciona un archivo para reemplazar.'];
        }
        $stmt = get_db()->prepare('UPDATE files SET original_name = ?, uploaded_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$cleanName, $fileId]);
        return ['ok' => true, 'message' => 'Archivo renombrado a "' . $cleanName . '".'];
    }

    if ($uploadedFile['error'] === UPLOAD_ERR_INI_SIZE || $uploadedFile['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'El archivo supera el límite permitido de 100 MB.'];
    }
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir la nueva versión (código ' . $uploadedFile['error'] . ').'];
    }

    $newSize = (int) $uploadedFile['size'];
    if ($newSize <= 0 || $newSize > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'El archivo está vacío o supera los 100 MB.'];
    }

    $ownerId = (int) $file['user_id'];
    $usedBytes = get_user_storage_usage($ownerId);
    $quotaBytes = get_user_quota_bytes($ownerId);
    $delta = $newSize - (int) $file['size_bytes'];
    if ($delta > 0 && ($usedBytes + $delta > $quotaBytes)) {
        return ['ok' => false, 'error' => 'La nueva versión supera el límite de almacenamiento de la cuenta (' . format_bytes($quotaBytes) . ').'];
    }

    $finalName = $cleanName !== '' ? $cleanName : (preg_replace('/[\r\n\x00]/', '', basename((string) $uploadedFile['name'])) ?: $file['original_name']);
    $newStoredName = bin2hex(random_bytes(16));
    $mime = detect_file_mime((string) $uploadedFile['tmp_name']);
    $ownerDir = user_storage_dir($ownerId);
    $newPath = $ownerDir . '/' . $newStoredName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $newPath)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la nueva versión en el servidor.'];
    }
    chmod($newPath, 0600);

    if (is_file($file['full_path'])) {
        unlink($file['full_path']);
    }

    $stmt = get_db()->prepare('
        UPDATE files
        SET original_name = ?, stored_name = ?, mime_type = ?, size_bytes = ?, uploaded_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute([$finalName, $newStoredName, $mime, $newSize, $fileId]);

    return ['ok' => true, 'message' => 'Archivo "' . $finalName . '" actualizado correctamente.'];
}

function delete_user_file(int $actorId, int $fileId): bool {
    $file = get_accessible_file($actorId, $fileId);
    if ($file === null || !can_modify_file($file)) {
        return false;
    }

    if (is_file($file['full_path'])) {
        unlink($file['full_path']);
    }

    $stmt = get_db()->prepare('DELETE FROM files WHERE id = ?');
    $stmt->execute([$fileId]);
    return $stmt->rowCount() > 0;
}
