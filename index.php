<?php
declare(strict_types=1);

$target = 'public/?url=admin';

header('Location: ' . $target, true, 302);
exit;
