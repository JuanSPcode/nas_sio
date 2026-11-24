<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/files.php';

if (current_user() !== null) {
    header('Location: /index.php');
    exit;
}

$identifier = trim((string) ($_POST['identifier'] ?? $_GET['u'] ?? ''));
$error = '';
$info = '';
$canReset = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión inválida. Recarga la página e intenta nuevamente.';
    } else {
        $step = (string) ($_POST['step'] ?? 'request');

        if ($step === 'request') {
            $res = create_password_reset_request($identifier);
            if (!$res['ok']) {
                $error = $res['error'];
            } elseif ($res['state'] === 'approved') {
                $canReset = true;
                $info = 'El administrador ya autorizó tu cambio de contraseña.';
            } elseif ($res['state'] === 'pending') {
                $info = 'Ya tienes una solicitud pendiente en el buzón del administrador. Vuelve a comprobar aquí cuando te dé permiso.';
            } else {
                $info = 'Solicitud enviada al buzón del administrador. Cuando te conceda permiso, vuelve a ingresar tu usuario o correo aquí para cambiar la contraseña.';
            }
        } elseif ($step === 'reset') {
            $res = complete_password_reset(
                $identifier,
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            if ($res['ok']) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.'];
                header('Location: /login.php');
                exit;
            }
            $error = $res['error'];
            $state = get_password_reset_state($identifier);
            $canReset = ($state['found'] && $state['request'] && $state['request']['status'] === 'approved');
        }
    }
} elseif ($identifier !== '') {
    $state = get_password_reset_state($identifier);
    if ($state['found'] && $state['request'] && $state['request']['status'] === 'approved') {
        $canReset = true;
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

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" style="margin-bottom:16px;">
                    <div class="alert-body">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?= h($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($info !== ''): ?>
                <div class="alert alert-info" style="margin-bottom:16px;">
                    <div class="alert-body">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><?= h($info) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canReset): ?>
                <form method="POST" action="/forgot-password.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="step" value="reset">
                    <input type="hidden" name="identifier" value="<?= h($identifier) ?>">

                    <div class="form-group">
                        <label>Cuenta autorizada</label>
                        <input type="text" value="<?= h($identifier) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="resetNewPwd">Nueva contraseña (mínimo 8 caracteres)</label>
                        <input type="password" id="resetNewPwd" name="new_password" minlength="8" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="resetConfPwd">Confirmar contraseña</label>
                        <input type="password" id="resetConfPwd" name="confirm_password" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fa-solid fa-key"></i> Restablecer contraseña
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/forgot-password.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="step" value="request">
                    <div class="form-group">
                        <label for="reqIdentifier">Usuario o correo electrónico</label>
                        <input type="text" id="reqIdentifier" name="identifier" value="<?= h($identifier) ?>" required autofocus>
                        <span class="upload-hint" style="display:block;margin-top:6px;">
                            Se enviará una solicitud al buzón del administrador. Si ya te dio permiso, se habilitará el cambio de contraseña.
                        </span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fa-solid fa-paper-plane"></i> Solicitar o verificar permiso
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <a href="/login.php">Volver a iniciar sesión</a>
            </div>
        </div>
    </div>
</body>
</html>
