<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz V3 - Gestione Media</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/media.css">
</head>
<body>
<div class="container">
    <p><a class="back" href="index.php?url=admin/index">← Torna alla regia</a></p>
    <h1>🖼 Gestione media screen</h1>

    <div class="card">
        <form id="upload-form" class="upload-form">
            <input id="titolo" type="text" name="titolo" placeholder="Titolo immagine (opzionale)">
            <input id="immagine" type="file" name="immagine" accept="image/*" required>
            <button type="submit">Carica</button>
        </form>
        <div class="toolbar">
            <button id="btn-refresh" type="button" class="secondary">Aggiorna lista</button>
            <button id="btn-disattiva" type="button" class="warn">Disattiva tutte</button>
        </div>
        <div id="log" class="log"></div>
    </div>

    <div id="media-list" class="media-grid card">
        <div class="empty">Nessuna immagine caricata.</div>
    </div>
</div>

<script>
window.MEDIA_BOOTSTRAP = {
    basePublicUrl: window.location.pathname.replace(/index\.php$/, ''),
    apiBase: `${window.location.pathname.replace(/index\.php$/, '')}index.php?url=api`,
    adminToken: 'SUPERSEGRETO123'
};
</script>
<script src="/chillquizV3/public/assets/js/media.js"></script>
</body>
</html>
