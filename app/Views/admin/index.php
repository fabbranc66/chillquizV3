<?php /*
 * FILE: app/Views/admin/index.php
 * RUOLO: Layout pagina Regia (struttura HTML+CSS), compone moduli admin e carica bootstrap/js.
 * MODULI INCLUSI: modules/admin/* e modules/classifica/live_admin.php.
 * JS UTILIZZATO: public/assets/js/admin.js
 */ ?>
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

<!-- BLOCCO LOGICO: BOOTSTRAP JS ADMIN -->
<script>
window.ADMIN_BOOTSTRAP = {
    sessioneId: <?= (int)($sessioneId ?? 0) ?>,
    adminToken: "SUPERSEGRETO123",
    apiBase: 'index.php?url=api'
};
</script>

<!-- FILE JS: public/assets/js/admin.js -->
<script src="/chillquizV3/public/assets/js/admin.js"></script>

</body>
</html>
