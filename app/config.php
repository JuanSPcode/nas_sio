<?php
declare(strict_types=1);

define('DB_PATH', '/srv/nas/data/nas.sqlite');
define('STORAGE_DIR', '/srv/nas/storage');
define('MAX_UPLOAD_BYTES', 100 * 1024 * 1024); // 100 MB por archivo
define('DEFAULT_QUOTA_BYTES', 1024 * 1024 * 1024); // 1 GB por usuario
define('ALLOW_PUBLIC_REGISTER', true);
