<?php /*
 * FILE: app/Views/modules/player/screen_puntata.php
 * RUOLO: Schermata puntata player.
 * UTILIZZATO DA: app/Views/player/index.php
 * ELEMENTI USATI DA JS: #screen-puntata #puntata #btn-punta
 */ ?>
<div class="module-debug-tag">player/screen_puntata.php</div>
<!-- PUNTATA -->
<div id="screen-puntata" class="screen hidden">
    <h2>Fai la tua puntata</h2>
    <div id="question-type-badge-player" class="question-type-badge-player hidden" aria-live="polite">
        <img id="question-type-badge-image-player" class="question-type-badge-image-player hidden" alt="Tipologia domanda">
    </div>
    <input type="number" id="puntata" placeholder="Importo">
    <div class="puntata-actions">
        <button id="btn-puntata-dec" type="button" class="btn-secondary">-250</button>
        <button id="btn-puntata-allin" type="button" class="btn-secondary">All-in</button>
        <button id="btn-puntata-inc" type="button" class="btn-secondary">+250</button>
    </div>
    <button id="btn-punta" class="btn-primary">Punta</button>
</div>
