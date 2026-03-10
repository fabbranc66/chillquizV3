<?php /*
 * FILE: app/Views/modules/player/header_bar.php
 * RUOLO: Header player (nome + timer + punti giocatore).
 * UTILIZZATO DA: app/Views/player/index.php
 * ELEMENTI USATI DA JS: #player-display-name #capitale-value #player-header-timer-indicator #player-header-timer-label
 */ ?>
<div class="module-debug-tag">player/header_bar.php</div>
<!-- HEADER -->
<div class="header">
    <div class="player-header-logo-wrap" aria-hidden="true">
        <img
            src="<?= htmlspecialchars(chillquiz_public_url($playerLogoPath !== '' ? $playerLogoPath : 'upload/image/logo-chillquiz-1773183162-5169.png'), ENT_QUOTES, 'UTF-8') ?>"
            alt="ChillQuiz"
            class="player-header-logo"
        >
    </div>

    <div id="player-display-name">Player</div>

    <div class="header-timer-wrap" id="player-header-timer-wrap" aria-label="Timer domanda">
        <span id="player-header-timer-indicator" class="player-timer-indicator">
            <span class="player-timer-indicator-inner"></span>
        </span>
        <span id="player-header-timer-label" class="player-timer-label">0s</span>
    </div>

    <div class="capitale" title="Punti giocatore">
        &#9733; <span id="capitale-value">0</span>
    </div>

</div>
