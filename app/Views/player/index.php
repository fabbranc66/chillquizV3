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
    <title>ChillQuiz - Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars(chillquiz_asset_url('assets/css/player.css'), ENT_QUOTES, 'UTF-8') ?>">
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

    <div id="player-ui-alert" class="player-ui-alert hidden" aria-live="assertive" aria-atomic="true">
        <div id="player-ui-alert-card" class="player-ui-alert-card player-ui-alert-info">
            <div id="player-ui-alert-title" class="player-ui-alert-title">Messaggio</div>
            <div id="player-ui-alert-message" class="player-ui-alert-message"></div>
            <button id="player-ui-alert-close" type="button" class="player-ui-alert-close">OK</button>
        </div>
    </div>

</div>

<script>
window.PLAYER_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    publicBaseUrl: <?= json_encode(chillquiz_public_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    apiBase: <?= json_encode(chillquiz_api_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/common/clock_sync.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/01_bootstrap.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/02_dom.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/03_utils.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/04_screens.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/05_join.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/06a_polling_render.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/06_polling.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/07_domanda.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/07a_domanda_render.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/08_puntata.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/09_classifica.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/09a_classifica_render.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/player/10_main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>
