<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz - Regia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars(chillquiz_asset_url('assets/css/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="<?= !empty($showModuleTags) ? 'module-tags-on' : 'module-tags-off' ?>">

<div class="container">

<h2>Regia ChillQuiz</h2>

<div class="panel-toolbar">
    <button id="btnToggleDomandePanel" type="button">Domande</button>
    <button id="btnToggleClassifica" type="button">Classifica</button>
    <button id="btnToggleJoinRequests" type="button">Richieste</button>
    <button id="btnToggleLog" type="button">Log</button>
</div>

<div class="kahoot-panel" id="panel-info">
<?php require BASE_PATH . '/app/Views/modules/admin/info_bar.php'; ?>
</div>

<div class="kahoot-panel" id="panel-classifica">
<?php require BASE_PATH . '/app/Views/modules/classifica/live_admin.php'; ?>
</div>

<div class="kahoot-panel" id="panel-join">
<?php require BASE_PATH . '/app/Views/modules/admin/join_requests.php'; ?>
</div>

<div class="kahoot-panel" id="panel-phase">
<?php require BASE_PATH . '/app/Views/modules/admin/phase_actions.php'; ?>
</div>

<div class="kahoot-panel" id="panel-system">
<?php require BASE_PATH . '/app/Views/modules/admin/system_actions.php'; ?>
</div>

<div class="kahoot-panel" id="panel-question">
<?php require BASE_PATH . '/app/Views/modules/admin/question_action.php'; ?>
</div>

<div class="kahoot-panel" id="panel-log">
<?php require BASE_PATH . '/app/Views/modules/admin/log_panel.php'; ?>
</div>

</div>

<script>
window.ADMIN_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    nomeSessione: <?= json_encode((string)($nomeSessione ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    adminToken: "SUPERSEGRETO123",
    publicBaseUrl: <?= json_encode(chillquiz_public_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    apiBase: <?= json_encode(chillquiz_api_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/01_bootstrap.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/02_dom.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/03_utils.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/04_log.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/05_render.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/06_ui.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/07_actions.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/admin/08_main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>
