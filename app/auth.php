<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool {
    start_secure_session();
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function parse_quota_bytes(string $amountRaw, string $unit): ?int {
    $normalized = str_replace(',', '.', trim($amountRaw));
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }
    $val = (float) $normalized;
    if ($val <= 0 || $val > 10240) {
        return null;
    }
    $multiplier = ($unit === 'MB') ? (1024 * 1024) : (1024 * 1024 * 1024);
    $bytes = (int) round($val * $multiplier);
    return max(1024 * 1024, $bytes); // Mínimo 1 MB
}

function user_storage_dir(int $userId): string {
    $dir = STORAGE_DIR . '/u_' . $userId;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $realBase = realpath(STORAGE_DIR);
    $realDir = realpath($dir);
    if ($realBase === false || $realDir === false || !str_starts_with($realDir, $realBase . '/')) {
        throw new RuntimeException('Ruta de almacenamiento inválida.');
    }
    return $realDir;
}

function register_user(string $username, string $email, string $password, string $role = 'user', int $quotaBytes = DEFAULT_QUOTA_BYTES): array {
    $username = trim($username);
    $email = strtolower(trim($email));
    $role = in_array($role, ['user', 'admin'], true) ? $role : 'user';
    $quotaBytes = max(1024 * 1024, $quotaBytes);

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        return ['ok' => false, 'error' => 'El usuario debe tener entre 3 y 30 caracteres alfanuméricos.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Correo electrónico no válido.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'];
    }

    $pdo = get_db();
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        return ['ok' => false, 'error' => 'El usuario o correo ya está registrado.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, storage_quota_bytes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, $hash, $role, $quotaBytes]);
    $userId = (int) $pdo->lastInsertId();

    user_storage_dir($userId);

    return ['ok' => true, 'user_id' => $userId];
}

function login_user(string $identifier, string $password): array {
    $identifier = trim($identifier);
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role, storage_quota_bytes FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, strtolower($identifier)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
    }

    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'user',
        'storage_quota_bytes' => (int) ($user['storage_quota_bytes'] ?? DEFAULT_QUOTA_BYTES),
    ];

    user_storage_dir((int) $user['id']);

    return ['ok' => true, 'user' => $_SESSION['user']];
}

function current_user(): ?array {
    start_secure_session();
    if (empty($_SESSION['user']['id'])) {
        return null;
    }
    static $synced = false;
    if (!$synced) {
        $stmt = get_db()->prepare('SELECT id, username, email, role, storage_quota_bytes FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user']['id']]);
        $fresh = $stmt->fetch();
        if (!$fresh) {
            logout_user();
            return null;
        }
        $_SESSION['user'] = [
            'id' => (int) $fresh['id'],
            'username' => $fresh['username'],
            'email' => $fresh['email'],
            'role' => $fresh['role'] ?? 'user',
            'storage_quota_bytes' => (int) ($fresh['storage_quota_bytes'] ?? DEFAULT_QUOTA_BYTES),
        ];
        $synced = true;
    }
    return $_SESSION['user'];
}

function is_admin(): bool {
    $user = current_user();
    return $user !== null && (($user['role'] ?? 'user') === 'admin');
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if (($user['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        exit('Acceso denegado: se requieren permisos de administrador.');
    }
    return $user;
}

function list_all_users(): array {
    $pdo = get_db();
    return $pdo->query('
        SELECT u.id, u.username, u.email, u.role, u.storage_quota_bytes, u.created_at,
               COUNT(f.id) AS file_count,
               COALESCE(SUM(f.size_bytes), 0) AS used_bytes
        FROM users u
        LEFT JOIN files f ON f.user_id = u.id
        GROUP BY u.id
        ORDER BY u.id ASC
    ')->fetchAll();
}

function set_user_role(int $targetUserId, string $role, int $actorId): array {
    if ($targetUserId === $actorId) {
        return ['ok' => false, 'error' => 'No puedes cambiar el rol de tu propia sesión activa.'];
    }
    if (!in_array($role, ['user', 'admin'], true)) {
        return ['ok' => false, 'error' => 'Rol no válido.'];
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        return ['ok' => false, 'error' => 'El usuario no existe.'];
    }

    $upd = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
    $upd->execute([$role, $targetUserId]);

    return ['ok' => true, 'username' => $target['username'], 'role' => $role];
}

function update_user_account_admin(int $targetUserId, int $quotaBytes, string $newPassword = ''): array {
    $quotaBytes = max(1024 * 1024, $quotaBytes);
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        return ['ok' => false, 'error' => 'El usuario no existe.'];
    }

    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET storage_quota_bytes = ?, password_hash = ? WHERE id = ?');
        $upd->execute([$quotaBytes, $hash, $targetUserId]);
        return ['ok' => true, 'message' => 'Cuota y contraseña de "' . $target['username'] . '" actualizadas.'];
    }

    $upd = $pdo->prepare('UPDATE users SET storage_quota_bytes = ? WHERE id = ?');
    $upd->execute([$quotaBytes, $targetUserId]);
    return ['ok' => true, 'message' => 'Límite de almacenamiento de "' . $target['username'] . '" actualizado.'];
}

function change_own_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array {
    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'La nueva contraseña y su confirmación no coinciden.'];
    }
    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'La contraseña actual es incorrecta.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->execute([$hash, $userId]);

    start_secure_session();
    session_regenerate_id(true);

    return ['ok' => true, 'message' => 'Tu contraseña se actualizó correctamente.'];
}

function find_user_by_identifier(string $identifier): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    $stmt = get_db()->prepare('SELECT id, username, email FROM users WHERE username = ? OR LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_password_reset_state(string $identifier): array {
    $user = find_user_by_identifier($identifier);
    if (!$user) {
        return ['found' => false];
    }

    $stmt = get_db()->prepare('
        SELECT id, status, created_at, reviewed_at
        FROM password_reset_requests
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->execute([(int) $user['id']]);
    $latest = $stmt->fetch();

    return [
        'found' => true,
        'user' => $user,
        'request' => $latest ?: null,
    ];
}

function create_password_reset_request(string $identifier): array {
    $state = get_password_reset_state($identifier);
    if (!$state['found']) {
        return ['ok' => false, 'error' => 'No se encontró ninguna cuenta con ese usuario o correo.'];
    }

    $user = $state['user'];
    $latest = $state['request'];

    if ($latest && $latest['status'] === 'approved') {
        return ['ok' => true, 'state' => 'approved', 'user' => $user];
    }
    if ($latest && $latest['status'] === 'pending') {
        return ['ok' => true, 'state' => 'pending', 'user' => $user];
    }

    $ins = get_db()->prepare("INSERT INTO password_reset_requests (user_id, status) VALUES (?, 'pending')");
    $ins->execute([(int) $user['id']]);

    return ['ok' => true, 'state' => 'created', 'user' => $user];
}

function complete_password_reset(string $identifier, string $newPassword, string $confirmPassword): array {
    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'La nueva contraseña y su confirmación no coinciden.'];
    }
    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
    }

    $state = get_password_reset_state($identifier);
    if (!$state['found'] || !$state['request'] || $state['request']['status'] !== 'approved') {
        return ['ok' => false, 'error' => 'No tienes autorización activa del administrador para cambiar la contraseña.'];
    }

    $pdo = get_db();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, (int) $state['user']['id']]);
    $pdo->prepare("UPDATE password_reset_requests SET status = 'used' WHERE id = ?")->execute([(int) $state['request']['id']]);

    return ['ok' => true];
}

function count_pending_password_resets(): int {
    return (int) get_db()->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'")->fetchColumn();
}

function list_pending_password_resets(): array {
    return get_db()->query("
        SELECT r.id, r.user_id, r.status, r.created_at,
               u.username, u.email
        FROM password_reset_requests r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC, r.id DESC
    ")->fetchAll();
}

function list_recent_password_resets(): array {
    return get_db()->query("
        SELECT r.id, r.status, r.created_at, r.reviewed_at,
               u.username, u.email,
               adm.username AS reviewer_username
        FROM password_reset_requests r
        INNER JOIN users u ON u.id = r.user_id
        LEFT JOIN users adm ON adm.id = r.reviewed_by
        WHERE r.status != 'pending'
        ORDER BY r.id DESC
        LIMIT 15
    ")->fetchAll();
}

function review_password_reset(int $adminId, int $requestId, string $decision): array {
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return ['ok' => false, 'error' => 'Acción inválida.'];
    }
    $newStatus = ($decision === 'approve') ? 'approved' : 'rejected';
    $pdo = get_db();
    $check = $pdo->prepare("
        SELECT r.id, u.username
        FROM password_reset_requests r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.id = ? AND r.status = 'pending'
        LIMIT 1
    ");
    $check->execute([$requestId]);
    $row = $check->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'La solicitud ya fue atendida o no existe.'];
    }

    $upd = $pdo->prepare('UPDATE password_reset_requests SET status = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ?');
    $upd->execute([$newStatus, $adminId, $requestId]);

    return [
        'ok' => true,
        'message' => $newStatus === 'approved'
            ? 'Permiso concedido a "' . $row['username'] . '" para restablecer su contraseña.'
            : 'Solicitud de "' . $row['username'] . '" rechazada.',
    ];
}

function delete_user_account(int $targetUserId, int $actorId): array {
    if ($targetUserId === $actorId) {
        return ['ok' => false, 'error' => 'No puedes eliminar tu propia cuenta activa.'];
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        return ['ok' => false, 'error' => 'El usuario no existe.'];
    }

    $dir = STORAGE_DIR . '/u_' . $targetUserId;
    $realBase = realpath(STORAGE_DIR);
    $realDir = realpath($dir);
    if ($realBase !== false && $realDir !== false && str_starts_with($realDir, $realBase . '/') && is_dir($realDir)) {
        foreach (scandir($realDir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $realDir . '/' . $item;
            if (is_file($path)) {
                unlink($path);
            }
        }
        @rmdir($realDir);
    }

    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $del->execute([$targetUserId]);

    return ['ok' => true, 'username' => $target['username']];
}

function logout_user(): void {
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
