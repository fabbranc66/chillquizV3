<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz - Gestione Media</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars(chillquiz_asset_url('assets/css/media.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="container">
    <p><a class="back" href="<?= htmlspecialchars(chillquiz_public_url('index.php?url=admin/index'), ENT_QUOTES, 'UTF-8') ?>">&larr; Torna alla regia</a></p>
    <h1>Gestione media screen</h1>

    <div class="card">
        <form id="upload-form" class="upload-form">
            <input id="titolo" type="text" name="titolo" placeholder="Titolo media (opzionale)">
            <input id="immagine" type="file" name="immagine" accept="image/*,audio/*" required>
            <button type="submit">Carica</button>
        </form>
        <div class="toolbar">
            <button id="btn-refresh" type="button" class="secondary">Aggiorna lista</button>
            <button id="btn-disattiva" type="button" class="warn">Disattiva tutte</button>
        </div>
        <div id="log" class="log"></div>
    </div>

    <div id="media-list" class="media-grid card">
        <div class="empty">Nessun media caricato.</div>
    </div>
</div>

<script>
window.MEDIA_BOOTSTRAP = {
    basePublicUrl: <?= json_encode(chillquiz_public_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    apiBase: <?= json_encode(chillquiz_api_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    adminToken: 'SUPERSEGRETO123'
};
</script>
<script src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/media.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
