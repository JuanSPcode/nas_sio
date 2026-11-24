<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function str_length(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function get_folder_ancestors(int $folderId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, user_id, parent_id, name, description, created_at FROM folders WHERE id = ? LIMIT 1');
    $chain = [];
    $visited = [];
    $currentId = $folderId;

    while ($currentId > 0 && !isset($visited[$currentId]) && count($chain) < 50) {
        $visited[$currentId] = true;
        $stmt->execute([$currentId]);
        $row = $stmt->fetch();
        if (!$row) {
            break;
        }
        $chain[] = $row;
        $currentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
    }

    return array_reverse($chain);
}

function get_folder_access(int $userId, int $folderId): ?string {
    $ancestors = get_folder_ancestors($folderId);
    if (empty($ancestors)) {
        return null;
    }

    $target = end($ancestors);
    if ((int) $target['user_id'] === $userId) {
        return 'owner';
    }

    $pdo = get_db();
    $share = $pdo->prepare("
        SELECT permission
        FROM folder_shares
        WHERE folder_id = ? AND invited_user_id = ? AND status = 'accepted'
        LIMIT 1
    ");

    for ($i = count($ancestors) - 1; $i >= 0; $i--) {
        $ancId = (int) $ancestors[$i]['id'];
        $share->execute([$ancId, $userId]);
        $row = $share->fetch();
        if ($row) {
            return (string) $row['permission'];
        }
    }

    return null;
}

function get_visible_breadcrumbs(int $userId, int $folderId): array {
    $ancestors = get_folder_ancestors($folderId);
    $visible = [];
    foreach ($ancestors as $anc) {
        if (get_folder_access($userId, (int) $anc['id']) !== null) {
            $visible[] = $anc;
        }
    }
    return $visible;
}

function create_folder(int $userId, string $name, ?int $parentId = null, string $description = ''): array {
    $name = trim(preg_replace('/[\r\n\x00]/', '', $name) ?? '');
    $description = trim(preg_replace('/[\r\n\x00]/', ' ', $description) ?? '');

    if ($name === '' || str_length($name) > 80) {
        return ['ok' => false, 'error' => 'El nombre de la carpeta debe tener entre 1 y 80 caracteres.'];
    }

    $pdo = get_db();
    $ownerId = $userId;

    if ($parentId !== null && $parentId > 0) {
        $access = get_folder_access($userId, $parentId);
        if (!in_array($access, ['owner', 'write'], true)) {
            return ['ok' => false, 'error' => 'No tienes permiso para crear subcarpetas aquí.'];
        }
        $pStmt = $pdo->prepare('SELECT user_id FROM folders WHERE id = ? LIMIT 1');
        $pStmt->execute([$parentId]);
        $pRow = $pStmt->fetch();
        if (!$pRow) {
            return ['ok' => false, 'error' => 'La carpeta padre no existe.'];
        }
        $ownerId = (int) $pRow['user_id'];

        $check = $pdo->prepare('SELECT id FROM folders WHERE parent_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
        $check->execute([$parentId, $name]);
    } else {
        $parentId = null;
        $check = $pdo->prepare('SELECT id FROM folders WHERE user_id = ? AND parent_id IS NULL AND LOWER(name) = LOWER(?) LIMIT 1');
        $check->execute([$ownerId, $name]);
    }

    if ($check->fetch()) {
        return ['ok' => false, 'error' => 'Ya existe una carpeta con ese nombre en esta ubicación.'];
    }

    $stmt = $pdo->prepare('INSERT INTO folders (user_id, parent_id, name, description) VALUES (?, ?, ?, ?)');
    $stmt->execute([$ownerId, $parentId, $name, $description]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'name' => $name];
}

function list_owned_folders(int $userId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT fo.id, fo.name, fo.description, fo.created_at,
               (SELECT COUNT(*) FROM files f WHERE f.folder_id = fo.id) AS file_count,
               (SELECT COUNT(*) FROM folders sub WHERE sub.parent_id = fo.id) AS subfolder_count,
               (SELECT COALESCE(SUM(f.size_bytes), 0) FROM files f WHERE f.folder_id = fo.id) AS total_bytes,
               (SELECT COUNT(*) FROM folder_shares fs WHERE fs.folder_id = fo.id AND fs.status = 'accepted') AS shared_count,
               (SELECT COUNT(*) FROM folder_shares fs WHERE fs.folder_id = fo.id AND fs.status = 'pending') AS pending_count
        FROM folders fo
        WHERE fo.user_id = ? AND fo.parent_id IS NULL
        ORDER BY fo.created_at DESC, fo.id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function list_child_folders(int $userId, int $parentId): array {
    if (get_folder_access($userId, $parentId) === null) {
        return [];
    }
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT fo.id, fo.name, fo.description, fo.created_at,
               (SELECT COUNT(*) FROM files f WHERE f.folder_id = fo.id) AS file_count,
               (SELECT COUNT(*) FROM folders sub WHERE sub.parent_id = fo.id) AS subfolder_count,
               (SELECT COALESCE(SUM(f.size_bytes), 0) FROM files f WHERE f.folder_id = fo.id) AS total_bytes,
               (SELECT COUNT(*) FROM folder_shares fs WHERE fs.folder_id = fo.id AND fs.status = 'accepted') AS shared_count,
               (SELECT COUNT(*) FROM folder_shares fs WHERE fs.folder_id = fo.id AND fs.status = 'pending') AS pending_count
        FROM folders fo
        WHERE fo.parent_id = ?
        ORDER BY fo.name COLLATE NOCASE ASC, fo.id ASC
    ");
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

function list_shared_with_me_folders(int $userId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT fo.id, fo.name, fo.description, fo.created_at,
               u.username AS owner_username, u.email AS owner_email,
               fs.id AS share_id, fs.permission, fs.responded_at,
               (SELECT COUNT(*) FROM files f WHERE f.folder_id = fo.id) AS file_count,
               (SELECT COUNT(*) FROM folders sub WHERE sub.parent_id = fo.id) AS subfolder_count,
               (SELECT COALESCE(SUM(f.size_bytes), 0) FROM files f WHERE f.folder_id = fo.id) AS total_bytes
        FROM folder_shares fs
        INNER JOIN folders fo ON fo.id = fs.folder_id
        INNER JOIN users u ON u.id = fo.user_id
        WHERE fs.invited_user_id = ? AND fs.status = 'accepted'
        ORDER BY fs.responded_at DESC, fo.id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_folder_for_user(int $userId, int $folderId): ?array {
    $perm = get_folder_access($userId, $folderId);
    if ($perm === null) {
        return null;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('
        SELECT fo.id, fo.user_id, fo.parent_id, fo.name, fo.description, fo.created_at,
               u.username AS owner_username, u.email AS owner_email
        FROM folders fo
        INNER JOIN users u ON u.id = fo.user_id
        WHERE fo.id = ?
        LIMIT 1
    ');
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch();
    if (!$folder) {
        return null;
    }
    $folder['access'] = $perm;
    return $folder;
}

function list_folder_collaborators(int $ownerId, int $folderId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT fs.id, fs.permission, fs.status, fs.created_at, fs.responded_at,
               u.id AS user_id, u.username, u.email
        FROM folder_shares fs
        INNER JOIN users u ON u.id = fs.invited_user_id
        WHERE fs.folder_id = ? AND fs.owner_id = ?
        ORDER BY CASE fs.status WHEN 'accepted' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END, fs.created_at DESC
    ");
    $stmt->execute([$folderId, $ownerId]);
    return $stmt->fetchAll();
}

function invite_user_to_folder(int $ownerId, int $folderId, string $email, string $permission): array {
    $email = strtolower(trim($email));
    $permission = in_array($permission, ['read', 'write'], true) ? $permission : 'read';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Ingresa un correo electrónico válido.'];
    }

    $pdo = get_db();
    $folder = $pdo->prepare('SELECT id, name FROM folders WHERE id = ? AND user_id = ? LIMIT 1');
    $folder->execute([$folderId, $ownerId]);
    $folderRow = $folder->fetch();
    if (!$folderRow) {
        return ['ok' => false, 'error' => 'No tienes permisos para compartir esta carpeta.'];
    }

    $target = $pdo->prepare('SELECT id, username, email FROM users WHERE LOWER(email) = ? LIMIT 1');
    $target->execute([$email]);
    $targetUser = $target->fetch();
    if (!$targetUser) {
        return ['ok' => false, 'error' => 'No existe ningún usuario registrado en el NAS con ese correo.'];
    }
    if ((int) $targetUser['id'] === $ownerId) {
        return ['ok' => false, 'error' => 'Ya eres el propietario de esta carpeta.'];
    }

    $existing = $pdo->prepare('SELECT id, status FROM folder_shares WHERE folder_id = ? AND invited_user_id = ? LIMIT 1');
    $existing->execute([$folderId, (int) $targetUser['id']]);
    $shareRow = $existing->fetch();

    if ($shareRow) {
        if ($shareRow['status'] === 'accepted') {
            $upd = $pdo->prepare('UPDATE folder_shares SET permission = ? WHERE id = ?');
            $upd->execute([$permission, (int) $shareRow['id']]);
            return ['ok' => true, 'message' => $targetUser['username'] . ' ya era colaborador; se actualizó su permiso.'];
        }
        $upd = $pdo->prepare("
            UPDATE folder_shares
            SET permission = ?, status = 'pending', created_at = CURRENT_TIMESTAMP, responded_at = NULL
            WHERE id = ?
        ");
        $upd->execute([$permission, (int) $shareRow['id']]);
        return ['ok' => true, 'message' => 'Se reenvió la invitación al buzón de ' . $targetUser['username'] . '.'];
    }

    $ins = $pdo->prepare("
        INSERT INTO folder_shares (folder_id, owner_id, invited_user_id, permission, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $ins->execute([$folderId, $ownerId, (int) $targetUser['id'], $permission]);
    return ['ok' => true, 'message' => 'Invitación enviada al buzón de ' . $targetUser['username'] . ' (' . $targetUser['email'] . ').'];
}

function update_share_permission(int $ownerId, int $shareId, string $permission): bool {
    if (!in_array($permission, ['read', 'write'], true)) {
        return false;
    }
    $stmt = get_db()->prepare('UPDATE folder_shares SET permission = ? WHERE id = ? AND owner_id = ?');
    $stmt->execute([$permission, $shareId, $ownerId]);
    return $stmt->rowCount() > 0;
}

function revoke_folder_share(int $ownerId, int $shareId): bool {
    $stmt = get_db()->prepare('DELETE FROM folder_shares WHERE id = ? AND owner_id = ?');
    $stmt->execute([$shareId, $ownerId]);
    return $stmt->rowCount() > 0;
}

function leave_shared_folder(int $userId, int $folderId): bool {
    $stmt = get_db()->prepare('DELETE FROM folder_shares WHERE folder_id = ? AND invited_user_id = ?');
    $stmt->execute([$folderId, $userId]);
    return $stmt->rowCount() > 0;
}

function count_pending_invites(int $userId): int {
    $stmt = get_db()->prepare("SELECT COUNT(*) FROM folder_shares WHERE invited_user_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function list_pending_invites(int $userId): array {
    $stmt = get_db()->prepare("
        SELECT fs.id, fs.folder_id, fs.permission, fs.created_at,
               fo.name AS folder_name, fo.description AS folder_description,
               u.username AS owner_username, u.email AS owner_email
        FROM folder_shares fs
        INNER JOIN folders fo ON fo.id = fs.folder_id
        INNER JOIN users u ON u.id = fs.owner_id
        WHERE fs.invited_user_id = ? AND fs.status = 'pending'
        ORDER BY fs.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function list_sent_invites(int $ownerId): array {
    $stmt = get_db()->prepare('
        SELECT fs.id, fs.folder_id, fs.permission, fs.status, fs.created_at, fs.responded_at,
               fo.name AS folder_name,
               u.username AS invited_username, u.email AS invited_email
        FROM folder_shares fs
        INNER JOIN folders fo ON fo.id = fs.folder_id
        INNER JOIN users u ON u.id = fs.invited_user_id
        WHERE fs.owner_id = ?
        ORDER BY fs.created_at DESC
    ');
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

function respond_to_invite(int $userId, int $shareId, string $decision): array {
    if (!in_array($decision, ['accept', 'decline'], true)) {
        return ['ok' => false, 'error' => 'Respuesta inválida.'];
    }
    $newStatus = $decision === 'accept' ? 'accepted' : 'declined';
    $pdo = get_db();
    $check = $pdo->prepare("
        SELECT fs.id, fo.name AS folder_name
        FROM folder_shares fs
        INNER JOIN folders fo ON fo.id = fs.folder_id
        WHERE fs.id = ? AND fs.invited_user_id = ? AND fs.status = 'pending'
        LIMIT 1
    ");
    $check->execute([$shareId, $userId]);
    $row = $check->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'La invitación ya no está disponible.'];
    }

    $upd = $pdo->prepare('UPDATE folder_shares SET status = ?, responded_at = CURRENT_TIMESTAMP WHERE id = ?');
    $upd->execute([$newStatus, $shareId]);

    return [
        'ok' => true,
        'status' => $newStatus,
        'folder_name' => $row['folder_name'],
    ];
}

function delete_folder(int $actorId, int $folderId): bool {
    $pdo = get_db();
    $check = $pdo->prepare('SELECT id, parent_id FROM folders WHERE id = ? LIMIT 1');
    $check->execute([$folderId]);
    $root = $check->fetch();
    if (!$root) {
        return false;
    }

    $access = get_folder_access($actorId, $folderId);
    $allowed = ($root['parent_id'] === null)
        ? ($access === 'owner')
        : in_array($access, ['owner', 'write'], true);

    if (!$allowed) {
        return false;
    }

    $toDelete = [$folderId];
    $queue = [$folderId];
    $childStmt = $pdo->prepare('SELECT id FROM folders WHERE parent_id = ?');

    while (!empty($queue)) {
        $curr = array_shift($queue);
        $childStmt->execute([$curr]);
        foreach ($childStmt->fetchAll() as $child) {
            $cid = (int) $child['id'];
            if (!in_array($cid, $toDelete, true)) {
                $toDelete[] = $cid;
                $queue[] = $cid;
            }
        }
    }

    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
    $files = $pdo->prepare("SELECT id, user_id, stored_name FROM files WHERE folder_id IN ($placeholders)");
    $files->execute($toDelete);
    foreach ($files->fetchAll() as $f) {
        $dir = user_storage_dir((int) $f['user_id']);
        $realFile = realpath($dir . '/' . $f['stored_name']);
        if ($realFile !== false && str_starts_with($realFile, $dir . '/') && is_file($realFile)) {
            unlink($realFile);
        }
    }

    $pdo->prepare("DELETE FROM files WHERE folder_id IN ($placeholders)")->execute($toDelete);
    $pdo->prepare("DELETE FROM folder_shares WHERE folder_id IN ($placeholders)")->execute($toDelete);
    $del = $pdo->prepare("DELETE FROM folders WHERE id IN ($placeholders)");
    $del->execute($toDelete);

    return $del->rowCount() > 0;
}
