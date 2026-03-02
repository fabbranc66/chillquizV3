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

<script src="/chillquizV3/public/assets/js/admin.js"></script>

</body>
</html>