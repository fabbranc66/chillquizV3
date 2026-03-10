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
    <header class="page-hero">
        <div class="hero-copy">
            <h1>Impostazioni sistema</h1>
            <p>Organizza tempi, punteggi e opzioni di debug della regia.</p>
        </div>
        <button id="btn-save" type="button">Salva impostazioni</button>
        <button id="btn-refresh" type="button" class="secondary">Ricarica</button>
    </header>

    <section class="settings-grid">
        <div class="card card-highlight">
            <div class="card-title">Debug interfaccia</div>
            <div class="setting-row setting-row-standalone">
                <div>
                    <div class="setting-title">Visualizza tag modulo</div>
                    <div class="setting-desc">Mostra o nasconde il badge debug con nome del modulo nelle view.</div>
                </div>
                <label class="switch">
                    <input id="show-module-tags" type="checkbox" <?= !empty($settings['show_module_tags']) ? 'checked' : '' ?>>
                    <span>Attivo</span>
                </label>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Logo admin</div>
            <div class="logo-settings-row">
                <div class="logo-preview-wrap">
                    <img id="logo-preview" class="logo-preview" src="<?= htmlspecialchars(chillquiz_public_url(ltrim((string) ($settings['configurazioni_sistema']['logo'] ?? 'upload/image/logo-chillquiz-1773183162-5169.png'), '/')), ENT_QUOTES, 'UTF-8') ?>" alt="Logo admin">
                </div>
                <div class="logo-upload-controls">
                    <div class="setting-title">Anteprima logo</div>
                    <div class="setting-desc">Carica un nuovo logo admin e aggiorna automaticamente il parametro <code>logo</code>.</div>
                    <input id="logo-file" type="file" accept="image/*">
                    <button id="btn-upload-logo" type="button">Carica logo</button>
                </div>
            </div>
        </div>

        <div id="settings-list" class="settings-list"></div>
    </section>

    <div id="log" class="log"></div>
</div>

<script>
window.SETTINGS_BOOTSTRAP = {
    apiBase: <?= json_encode(chillquiz_api_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    publicBaseUrl: <?= json_encode(chillquiz_public_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    currentLogoPath: <?= json_encode((string) (($settings['configurazioni_sistema']['logo'] ?? 'upload/image/logo-chillquiz-1773183162-5169.png')), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/settings.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
