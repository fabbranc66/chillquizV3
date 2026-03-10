<?php /*
 * FILE: app/Views/modules/player/screen_accesso.php
 * RUOLO: Schermata accesso player (join).
 * UTILIZZATO DA: app/Views/player/index.php
 * ELEMENTI USATI DA JS: #screen-accesso #player-name #btn-entra
 */ ?>
<div class="module-debug-tag">player/screen_accesso.php</div>
<!-- ACCESSO -->
<div id="screen-accesso" class="screen">
    <div class="player-access-welcome">
        <img
            src="<?= htmlspecialchars(chillquiz_asset_url('assets/img/player/welcome-banner.svg'), ENT_QUOTES, 'UTF-8') ?>"
            alt=""
            class="player-access-welcome-image"
            aria-hidden="true"
        >
        <div class="player-access-welcome-overlay">
            <div class="player-access-welcome-kicker">BENVENUTI SU</div>
            <img
                src="<?= htmlspecialchars(chillquiz_public_url($playerLogoPath !== '' ? $playerLogoPath : 'upload/image/logo-chillquiz-1773183162-5169.png'), ENT_QUOTES, 'UTF-8') ?>"
                alt="ChillQuiz"
                class="player-access-welcome-logo"
            >
        </div>
    </div>
    <input type="text" id="player-name" placeholder="Inserisci il tuo nome">
    <button id="btn-entra" class="btn-primary">Entra</button>
</div>
