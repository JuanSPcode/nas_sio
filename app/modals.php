<?php
declare(strict_types=1);

function render_new_dropdown(): void {
    ?>
    <div class="dropdown" data-dropdown>
        <button type="button" class="btn btn-primary dropdown-toggle" aria-haspopup="true" aria-expanded="false">
            <i class="fa-solid fa-plus"></i>
            <span>Nuevo</span>
            <i class="fa-solid fa-chevron-down" style="font-size:0.72rem;margin-left:2px;"></i>
        </button>
        <div class="dropdown-menu" role="menu">
            <button type="button" class="dropdown-item upload-trigger-btn" role="menuitem">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>Subir archivo</span>
            </button>
            <button type="button" class="dropdown-item create-folder-trigger-btn" role="menuitem">
                <i class="fa-solid fa-folder-plus"></i>
                <span>Nueva carpeta</span>
            </button>
        </div>
    </div>
    <?php
}

function render_file_modals(string $token, string $returnTo, bool $enableUpload = false, ?int $uploadFolderId = null, string $uploadTargetLabel = 'Mi unidad', bool $enableCreateFolder = false, ?int $parentFolderId = null): void {
    ?>
    <div class="modal-backdrop" id="previewModal" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="previewTitle">
            <div class="modal-header">
                <div class="modal-title" id="previewTitle">Vista previa</div>
                <div class="modal-actions">
                    <a id="previewOpenNew" class="btn btn-icon" href="#" target="_blank" rel="noopener" title="Abrir en pestaña nueva">
                        <i class="fa-solid fa-up-right-from-square"></i>
                    </a>
                    <a id="previewDownload" class="btn btn-icon" href="#" title="Descargar">
                        <i class="fa-solid fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-icon" id="previewClose" title="Cerrar (Esc)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body" id="previewBody"></div>
        </div>
    </div>

    <div class="modal-backdrop" id="editFileModal" aria-hidden="true">
        <div class="modal-box modal-box-sm" role="dialog" aria-modal="true" aria-labelledby="editFileTitle">
            <div class="modal-header">
                <div class="modal-title" id="editFileTitle"><i class="fa-solid fa-pen-to-square"></i> Actualizar archivo</div>
                <button type="button" class="btn btn-icon" id="editFileClose" title="Cerrar (Esc)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="/action.php" enctype="multipart/form-data" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="update_file">
                <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                <input type="hidden" name="id" id="editFileId" value="">

                <div class="form-group" style="margin:0;">
                    <label for="editFileName">Nombre del archivo</label>
                    <input type="text" id="editFileName" name="new_name" required>
                </div>

                <div class="form-group" style="margin:0;">
                    <label for="editFileUpload">Reemplazar contenido (opcional, máx. 100 MB)</label>
                    <input type="file" id="editFileUpload" name="file">
                    <span class="upload-hint">Puedes cambiar solo el nombre o subir una nueva versión.</span>
                </div>

                <div class="modal-footer-actions">
                    <button type="button" class="btn" id="editFileCancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($enableCreateFolder): ?>
        <div class="modal-backdrop" id="createFolderModal" aria-hidden="true">
            <div class="modal-box modal-box-sm" role="dialog" aria-modal="true" aria-labelledby="createFolderTitle">
                <div class="modal-header">
                    <div class="modal-title" id="createFolderTitle"><i class="fa-solid fa-folder-plus"></i> Nueva carpeta</div>
                    <button type="button" class="btn btn-icon" id="closeCreateFolderBtn" title="Cerrar (Esc)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="/action.php" style="padding:20px;display:flex;flex-direction:column;gap:16px;">
                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                    <?php if ($parentFolderId !== null): ?>
                        <input type="hidden" name="parent_id" value="<?= $parentFolderId ?>">
                    <?php endif; ?>
                    <div class="form-group" style="margin:0;">
                        <label for="newFolderName">Nombre de la carpeta</label>
                        <input type="text" id="newFolderName" name="name" maxlength="80" placeholder="Ej. Proyecto Redes UES" required>
                    </div>
                    <div class="modal-footer-actions">
                        <button type="button" class="btn" id="cancelCreateFolderBtn">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-folder-plus"></i> Crear carpeta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($enableUpload): ?>
        <div class="drag-overlay" id="dragOverlay" aria-hidden="true">
            <div class="drag-overlay-box">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <strong>Suelta el archivo aquí</strong>
                <span>Se preparará para subir a <?= h($uploadTargetLabel) ?></span>
            </div>
        </div>

        <div class="modal-backdrop" id="uploadConfirmModal" aria-hidden="true">
            <div class="modal-box modal-box-sm" role="dialog" aria-modal="true" aria-labelledby="uploadConfirmTitle">
                <div class="modal-header">
                    <div class="modal-title" id="uploadConfirmTitle"><i class="fa-solid fa-cloud-arrow-up"></i> Confirmar subida</div>
                    <button type="button" class="btn btn-icon" id="uploadConfirmClose" title="Cerrar (Esc)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="/action.php" enctype="multipart/form-data" id="globalUploadForm" style="padding:20px;display:flex;flex-direction:column;gap:16px;">
                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                    <?php if ($uploadFolderId !== null): ?>
                        <input type="hidden" name="folder_id" value="<?= $uploadFolderId ?>">
                    <?php endif; ?>
                    <input type="file" name="file" id="globalUploadInput" hidden>

                    <div class="file-confirm-card">
                        <div class="file-confirm-icon"><i class="fa-regular fa-file"></i></div>
                        <div class="file-confirm-meta">
                            <div class="file-confirm-name" id="uploadFileName">archivo</div>
                            <div class="file-confirm-sub">
                                <span id="uploadFileSize">0 B</span> · Destino: <strong><?= h($uploadTargetLabel) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-error" id="uploadSizeError" style="display:none;margin:0;">
                        <div class="alert-body">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>El archivo supera el límite máximo permitido de 100 MB.</span>
                        </div>
                    </div>

                    <div class="modal-footer-actions">
                        <button type="button" class="btn" id="uploadConfirmCancel">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="uploadConfirmSubmit">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
    (() => {
        function closeAllDropdowns() {
            document.querySelectorAll('[data-dropdown]').forEach((dd) => {
                dd.classList.remove('open');
                const btn = dd.querySelector('.dropdown-toggle');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        }

        document.querySelectorAll('[data-dropdown]').forEach((dd) => {
            const toggle = dd.querySelector('.dropdown-toggle');
            if (!toggle) return;
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = dd.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) {
                    dd.classList.add('open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', closeAllDropdowns);

        const pModal = document.getElementById('previewModal');
        const pTitle = document.getElementById('previewTitle');
        const pBody = document.getElementById('previewBody');
        const pOpenNew = document.getElementById('previewOpenNew');
        const pDownload = document.getElementById('previewDownload');
        const pClose = document.getElementById('previewClose');

        function closePreview() {
            pModal.classList.remove('open');
            pModal.setAttribute('aria-hidden', 'true');
            pBody.innerHTML = '';
            pBody.classList.remove('is-text');
        }

        async function openPreview(id, name, type) {
            const previewUrl = '/action.php?action=preview&id=' + encodeURIComponent(id);
            const downloadUrl = '/action.php?action=download&id=' + encodeURIComponent(id);
            pTitle.textContent = name;
            pOpenNew.href = previewUrl;
            pDownload.href = downloadUrl;
            pBody.classList.toggle('is-text', type === 'text');
            pBody.innerHTML = '<div class="preview-status"><i class="fa-solid fa-spinner fa-spin"></i> Cargando vista previa...</div>';
            pModal.classList.add('open');
            pModal.setAttribute('aria-hidden', 'false');

            if (type === 'image') {
                const img = document.createElement('img');
                img.src = previewUrl;
                img.alt = name;
                pBody.innerHTML = '';
                pBody.appendChild(img);
                return;
            }
            if (type === 'pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = previewUrl;
                iframe.title = name;
                pBody.innerHTML = '';
                pBody.appendChild(iframe);
                return;
            }
            if (type === 'video' || type === 'audio') {
                const media = document.createElement(type);
                media.src = previewUrl;
                media.controls = true;
                media.playsInline = true;
                pBody.innerHTML = '';
                pBody.appendChild(media);
                return;
            }
            if (type === 'text') {
                try {
                    const res = await fetch(previewUrl);
                    if (!res.ok) throw new Error();
                    const pre = document.createElement('pre');
                    pre.className = 'preview-text';
                    pre.textContent = await res.text();
                    pBody.innerHTML = '';
                    pBody.appendChild(pre);
                } catch {
                    pBody.innerHTML = '<div class="preview-status" style="color:var(--danger);">Error al cargar la vista previa.</div>';
                }
            }
        }

        document.querySelectorAll('.preview-trigger').forEach((btn) => {
            btn.addEventListener('click', () => openPreview(btn.dataset.id, btn.dataset.name, btn.dataset.type));
        });
        pClose.addEventListener('click', closePreview);
        pModal.addEventListener('click', (e) => { if (e.target === pModal) closePreview(); });

        const eModal = document.getElementById('editFileModal');
        const eId = document.getElementById('editFileId');
        const eName = document.getElementById('editFileName');
        const eUpload = document.getElementById('editFileUpload');
        const eClose = document.getElementById('editFileClose');
        const eCancel = document.getElementById('editFileCancel');

        function closeEdit() {
            eModal.classList.remove('open');
            eModal.setAttribute('aria-hidden', 'true');
            eUpload.value = '';
        }

        document.querySelectorAll('.edit-trigger').forEach((btn) => {
            btn.addEventListener('click', () => {
                eId.value = btn.dataset.id;
                eName.value = btn.dataset.name;
                eUpload.value = '';
                eModal.classList.add('open');
                eModal.setAttribute('aria-hidden', 'false');
                eName.focus();
            });
        });

        eClose.addEventListener('click', closeEdit);
        eCancel.addEventListener('click', closeEdit);
        eModal.addEventListener('click', (e) => { if (e.target === eModal) closeEdit(); });

        const cfModal = document.getElementById('createFolderModal');
        const cfClose = document.getElementById('closeCreateFolderBtn');
        const cfCancel = document.getElementById('cancelCreateFolderBtn');
        const cfInput = document.getElementById('newFolderName');

        function closeCreateFolder() {
            if (!cfModal) return;
            cfModal.classList.remove('open');
            cfModal.setAttribute('aria-hidden', 'true');
        }

        if (cfModal) {
            document.querySelectorAll('.create-folder-trigger-btn').forEach((el) => {
                el.addEventListener('click', () => {
                    closeAllDropdowns();
                    cfModal.classList.add('open');
                    cfModal.setAttribute('aria-hidden', 'false');
                    if (cfInput) cfInput.focus();
                });
            });
            cfClose.addEventListener('click', closeCreateFolder);
            cfCancel.addEventListener('click', closeCreateFolder);
            cfModal.addEventListener('click', (e) => { if (e.target === cfModal) closeCreateFolder(); });
        }

        const uModal = document.getElementById('uploadConfirmModal');
        const uInput = document.getElementById('globalUploadInput');
        const uForm = document.getElementById('globalUploadForm');
        const uName = document.getElementById('uploadFileName');
        const uSize = document.getElementById('uploadFileSize');
        const uErr = document.getElementById('uploadSizeError');
        const uSubmit = document.getElementById('uploadConfirmSubmit');
        const uClose = document.getElementById('uploadConfirmClose');
        const uCancel = document.getElementById('uploadConfirmCancel');
        const dragOverlay = document.getElementById('dragOverlay');
        const maxBytes = <?= (int) MAX_UPLOAD_BYTES ?>;

        function humanSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            const units = ['KB', 'MB', 'GB'];
            let size = bytes / 1024;
            for (let i = 0; i < units.length; i++) {
                if (size < 1024 || i === units.length - 1) return size.toFixed(2) + ' ' + units[i];
                size /= 1024;
            }
            return size.toFixed(2) + ' GB';
        }

        function closeUploadModal() {
            if (!uModal) return;
            uModal.classList.remove('open');
            uModal.setAttribute('aria-hidden', 'true');
            uInput.value = '';
            uErr.style.display = 'none';
            uSubmit.disabled = false;
            uSubmit.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Subir';
        }

        function showUploadConfirm(file) {
            if (!uModal || !file) return;
            uName.textContent = file.name;
            uSize.textContent = humanSize(file.size);
            const tooBig = file.size > maxBytes || file.size <= 0;
            uErr.style.display = tooBig ? 'flex' : 'none';
            uSubmit.disabled = tooBig;
            uModal.classList.add('open');
            uModal.setAttribute('aria-hidden', 'false');
        }

        if (uModal && uInput) {
            document.querySelectorAll('.upload-trigger-btn').forEach((el) => {
                el.addEventListener('click', () => {
                    closeAllDropdowns();
                    uInput.click();
                });
            });

            uInput.addEventListener('change', () => {
                if (uInput.files && uInput.files.length > 0) {
                    showUploadConfirm(uInput.files[0]);
                }
            });

            uClose.addEventListener('click', closeUploadModal);
            uCancel.addEventListener('click', closeUploadModal);
            uModal.addEventListener('click', (e) => { if (e.target === uModal) closeUploadModal(); });

            uForm.addEventListener('submit', () => {
                uSubmit.disabled = true;
                uSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';
            });

            let dragDepth = 0;
            const hasFiles = (e) => e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');

            window.addEventListener('dragenter', (e) => {
                if (!hasFiles(e)) return;
                e.preventDefault();
                dragDepth++;
                dragOverlay.classList.add('open');
            });
            window.addEventListener('dragover', (e) => {
                if (!hasFiles(e)) return;
                e.preventDefault();
            });
            window.addEventListener('dragleave', (e) => {
                if (!hasFiles(e)) return;
                dragDepth = Math.max(0, dragDepth - 1);
                if (dragDepth === 0) dragOverlay.classList.remove('open');
            });
            window.addEventListener('drop', (e) => {
                if (!hasFiles(e)) return;
                e.preventDefault();
                dragDepth = 0;
                dragOverlay.classList.remove('open');
                const dropped = e.dataTransfer.files;
                if (dropped && dropped.length > 0) {
                    const dt = new DataTransfer();
                    dt.items.add(dropped[0]);
                    uInput.files = dt.files;
                    showUploadConfirm(dropped[0]);
                }
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllDropdowns();
                if (pModal.classList.contains('open')) closePreview();
                if (eModal.classList.contains('open')) closeEdit();
                if (cfModal && cfModal.classList.contains('open')) closeCreateFolder();
                if (uModal && uModal.classList.contains('open')) closeUploadModal();
            }
        });
    })();
    </script>
    <?php
}
