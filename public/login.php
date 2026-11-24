<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';

if (current_user() !== null) {
    header('Location: /index.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión inválida. Recarga la página e intenta nuevamente.';
    } else {
        $res = login_user($_POST['identifier'] ?? '', $_POST['password'] ?? '');
        if ($res['ok']) {
            header('Location: /index.php');
            exit;
        }
        $error = $res['error'];
    }
}
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
    <div class="auth-wrapper">
        <div class="auth-card">
            <a href="/index.php" class="brand" style="text-decoration:none;"><i class="fa-solid fa-cloud"></i><span>NAS</span></a>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom:16px;">
                    <div class="alert-body">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <span><?= h($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error" style="margin-bottom:16px;">
                    <div class="alert-body">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?= h($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <form method="POST" action="/login.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <div class="form-group">
                    <label for="identifier">Usuario o correo electrónico</label>
                    <input type="text" id="identifier" name="identifier" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
                </button>
            </form>
            <div class="auth-footer" style="display:flex;flex-direction:column;gap:8px;">
                <div><a href="/forgot-password.php">¿Olvidaste tu contraseña?</a></div>
                <?php if (defined('ALLOW_PUBLIC_REGISTER') && ALLOW_PUBLIC_REGISTER): ?>
                    <div>¿No tienes cuenta? <a href="/register.php">Crear cuenta</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
