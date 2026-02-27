<?php /*
 * FILE: app/Views/modules/classifica/debug_live_player.php
 * RUOLO: Modulo debug player con tabella formula premio per domanda corrente.
 * UTILIZZATO DA: app/Views/player/index.php
 * VISIBILITA: solo con setting "Visualizza tag modulo" attivo.
 * ELEMENTI USATI DA JS: #debug-live-player-module #debug-live-player-body
 */ ?>
<div class="module-debug-tag">classifica/debug_live_player.php</div>
<div id="debug-live-player-module" class="debug-live-player hidden">
    <h3 class="section-title">🐞 Debug live player (valori reali calcolo)</h3>
    <div class="live-wrap">
        <table class="live-table live-table-detailed debug-live-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Giocatore</th>
                    <th>Puntata</th>
                    <th>Coeff. difficoltà</th>
                    <th>Bonus primo</th>
                    <th>Coeff. velocità</th>
                </tr>
            </thead>
            <tbody id="debug-live-player-body">
                <tr><td colspan="6">Debug formula non disponibile</td></tr>
            </tbody>
        </table>
    </div>
</div>
