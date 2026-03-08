<?php /*
 * FILE: app/Views/modules/player/screen_domanda.php
 * RUOLO: Schermata domanda player con opzioni risposta.
 * UTILIZZATO DA: app/Views/player/index.php
 * ELEMENTI USATI DA JS: #screen-domanda #domanda-media-player #domanda-testo #opzioni
 */ ?>
<div class="module-debug-tag">player/screen_domanda.php</div>
<!-- DOMANDA -->
<div id="screen-domanda" class="screen hidden">
    <div id="domanda-media-player" class="domanda-media-wrap hidden" aria-live="polite">
        <img id="domanda-media-image-player" class="domanda-media-image hidden" alt="Media domanda">
        <div id="domanda-media-caption-player" class="domanda-media-caption hidden"></div>
    </div>
    <div id="domanda-status-message-player" class="domanda-status-message hidden"></div>
    <h2 id="domanda-testo"></h2>
    <div id="opzioni" class="grid-opzioni"></div>
</div>
