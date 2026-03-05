<?php /*
 * FILE: app/Views/player/index.php
 * RUOLO: Layout pagina Player, compone moduli player/classifica e carica bootstrap JS player.
 * MODULI INCLUSI: modules/player/* e modules/classifica/live_player.php.
 * JS UTILIZZATO: public/assets/js/player.js
 */ ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/player.css?v=<?= time() ?>">
</head>
<body class="<?= !empty($showModuleTags) ? 'module-tags-on' : 'module-tags-off' ?>">

<div id="app">

    <?php require BASE_PATH . '/app/Views/modules/player/header_bar.php'; ?>

    <div class="content">

        <?php require BASE_PATH . '/app/Views/modules/player/screen_accesso.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/player/screen_lobby.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/player/screen_puntata.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/player/screen_domanda.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/classifica/live_player.php'; ?>
        <?php require BASE_PATH . '/app/Views/modules/player/screen_fine.php'; ?>

    </div>

</div>

<!-- BLOCCO LOGICO: BOOTSTRAP JS PLAYER -->
<script>
window.PLAYER_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    apiBase: '/chillquizV3/public/?url=api'
};
</script>

<!-- FILE JS: public/assets/js/player.js -->
<script defer src="/chillquizV3/public/assets/js/player/01_bootstrap.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/02_dom.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/03_utils.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/04_screens.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/05_join.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/06_polling.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/07_domanda.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/08_puntata.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/09_classifica.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/player/10_main.js?v=<?= time() ?>"></script></html>
