<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz V3 - Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/settings.css">
</head>
<body>
<div class="container">
    <p><a class="back" href="index.php?url=admin/index">← Torna alla regia</a></p>
    <h1>⚙️ Admin settings</h1>

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
    apiBase: `${window.location.pathname.replace(/index\.php$/, '')}index.php?url=api`,
    adminToken: 'SUPERSEGRETO123'
};
</script>
<script src="/chillquizV3/public/assets/js/settings.js"></script>
</body>
</html>
