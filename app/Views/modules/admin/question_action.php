<?php /*
 * FILE: app/Views/modules/admin/question_action.php
 * RUOLO: Pannello domande sessione con elenco e editor domanda.
 * UTILIZZATO DA: app/Views/admin/index.php
 */ ?>
<div class="module-debug-tag">admin/question_action.php</div>

<div id="domande-sessione-wrapper" class="qa-wrap qa-card">
    <div class="qa-toolbar">
        <div class="qa-title">Domande selezionate sessione</div>
        <div class="qa-toolbar-actions">
            <button id="btnSearchSessionImages" type="button">Ricerca immagini sessione</button>
        </div>
    </div>
    <div id="domande-sessione-list" class="qa-list">Nessuna domanda caricata</div>
    <div id="session-image-search-report" class="qa-search-report" style="display:none;">
        <div class="qa-subtitle">Suggerimenti immagini sessione corrente</div>
        <div id="session-image-search-summary" class="qa-search-summary">Nessuna analisi eseguita</div>
        <div id="session-image-search-list" class="qa-search-list"></div>
    </div>
</div>

<div id="domanda-editor-wrapper" class="qa-editor qa-card" style="display:none;">
    <div class="qa-title">Editor domanda selezionata</div>

    <div id="domanda-editor-selected-info" class="qa-selected-info">
        Nessuna domanda selezionata
    </div>

    <input id="domanda-editor-id" type="hidden" value="0">

    <div class="qa-section">
        <label for="domanda-editor-tipo" class="qa-label">Tipologia domanda</label>
        <select id="domanda-editor-tipo" class="qa-input qa-full">
            <option value="CLASSIC">CLASSIC</option>
            <option value="MEDIA">MEDIA</option>
            <option value="SARABANDA">SARABANDA</option>
            <option value="MAJORITY">MAJORITY</option>
            <option value="RANDOM_BONUS">RANDOM_BONUS</option>
            <option value="BOMB">BOMB</option>
            <option value="CHAOS">CHAOS</option>
            <option value="AUDIO_PARTY">AUDIO_PARTY</option>
            <option value="IMAGE_PARTY">IMAGE_PARTY</option>
        </select>
    </div>

    <div class="qa-section" id="domanda-editor-row-fase" style="display:none;">
        <label for="domanda-editor-fase" class="qa-label">Fase domanda</label>
        <select id="domanda-editor-fase" class="qa-input qa-full">
            <option value="domanda">domanda</option>
            <option value="intro">intro</option>
        </select>
    </div>

    <div class="qa-section" id="domanda-editor-row-party">
        <label for="domanda-editor-modalita-party" class="qa-label">Modalita party (opzionale)</label>
        <input id="domanda-editor-modalita-party" type="text" class="qa-input qa-full" placeholder="es. AUDIO_FRAMMENTATO / PIXELATE / ecc">
    </div>

    <div id="domanda-editor-media-wrap" class="qa-section" style="display:none;">
        <div class="qa-subtitle">Media domanda</div>

        <div class="qa-grid">
            <div class="qa-col">
                <label for="domanda-editor-upload-title" class="qa-label">Nome media riconoscibile</label>
                <input id="domanda-editor-upload-title" type="text" class="qa-input qa-full" placeholder="es. Intro Q5 artista misterioso">
            </div>
            <div class="qa-col">
                <label for="domanda-editor-upload-file" class="qa-label">File media (immagine/audio)</label>
                <input id="domanda-editor-upload-file" type="file" class="qa-input qa-full" accept="image/*,audio/*">
            </div>
            <div class="qa-col-auto">
                <button id="btnUploadDomandaMedia" type="button">Carica media domanda</button>
            </div>
        </div>

        <div class="qa-grid">
            <div class="qa-col">
                <label for="domanda-editor-media-image-select" class="qa-label">Libreria media (immagine)</label>
                <select id="domanda-editor-media-image-select" class="qa-input qa-full">
                    <option value="">Seleziona media immagine...</option>
                </select>
            </div>
            <div class="qa-col">
                <label for="domanda-editor-media-audio-select" class="qa-label">Libreria media (audio)</label>
                <select id="domanda-editor-media-audio-select" class="qa-input qa-full">
                    <option value="">Seleziona media audio...</option>
                </select>
            </div>
            <div class="qa-col-auto">
                <button id="btnRicaricaMediaCatalog" type="button">Ricarica catalogo media</button>
            </div>
        </div>

        <label for="domanda-editor-media-image" class="qa-label">Path immagine</label>
        <input id="domanda-editor-media-image" type="text" class="qa-input qa-full" placeholder="/upload/image/xxx.jpg">
        <div class="qa-preview-block">
            <div class="qa-preview-title">Anteprima immagine</div>
            <div class="qa-image-preview-wrap">
                <img id="domanda-editor-image-preview" class="qa-image-preview" alt="Anteprima immagine domanda" style="display:none;">
                <div id="domanda-editor-image-preview-empty" class="qa-preview-empty">Nessuna immagine selezionata</div>
            </div>
        </div>

        <label for="domanda-editor-media-audio" class="qa-label">Path audio</label>
        <input id="domanda-editor-media-audio" type="text" class="qa-input qa-full" placeholder="/upload/audio/xxx.mp3">
        <div class="qa-preview-block">
            <div class="qa-preview-title">Anteprima audio</div>
            <audio id="domanda-editor-audio-preview" class="qa-audio-preview" controls style="display:none;"></audio>
            <div id="domanda-editor-audio-preview-empty" class="qa-preview-empty">Nessun audio selezionato</div>
        </div>

        <label for="domanda-editor-media-preview" class="qa-label">Preview audio (secondi)</label>
        <input id="domanda-editor-media-preview" type="number" min="0" value="5" class="qa-input qa-full" placeholder="0 = nessun limite">

        <label for="domanda-editor-media-caption" class="qa-label">Caption media</label>
        <input id="domanda-editor-media-caption" type="text" class="qa-input qa-full" placeholder="Testo indizio/caption">
    </div>

    <div class="qa-section qa-actions">
        <button id="btnCaricaDomandaEditor">Ricarica dati domanda</button>
        <button id="btnSalvaDomandaEditor">Salva modifica domanda</button>
    </div>
</div>
