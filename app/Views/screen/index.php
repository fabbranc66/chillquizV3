<?php /*
 * FILE: app/Views/screen/index.php
 * RUOLO: Layout pagina Schermo, compone moduli screen/classifica e carica bootstrap JS screen.
 * MODULI INCLUSI: modules/screen/* e modules/classifica/live_screen.php.
 * JS UTILIZZATO: public/assets/js/screen.js
 */ ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChillQuiz Screen</title>
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/screen.css">
</head>
<body class="<?= !empty($showModuleTags) ? 'module-tags-on' : 'module-tags-off' ?>">

<div class="stage">
    <?php require BASE_PATH . '/app/Views/modules/screen/stage_header.php'; ?>

    <?php require BASE_PATH . '/app/Views/modules/screen/session_qr.php'; ?>

    <main class="stage-main">
        <?php require BASE_PATH . '/app/Views/modules/screen/screen_domanda.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/classifica/live_screen.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/screen/screen_placeholder.php'; ?>
    </main>

    <?php require BASE_PATH . '/app/Views/modules/screen/stage_footer.php'; ?>
</div>

<!-- BLOCCO LOGICO: BOOTSTRAP JS SCREEN -->
<script>
window.SCREEN_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    basePublicUrl: window.location.pathname.replace(/index\.php$/, '')
};
</script>

<!-- FILE JS: public/assets/js/screen.js -->
<script src="/chillquizV3/public/assets/js/screen.js"></script>

</body>
</html>
