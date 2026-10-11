<?php
// Cửa vào duy nhất của API: <DOCROOT>/api/index.php.
// Mặc định <APP_DIR> = /home/<user>/zika_app (02 §8.1). Đặt chỗ khác thì sửa dòng dưới (hoặc biến môi trường ZIKA_APP_DIR).
$appDir = getenv('ZIKA_APP_DIR') ?: __DIR__ . '/../../../../zika_app';
require $appDir . '/bootstrap.php';
Zika\Core\App::run();
