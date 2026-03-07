<?php /*
 * FILE: app/Views/modules/admin/phase_actions.php
 * RUOLO: Pulsanti transizioni fase quiz (puntata/domanda/risultati/prossima).
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #btnPuntata #btnDomanda #btnRisultati #btnProssima
 */ ?>
<div class="module-debug-tag">admin/phase_actions.php</div>
<!-- FASI -->
<div class="row">
    <button id="btnPuntata">Avvia Puntata</button>
    <button id="btnDomanda">Avvia Domanda</button>
    <button id="btnRisultati">Chiudi Domanda</button>
    <button id="btnProssima">Prossima Fase</button>
    <button id="btnRiavvia">Riavvia</button>
</div>
<div class="row">
    <button id="btnAudioPreview" style="display:none;">Anteprima audio</button>
    <button id="btnSchermo">Attiva Schermo</button>
    <button id="btnMedia">Gestione Media</button>
    <button id="btnSettings">Settings</button>
</div>

