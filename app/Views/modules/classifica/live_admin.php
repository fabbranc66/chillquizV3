<?php /*
 * FILE: app/Views/modules/classifica/live_admin.php
 * RUOLO: Tabella classifica live in area admin.
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #classifica-live
 */ ?>
<div class="module-debug-tag">classifica/live_admin.php</div>
<!-- CLASSIFICA LIVE -->
<div class="live-wrap">
    <div class="log-head">
        <div class="log-title">📊 Classifica live (puntata + esito risposta)</div>
    </div>
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
            </tr>
        </thead>
        <tbody id="classifica-live">
            <tr><td colspan="7">Nessun dato</td></tr>
        </tbody>
    </table>
</div>
