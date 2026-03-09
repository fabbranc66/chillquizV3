<?php /*
 * FILE: app/Views/modules/classifica/live_screen.php
 * RUOLO: Contenitore classifica risultati su schermo pubblico.
 * UTILIZZATO DA: app/Views/screen/index.php
 * ELEMENTI USATI DA JS: #screen-risultati #scoreboard-list
 */ ?>
<div class="module-debug-tag">classifica/live_screen.php</div>
<div id="screen-risultati" class="scoreboard-wrap hidden">
    <h2 class="scoreboard-title">🏆 Classifica</h2>
    <div id="meme-alert-screen" class="meme-alert hidden" aria-live="polite">
        <div class="meme-alert-card">
            <div class="meme-alert-title">Momento MEME</div>
            <div id="meme-alert-message" class="meme-alert-message"></div>
        </div>
    </div>
    <div id="scoreboard-list" class="scoreboard-list"></div>
</div>
