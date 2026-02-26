<?php /*
 * FILE: app/Views/modules/admin/log_panel.php
 * RUOLO: Log operazioni regia con pulsante reset log.
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #log #btnClearLog
 */ ?>
<div class="module-debug-tag">admin/log_panel.php</div>
<!-- LOG -->
<div class="log-wrap">
    <div class="log-head">
        <div class="log-title">📜 Log Regia</div>
        <div class="log-actions">
            <button id="btnClearLog" type="button">Pulisci</button>
        </div>
    </div>
    <div id="log" class="log">
        <!-- entries -->
    </div>
</div>
