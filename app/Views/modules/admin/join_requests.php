<?php /*
 * FILE: app/Views/modules/admin/join_requests.php
 * RUOLO: Contenitore richieste join pendenti (approva/rifiuta).
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #join-richieste e onclick gestisciJoin(requestId, action)
 */ ?>
<div class="module-debug-tag">admin/join_requests.php</div>
<!-- RICHIESTE JOIN -->
<div class="join-wrap">
    <div class="log-head">
        <div class="log-title">🙋 Richieste accesso (nomi già presenti)</div>
    </div>
    <div id="join-richieste" class="join-list">
        <div class="join-item">Nessuna richiesta pending</div>
    </div>
</div>
