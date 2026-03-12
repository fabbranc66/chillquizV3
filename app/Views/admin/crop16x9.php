<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz - Crop 16:9</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars(chillquiz_asset_url('assets/css/crop16x9.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="crop-page">
    <p>
        <a class="back-link" href="<?= htmlspecialchars(chillquiz_public_url('index.php?url=admin/index'), ENT_QUOTES, 'UTF-8') ?>">&larr; Torna alla regia</a>
        <a class="back-link" href="<?= htmlspecialchars(chillquiz_public_url('index.php?url=admin/media'), ENT_QUOTES, 'UTF-8') ?>">Gestione media</a>
    </p>

    <h1>Tool Crop 16:9</h1>
    <p class="subtitle">Scegli un'immagine server, posiziona il riquadro 16:9 e salva nello stesso percorso o in copia.</p>

    <div class="card controls">
        <label class="field">
            <span>Sorgente immagine</span>
            <select id="source-path"></select>
        </label>

        <div class="actions">
            <button id="btn-refresh" type="button" class="secondary">Aggiorna elenco</button>
            <button id="btn-load" type="button">Carica immagine</button>
            <button id="btn-reset-rect" type="button" class="secondary">Reset riquadro</button>
        </div>

        <label class="field mode-field">
            <span>Modalita salvataggio</span>
            <select id="save-mode">
                <option value="overwrite">Sovrascrivi stesso file</option>
                <option value="copy">Salva copia</option>
            </select>
        </label>

        <label class="field" id="copy-suffix-wrap" style="display:none;">
            <span>Suffisso copia</span>
            <input id="copy-suffix" type="text" value="-169" maxlength="24">
        </label>

        <div class="actions">
            <button id="btn-save" type="button" class="warn">Salva crop 16:9</button>
        </div>

        <div id="log" class="log"></div>
    </div>

    <div class="card stage-card">
        <div class="stage-wrap">
            <div id="stage" class="stage">
                <img id="stage-image" alt="Anteprima sorgente">
                <div id="crop-rect" class="crop-rect" style="display:none;">
                    <span class="crop-label">16:9</span>
                    <span id="crop-handle" class="crop-handle"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card preview-card">
        <div class="preview-head">Anteprima crop</div>
        <canvas id="preview-canvas" width="1280" height="720"></canvas>
    </div>
</div>

<script>
window.CROP169_BOOTSTRAP = {
    basePublicUrl: <?= json_encode(chillquiz_public_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    apiBase: <?= json_encode(chillquiz_api_base_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= htmlspecialchars(chillquiz_asset_url('assets/js/crop16x9.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
