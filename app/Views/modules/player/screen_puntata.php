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
    <div class="puntata-placeholder" aria-hidden="true">
        <img
            class="puntata-placeholder-image"
            src="<?= htmlspecialchars(chillquiz_asset_url('assets/img/player/puntata-placeholder.svg'), ENT_QUOTES, 'UTF-8') ?>"
            alt="Fase puntata"
        >
        <div class="puntata-placeholder-copy">La domanda arriva tra poco</div>
    </div>
    <input type="number" id="puntata" placeholder="Importo">
    <div class="puntata-actions">
        <button id="btn-puntata-dec" type="button" class="btn-secondary">-250</button>
        <button id="btn-puntata-allin" type="button" class="btn-secondary">All-in</button>
        <button id="btn-puntata-inc" type="button" class="btn-secondary">+250</button>
    </div>
    <button id="btn-punta" class="btn-primary">Punta</button>
</div>
