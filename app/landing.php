<?php
declare(strict_types=1);

function render_landing_page(): void {
    $team = [
        [
            'initials' => 'CA',
            'name' => 'Cesar Enoc Aparicio Reyes',
            'due' => 'AA23026',
        ],
        [
            'initials' => 'CG',
            'name' => 'Cesar Alexander Garay Ortez',
            'due' => 'GO22007',
        ],
        [
            'initials' => 'JS',
            'name' => 'Juan Antonio Sandoval Paiz',
            'due' => 'SP23002',
        ],
    ];
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
        <div class="lp-page">
            <header class="lp-nav">
                <div class="lp-container lp-nav-inner">
                    <a href="/index.php" class="brand" style="margin:0;text-decoration:none;">
                        <i class="fa-solid fa-cloud"></i><span>NAS</span>
                    </a>

                    <div class="lp-nav-actions">
                        <a href="/login.php" class="btn">Iniciar sesión</a>
                        <?php if (defined('ALLOW_PUBLIC_REGISTER') && ALLOW_PUBLIC_REGISTER): ?>
                            <a href="/register.php" class="btn btn-primary">Crear cuenta</a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <section class="lp-hero">
                <div class="lp-container">
                    <div class="lp-hero-body">
                        <span class="pill pill-blue">Sistemas Operativos · FMO-UES</span>
                        <h1>Almacenamiento en red privado y seguro</h1>
                        <p>
                            Nube personal autohospedada en Raspberry Pi 3 con espacios aislados por usuario, carpetas compartidas y acceso remoto cifrado.
                        </p>
                        <div class="lp-hero-cta">
                            <a href="/login.php" class="btn btn-primary">
                                <i class="fa-solid fa-right-to-bracket"></i> Entrar al sistema
                            </a>
                            <?php if (defined('ALLOW_PUBLIC_REGISTER') && ALLOW_PUBLIC_REGISTER): ?>
                                <a href="/register.php" class="btn">Crear cuenta</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="lp-section">
                <div class="lp-container">
                    <div class="lp-grid-3">
                        <div class="lp-card">
                            <div class="lp-card-icon"><i class="fa-solid fa-hard-drive"></i></div>
                            <h3>Archivos y vista previa</h3>
                            <p>Carpetas anidadas, cuotas configurables por usuario y visor integrado para imágenes, PDF, texto y multimedia.</p>
                        </div>

                        <div class="lp-card">
                            <div class="lp-card-icon"><i class="fa-solid fa-share-nodes"></i></div>
                            <h3>Carpetas compartidas</h3>
                            <p>Invitaciones internas por correo con buzón de aprobación y permisos de solo lectura o edición.</p>
                        </div>

                        <div class="lp-card">
                            <div class="lp-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <h3>Aislamiento y VPN</h3>
                            <p>Directorios privados en Linux (<code>chmod 700</code>) sobre Nginx, PHP 8.4 y SQLite con túnel cifrado Tailscale.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="lp-section lp-section-alt" id="equipo">
                <div class="lp-container">
                    <div class="lp-section-head">
                        <h2>Equipo de desarrollo</h2>
                        <p>Ingeniería de Sistemas Informáticos · FMO-UES</p>
                    </div>

                    <div class="team-grid">
                        <?php foreach ($team as $member): ?>
                            <div class="team-card">
                                <div class="team-card-top">
                                    <div class="team-avatar"><?= h($member['initials']) ?></div>
                                    <span class="pill pill-blue">DUE: <?= h($member['due']) ?></span>
                                </div>
                                <div class="team-name"><?= h($member['name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <footer class="lp-footer">
                <div class="lp-container lp-footer-inner">
                    <div class="lp-footer-brand"><i class="fa-solid fa-cloud"></i><span>NAS</span></div>
                    <div style="color:#CBD5E1;">Universidad de El Salvador · Facultad Multidisciplinaria Oriental · 2026</div>
                </div>
            </footer>
        </div>
    </body>
    </html>
    <?php
}
