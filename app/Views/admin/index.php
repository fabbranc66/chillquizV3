<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz V3 - Regia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/chillquizV3/public/assets/css/admin.css">
</head>
<body class="<?= !empty($showModuleTags) ? 'module-tags-on' : 'module-tags-off' ?>">

<div class="container">

<h2>🎛 ChillQuiz V3 – Regia</h2>

<?php require BASE_PATH . '/app/Views/modules/admin/info_bar.php'; ?>
<?php require BASE_PATH . '/app/Views/modules/admin/phase_actions.php'; ?>
<?php require BASE_PATH . '/app/Views/modules/admin/system_actions.php'; ?>
<?php require BASE_PATH . '/app/Views/modules/classifica/live_admin.php'; ?>
<?php require BASE_PATH . '/app/Views/modules/admin/join_requests.php'; ?>
<?php require BASE_PATH . '/app/Views/modules/admin/log_panel.php'; ?>

</div>

<script>
window.ADMIN_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    nomeSessione: <?= json_encode((string)($nomeSessione ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    adminToken: "SUPERSEGRETO123",
    apiBase: 'index.php?url=api'
};
</script>

<script defer src="/chillquizV3/public/assets/js/admin/01_bootstrap.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/02_dom.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/03_utils.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/04_log.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/05_render.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/06_ui.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/07_actions.js?v=<?= time() ?>"></script>
<script defer src="/chillquizV3/public/assets/js/admin/08_main.js?v=<?= time() ?>"></script>

</body>
</html>