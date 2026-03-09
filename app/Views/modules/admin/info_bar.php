<?php /*
 * FILE: app/Views/modules/admin/info_bar.php
 * RUOLO: Header informativo regia (sessione, domanda, partecipanti, timer, stato).
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #sessione-nome-display #sessione-id #domanda-numero #partecipanti-numero #timer-indicator #stato #conclusa
 */ ?>
<div class="module-debug-tag">admin/info_bar.php</div>
<div class="info-bar info-bar-kahoot">
    <div class="sessione-hero">
        <span class="sessione-label">SESSIONE</span>
        <strong id="sessione-nome-display" class="sessione-nome"><?= htmlspecialchars((string) ($nomeSessione ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="sessione-meta">ID <strong id="sessione-id"><?= (int) ($sessioneId ?? 0) ?></strong></span>
        <div id="sessione-domanda-info" class="sessione-domanda-info">#1 · - · -</div>
    </div>

    <div class="badge badge-participants">
        Partecipanti: <strong id="partecipanti-numero">0</strong>
    </div>
    <div class="badge">
        <span class="timer-wrap">Timer: <span id="timer-indicator" class="timer-indicator"><span class="timer-indicator-inner"></span></span></span>
    </div>

    <div class="effect-group effect-group-options">
        <span class="effect-group-label">Effetti opzioni</span>
        <div class="effect-group-controls effect-group-controls-left">
            <div class="effect-inline-pair">
                <button id="btnMemeToggle" type="button" class="badge badge-toggle">MEME OFF</button>
                <input id="memeTextInput" type="text" class="badge badge-input" placeholder="Testo MEME assurdo">
            </div>
            <button id="btnImpostoreToggle" type="button" class="badge badge-toggle">IMPOSTORE OFF</button>
        </div>
    </div>

    <div class="effect-group effect-group-image">
        <span class="effect-group-label">Effetti immagine</span>
        <button id="btnFadeToggle" type="button" class="badge badge-toggle">FADE OFF</button>
        <button id="btnImagePartyToggle" type="button" class="badge badge-toggle">PIXELATE OFF</button>
    </div>

    <div class="effect-group effect-group-audio">
        <span class="effect-group-label">Effetti audio</span>
        <div id="sarabandaAudioLed" class="effect-led is-off">
            <span class="effect-led-dot"></span>
            <span class="effect-led-label">SARABANDA</span>
        </div>
        <button id="btnSarabandaReverseToggle" type="button" class="badge badge-toggle">REVERSE OFF</button>
    </div>

    <button id="btnDebugSessione" type="button" class="badge badge-toggle">DEBUG</button>
</div>

<div id="stato">Stato: ...</div>
<div id="conclusa">SESSIONE CONCLUSA</div>
<div id="debug-sessione-panel" class="debug-sessione-panel" style="display:none;">
    <div class="debug-sessione-head">Snapshot debug sessione</div>
    <pre id="debug-sessione-output" class="debug-sessione-output">Nessun dato</pre>
</div>
