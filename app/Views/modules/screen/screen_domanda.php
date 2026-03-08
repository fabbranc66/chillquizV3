<?php /*
 * FILE: app/Views/modules/screen/screen_domanda.php
 * RUOLO: Contenitore domanda/opzioni nello schermo pubblico.
 * UTILIZZATO DA: app/Views/screen/index.php
 * ELEMENTI USATI DA JS: #screen-domanda #domanda-media-screen #domanda-testo #opzioni
 */ ?>
<div class="module-debug-tag">screen/screen_domanda.php</div>
<div id="screen-domanda" class="hidden">
    <div id="domanda-media-screen" class="domanda-media-wrap hidden" aria-live="polite">
        <img id="domanda-media-image-screen" class="domanda-media-image hidden" alt="Media domanda">
        <audio id="domanda-media-audio-screen" class="domanda-media-audio hidden" preload="metadata"></audio>
        <div id="domanda-media-caption-screen" class="domanda-media-caption hidden"></div>
    </div>

    <div id="domanda-status-message-screen" class="domanda-status-message hidden"></div>
    <h2 id="domanda-testo"></h2>
    <div id="opzioni" class="grid-opzioni"></div>
</div>
