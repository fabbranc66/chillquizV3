<?php /*
 * FILE: app/Views/modules/classifica/live_admin.php
 * RUOLO: Tabella classifica live in area admin.
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #classifica-live
 */ ?>
<div class="module-debug-tag">classifica/live_admin.php</div>
<!-- CLASSIFICA LIVE -->
<div class="live-wrap">
    <div class="live-head">
        <div class="live-kicker">LIVE SCORE</div>
        <div class="live-title">Classifica live</div>
        <div class="live-subtitle">Puntata, esito risposta e andamento in tempo reale</div>
    </div>
    <div class="live-table-scroll">
        <table class="live-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Giocatore</th>
                    <th>Capitale</th>
                    <th>Puntata</th>
                    <th>Esito</th>
                    <th>Tempo risposta (s)</th>
                    <th>Vincita domanda</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody id="classifica-live">
                <tr><td colspan="8">Nessun dato</td></tr>
            </tbody>
        </table>
    </div>
</div>
