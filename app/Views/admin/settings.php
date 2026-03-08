<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz - Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars(chillquiz_asset_url('assets/css/settings.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="container">
    <p><a class="back" href="<?= htmlspecialchars(chillquiz_public_url('index.php?url=admin/index'), ENT_QUOTES, 'UTF-8') ?>">&larr; Torna alla regia</a></p>
    <h1>Admin settings</h1>

    <div class="card">
        <div class="setting-row">
            <div>
                <div class="setting-title">Visualizza tag modulo</div>
                <div class="setting-desc">Mostra/nasconde il badge debug con nome del modulo nelle view.</div>
            </div>
            <label class="switch">
                <input id="show-module-tags" type="checkbox" <?= !empty($settings['show_module_tags']) ? 'checked' : '' ?>>
                <span>Attivo</span>
            </label>
        </div>

        <div class="toolbar">
            <button id="btn-save" type="button">Salva impostazioni</button>
            <button id="btn-refresh" type="button" class="secondary">Ricarica</button>
        </div>

        <div id="settings-list" class="settings-list"></div>

        <div id="log" class="log"></div>
    </div>
</div>

<script>
window.SETTINGS_BOOTSTRAP = {
    apiBase: <?= json_encode(chillquiz_api_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    adminToken: 'SUPERSEGRETO123'
};
</script>
<script src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/settings.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
