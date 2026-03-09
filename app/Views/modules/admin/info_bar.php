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
    </div>

    <div class="badge">
        Domanda: <strong id="domanda-numero">1</strong>
    </div>
    <div class="badge">
        Partecipanti: <strong id="partecipanti-numero">0</strong>
    </div>
    <div class="badge">
        <span class="timer-wrap">Timer: <span id="timer-indicator" class="timer-indicator"><span class="timer-indicator-inner"></span></span></span>
    </div>
    <input id="memeTextInput" type="text" class="badge badge-input" placeholder="Testo MEME assurdo">
    <button id="btnMemeToggle" type="button" class="badge badge-toggle">MEME OFF</button>
    <button id="btnImpostoreToggle" type="button" class="badge badge-toggle">IMPOSTORE OFF</button>
    <button id="btnDebugSessione" type="button" class="badge badge-toggle">DEBUG</button>
</div>

<div id="stato">Stato: ...</div>
<div id="conclusa">SESSIONE CONCLUSA</div>
<div id="debug-sessione-panel" class="debug-sessione-panel" style="display:none;">
    <div class="debug-sessione-head">Snapshot debug sessione</div>
    <pre id="debug-sessione-output" class="debug-sessione-output">Nessun dato</pre>
</div>
