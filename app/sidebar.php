<?php
declare(strict_types=1);

function render_sidebar(array $user, string $activeNav, string $token): void {
    $userId = (int) $user['id'];
    $adminUser = is_admin();
    $pendingCount = count_pending_invites($userId) + ($adminUser ? count_pending_password_resets() : 0);
    $usedBytes = get_user_storage_usage($userId);
    $quotaBytes = get_user_quota_bytes($userId);
    $accountFreeBytes = max(0, $quotaBytes - $usedBytes);
    $diskFree = $adminUser ? (@disk_free_space(STORAGE_DIR) ?: 0) : 0;
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    ?>
    <header class="mobile-header">
        <a href="/index.php" class="brand" style="margin:0;text-decoration:none;">
            <i class="fa-solid fa-cloud"></i><span>NAS</span>
        </a>
        <button type="button" class="mobile-menu-btn" id="mobileMenuOpen" aria-label="Abrir menú" aria-expanded="false" aria-controls="appSidebar">
            <i class="fa-solid fa-bars"></i>
            <?php if ($pendingCount > 0): ?>
                <span class="menu-dot" aria-hidden="true"></span>
            <?php endif; ?>
        </button>
    </header>

    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <aside class="sidebar" id="appSidebar">
        <div class="sidebar-top">
            <div class="brand" style="margin:0;"><i class="fa-solid fa-cloud"></i><span>NAS</span></div>
            <button type="button" class="btn btn-icon sidebar-close-btn" id="mobileMenuClose" aria-label="Cerrar menú">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="nav-menu" aria-label="Navegación principal">
            <a href="/index.php" class="nav-item <?= $activeNav === 'drive' ? 'active' : '' ?>">
                <span class="nav-label"><i class="fa-solid fa-hard-drive"></i> Mi unidad</span>
            </a>
            <a href="/folders.php" class="nav-item <?= $activeNav === 'folders' ? 'active' : '' ?>">
                <span class="nav-label"><i class="fa-solid fa-folder-open"></i> Carpetas</span>
            </a>
            <a href="/inbox.php" class="nav-item <?= $activeNav === 'inbox' ? 'active' : '' ?>">
                <span class="nav-label"><i class="fa-solid fa-inbox"></i> Buzón</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="nav-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <?php if ($adminUser): ?>
                <a href="/admin.php" class="nav-item <?= $activeNav === 'admin' ? 'active' : '' ?>">
                    <span class="nav-label"><i class="fa-solid fa-users-gear"></i> Gestionar usuarios</span>
                </a>
            <?php endif; ?>
            <button type="button" class="nav-item" id="openChangePwdBtn" style="width:100%;border:none;background:transparent;font:inherit;cursor:pointer;text-align:left;">
                <span class="nav-label"><i class="fa-solid fa-key"></i> Cambiar contraseña</span>
            </button>
        </nav>

        <div class="storage-card">
            <div><i class="fa-solid fa-hard-drive"></i> <strong>Mi uso:</strong> <?= h(format_bytes($usedBytes)) ?></div>
            <div><i class="fa-regular fa-circle-check"></i> <strong>Libre:</strong> <?= h(format_bytes($accountFreeBytes)) ?> <span style="font-size:0.76rem;">(de <?= h(format_bytes($quotaBytes)) ?>)</span></div>
            <?php if ($adminUser): ?>
                <div><i class="fa-solid fa-server"></i> <strong>Disco libre NAS:</strong> <?= h(format_bytes((int) $diskFree)) ?></div>
            <?php endif; ?>
        </div>

        <div class="user-card">
            <div class="user-meta">
                <div class="user-name"><i class="fa-regular fa-user"></i> <?= h($user['username']) ?></div>
                <div class="user-email"><?= h($user['email']) ?></div>
            </div>
            <form method="POST" action="/action.php">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-icon" title="Cerrar sesión">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </aside>

    <div class="modal-backdrop" id="changePwdModal" aria-hidden="true">
        <div class="modal-box modal-box-sm" role="dialog" aria-modal="true" aria-labelledby="changePwdTitle">
            <div class="modal-header">
                <div class="modal-title" id="changePwdTitle"><i class="fa-solid fa-key"></i> Cambiar contraseña</div>
                <button type="button" class="btn btn-icon" id="closeChangePwdBtn" title="Cerrar (Esc)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="/action.php" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="return_to" value="<?= h($currentUri) ?>">

                <div class="form-group" style="margin:0;">
                    <label for="curPwd">Contraseña actual</label>
                    <input type="password" id="curPwd" name="current_password" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="newPwd">Nueva contraseña (mín. 8 caracteres)</label>
                    <input type="password" id="newPwd" name="new_password" minlength="8" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="confPwd">Confirmar contraseña</label>
                    <input type="password" id="confPwd" name="confirm_password" minlength="8" required>
                </div>

                <div class="modal-footer-actions">
                    <button type="button" class="btn" id="cancelChangePwdBtn">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Guardar contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (() => {
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const openBtn = document.getElementById('mobileMenuOpen');
        const closeBtn = document.getElementById('mobileMenuClose');

        function openMenu() {
            if (!sidebar) return;
            sidebar.classList.add('open');
            backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
            openBtn.setAttribute('aria-expanded', 'true');
        }
        function closeMenu() {
            if (!sidebar) return;
            sidebar.classList.remove('open');
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
            openBtn.setAttribute('aria-expanded', 'false');
        }

        if (openBtn && closeBtn && backdrop) {
            openBtn.addEventListener('click', openMenu);
            closeBtn.addEventListener('click', closeMenu);
            backdrop.addEventListener('click', closeMenu);
        }

        const pwdModal = document.getElementById('changePwdModal');
        const openPwdBtn = document.getElementById('openChangePwdBtn');
        const closePwdBtn = document.getElementById('closeChangePwdBtn');
        const cancelPwdBtn = document.getElementById('cancelChangePwdBtn');
        const curPwdInput = document.getElementById('curPwd');

        function closePwdModal() {
            if (!pwdModal) return;
            pwdModal.classList.remove('open');
            pwdModal.setAttribute('aria-hidden', 'true');
        }

        if (pwdModal && openPwdBtn) {
            openPwdBtn.addEventListener('click', () => {
                closeMenu();
                pwdModal.classList.add('open');
                pwdModal.setAttribute('aria-hidden', 'false');
                if (curPwdInput) curPwdInput.focus();
            });
            closePwdBtn.addEventListener('click', closePwdModal);
            cancelPwdBtn.addEventListener('click', closePwdModal);
            pwdModal.addEventListener('click', (e) => { if (e.target === pwdModal) closePwdModal(); });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (sidebar && sidebar.classList.contains('open')) closeMenu();
                if (pwdModal && pwdModal.classList.contains('open')) closePwdModal();
            }
        });
    })();
    </script>
    <?php
}
