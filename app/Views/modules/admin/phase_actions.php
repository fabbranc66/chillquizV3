<?php /*
 * FILE: app/Views/modules/admin/phase_actions.php
 * RUOLO: Pulsanti transizioni fase quiz (puntata/domanda/risultati/prossima).
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #btnPuntata #btnDomanda #btnRisultati #btnProssima
 */ ?>
<div class="module-debug-tag">admin/phase_actions.php</div>
<div class="phase-actions-layout">
    <div class="phase-actions-block">
        <div class="phase-actions-title">Controllo partita</div>
        <div class="phase-actions-grid">
            <button id="btnPuntata">Avvia Puntata</button>
            <button id="btnDomanda">Avvia Domanda</button>
            <button id="btnRisultati">Chiudi Domanda</button>
            <button id="btnProssima">Prossima Fase</button>
        </div>
    </div>
</div>
