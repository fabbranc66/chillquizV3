<?php /*
 * FILE: app/Views/modules/admin/system_actions.php
 */ ?>
<div class="module-debug-tag">admin/system_actions.php</div>
<div class="row">
    <button id="btnRiavvia">Riavvia</button>
    <button id="btnAudioPreview" style="display:none;">Anteprima audio</button>
    <button id="btnSchermo">Attiva Schermo</button>
    <button id="btnMedia">Gestione Media</button>
    <button id="btnSettings">Settings</button>
</div><div class="row">
    <input id="sessione-nome" type="text" placeholder="Nome sessione" value="Sessione <?= date('Y-m-d H:i') ?>">
    <input id="sessione-numero-domande" type="number" min="1" value="10" style="width:120px;">
    <select id="sessione-pool-tipo">
        <option value="misto">Misto</option>
        <option value="fisso">Argomento fisso</option>
    </select>
    <input id="sessione-argomento-id" type="number" min="1" placeholder="ID argomento (se fisso)" style="width:170px;">
    <button id="btnNuova">Nuova Sessione</button>
    <button id="btnSetCorrente">Imposta Corrente</button>
    <select id="sessione-select" style="min-width:220px;"></select>
</div>
<div class="row">
    <button id="btnToggleDomandeSessione">Mostra/Nascondi domande selezionate</button>
    <button id="btnToggleDomandaEditor">Modifica Domanda Corrente</button>
</div>
<div id="domande-sessione-wrapper" style="display:none; margin:8px 0 12px 0; padding:10px; border:1px solid #2a2a2a; border-radius:8px; background:#111; text-align:left;">
    <div style="font-weight:700; margin-bottom:8px;">Domande selezionate sessione</div>
    <div id="domande-sessione-list">Nessuna domanda caricata</div>
</div>
<div id="domanda-corrente-meta" style="margin:8px 0 12px 0; padding:10px; border:1px solid #2a2a2a; border-radius:8px; background:#111; text-align:left;">
    <div style="font-weight:700; margin-bottom:8px;">Domanda corrente (metadati)</div>
    <div id="domanda-corrente-meta-body">Nessuna domanda attiva</div>
</div>
<div id="domanda-editor-wrapper" style="display:none; margin:8px 0 12px 0; padding:12px; border:1px solid #2a2a2a; border-radius:10px; background:#101010; text-align:left;">
    <div style="font-weight:700; margin-bottom:10px;">Editor domanda corrente</div>
    <div id="domanda-editor-selected-info" style="margin:0 0 10px 0; padding:8px 10px; border:1px solid #2a2a2a; border-radius:8px; background:#151515; font-size:13px; opacity:.95;">
        Nessuna domanda selezionata
    </div>

    <input id="domanda-editor-id" type="hidden" value="0">

    <div class="row" style="margin-top:8px; padding:12px;">
        <label for="domanda-editor-tipo" style="display:block; margin-bottom:6px;">Tipologia domanda</label>
        <select id="domanda-editor-tipo" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
            <option value="CLASSIC">CLASSIC</option>
            <option value="MEDIA">MEDIA</option>
            <option value="SARABANDA">SARABANDA</option>
            <option value="IMPOSTORE">IMPOSTORE</option>
            <option value="MEME">MEME</option>
            <option value="MAJORITY">MAJORITY</option>
            <option value="RANDOM_BONUS">RANDOM_BONUS</option>
            <option value="BOMB">BOMB</option>
            <option value="CHAOS">CHAOS</option>
            <option value="AUDIO_PARTY">AUDIO_PARTY</option>
            <option value="IMAGE_PARTY">IMAGE_PARTY</option>
        </select>
    </div>

    <div class="row" id="domanda-editor-row-fase" style="margin-top:8px; padding:12px; display:none;">
        <label for="domanda-editor-fase" style="display:block; margin-bottom:6px;">Fase domanda</label>
        <select id="domanda-editor-fase" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
            <option value="domanda">domanda</option>
            <option value="intro">intro</option>
        </select>
    </div>

    <div class="row" id="domanda-editor-row-party" style="margin-top:8px; padding:12px;">
        <label for="domanda-editor-modalita-party" style="display:block; margin-bottom:6px;">Modalita party (opzionale)</label>
        <input id="domanda-editor-modalita-party" type="text" placeholder="es. AUDIO_FRAMMENTATO / PIXELATE / ecc" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
    </div>

    <div id="domanda-editor-media-wrap" class="row" style="margin-top:8px; padding:12px; display:none;">
        <div style="font-weight:700; margin-bottom:8px;">Media domanda</div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin-bottom:10px;">
            <div style="flex:1 1 280px;">
                <label for="domanda-editor-upload-title" style="display:block; margin:6px 0;">Nome media riconoscibile</label>
                <input id="domanda-editor-upload-title" type="text" placeholder="es. Intro Q5 artista misterioso" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
            </div>
            <div style="flex:1 1 280px;">
                <label for="domanda-editor-upload-file" style="display:block; margin:6px 0;">File media (immagine/audio)</label>
                <input id="domanda-editor-upload-file" type="file" accept="image/*,audio/*" style="width:100%; padding:8px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
            </div>
            <div style="flex:0 0 auto;">
                <button id="btnUploadDomandaMedia" type="button">Carica media domanda</button>
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin-bottom:10px;">
            <div style="flex:1 1 300px;">
                <label for="domanda-editor-media-image-select" style="display:block; margin:6px 0;">Libreria media (immagine)</label>
                <select id="domanda-editor-media-image-select" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
                    <option value="">Seleziona media immagine...</option>
                </select>
            </div>
            <div style="flex:1 1 300px;">
                <label for="domanda-editor-media-audio-select" style="display:block; margin:6px 0;">Libreria media (audio)</label>
                <select id="domanda-editor-media-audio-select" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
                    <option value="">Seleziona media audio...</option>
                </select>
            </div>
            <div style="flex:0 0 auto;">
                <button id="btnRicaricaMediaCatalog" type="button">Ricarica catalogo media</button>
            </div>
        </div>

        <label for="domanda-editor-media-image" style="display:block; margin:6px 0;">Path immagine</label>
        <input id="domanda-editor-media-image" type="text" placeholder="/upload/image/xxx.jpg" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">

        <label for="domanda-editor-media-audio" style="display:block; margin:10px 0 6px;">Path audio</label>
        <input id="domanda-editor-media-audio" type="text" placeholder="/upload/audio/xxx.mp3" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">

        <label for="domanda-editor-media-preview" style="display:block; margin:10px 0 6px;">Preview audio (secondi)</label>
        <input id="domanda-editor-media-preview" type="number" min="0" placeholder="0 = nessun limite" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">

        <label for="domanda-editor-media-caption" style="display:block; margin:10px 0 6px;">Caption media</label>
        <input id="domanda-editor-media-caption" type="text" placeholder="Testo indizio/caption" style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b;">
    </div>

    <div class="row" style="margin-top:8px; padding:12px;">
        <label for="domanda-editor-config-json" style="display:block; margin-bottom:6px;">Config JSON (opzionale)</label>
        <textarea id="domanda-editor-config-json" rows="5" placeholder='{"chiave":"valore"}' style="width:100%; padding:10px; border-radius:8px; background:#1a1a1a; color:#fff; border:1px solid #2b2b2b; resize:vertical;"></textarea>
    </div>

    <div class="row" style="margin-top:8px; padding:12px; text-align:right;">
        <button id="btnCaricaDomandaEditor">Ricarica dati domanda</button>
        <button id="btnSalvaDomandaEditor">Salva modifica domanda</button>
    </div>
</div>
