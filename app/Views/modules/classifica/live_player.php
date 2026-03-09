<?php /*
 * FILE: app/Views/modules/classifica/live_player.php
 * RUOLO: Schermata risultati player (solo risultato personale).
 * UTILIZZATO DA: app/Views/player/index.php
 * ELEMENTI USATI DA JS: #screen-risultati #risultato-personale
 */ ?>
<div class="module-debug-tag">classifica/live_player.php</div>
<!-- RISULTATI -->
<div id="screen-risultati" class="screen hidden">
    <div class="risultati-header">
        <div class="risultati-kicker">Esito round</div>
        <h2 id="screen-risultati-title">Risultati</h2>
    </div>

    <div class="risultati-stack">
        <div id="risultato-personale" class="risultato-box"></div>
        <div id="classifica" class="classifica-list"></div>
    </div>
</div>
