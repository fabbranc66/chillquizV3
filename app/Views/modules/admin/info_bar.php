<?php /*
 * FILE: app/Views/modules/admin/info_bar.php
 * RUOLO: Header informativo regia (sessione, domanda, partecipanti, timer, stato).
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #sessione-id #domanda-numero #partecipanti-numero #timer-indicator #stato #conclusa
 */ ?>
<div class="module-debug-tag">admin/info_bar.php</div>
<div class="info-bar">
    <div class="badge">
        Sessione ID: <strong id="sessione-id"><?= (int)($sessioneId ?? 0) ?></strong>
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
</div>

<div id="stato">Stato: ...</div>
<div id="conclusa">🎉 SESSIONE CONCLUSA</div>
