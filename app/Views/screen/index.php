<?php /*
 * FILE: app/Views/screen/index.php
 * RUOLO: Layout pagina Schermo, compone moduli screen/classifica e carica bootstrap JS screen.
 * MODULI INCLUSI: modules/screen/* e modules/classifica/live_screen.php.
 * JS UTILIZZATO: public/assets/js/screen.js
 */

$httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
$serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
$publicHost = $httpHost;

if ($publicHost === '') {
    $publicHost = $serverAddr;
}

$isLocalHost = stripos($publicHost, 'localhost') !== false || stripos($publicHost, '127.0.0.1') !== false;

if ($isLocalHost) {
    $candidates = [];

    if (function_exists('gethostbynamel')) {
        $hostIps = gethostbynamel(gethostname());
        if (is_array($hostIps)) {
            foreach ($hostIps as $ip) {
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $candidates[] = $ip;
                }
            }
        }
    }

    if (filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $candidates[] = $serverAddr;
    }

    $meshIp = null;

    foreach ($candidates as $ip) {
        if (strpos($ip, '192.168.') === 0) {
            $meshIp = $ip;
            break;
        }
    }

    if ($meshIp === null) {
        foreach ($candidates as $ip) {
            if (strpos($ip, '192.') === 0) {
                $meshIp = $ip;
                break;
            }
        }
    }

    if ($meshIp !== null) {
        $port = '';
        if (strpos($httpHost, ':') !== false) {
            $port = strstr($httpHost, ':');
            if ($port === false) {
                $port = '';
            }
        }
        $publicHost = $meshIp . $port;
    } else {
        $publicHost = preg_replace('/localhost|127\.0\.0\.1/i', $serverAddr, $publicHost);
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChillQuiz Screen</title>
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/screen.css?v=<?= time() ?>">
</head>
<body class="<?= !empty($showModuleTags) ? 'module-tags-on' : 'module-tags-off' ?>">

<div class="stage">
    <div class="stage-timer" id="stage-timer" aria-label="Timer domanda">
        <span class="stage-timer-indicator" id="stage-timer-indicator">
            <span class="stage-timer-indicator-inner"></span>
        </span>
        <span class="stage-timer-label" id="stage-timer-label">0s</span>
    </div>

    <?php require BASE_PATH . '/app/Views/modules/screen/stage_header.php'; ?>

    <?php require BASE_PATH . '/app/Views/modules/screen/session_qr.php'; ?>

    <main class="stage-main">
        <?php require BASE_PATH . '/app/Views/modules/screen/screen_domanda.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/classifica/live_screen.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/screen/screen_placeholder.php'; ?>
    </main>

    <?php require BASE_PATH . '/app/Views/modules/screen/stage_footer.php'; ?>
</div>

<script>
window.SCREEN_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    basePublicUrl: window.location.pathname.replace(/index\.php$/, ''),
    publicHost: <?= json_encode($publicHost, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script src="/chillquizV3/public/assets/js/screen.js?v=<?= time() ?>"></script>

</body>
</html>
