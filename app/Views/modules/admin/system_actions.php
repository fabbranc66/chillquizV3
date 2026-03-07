<?php /*
 * FILE: app/Views/modules/admin/system_actions.php
 */ ?>
<div class="module-debug-tag">admin/system_actions.php</div>

<div class="row">
    <input id="sessione-nome" type="text" list="sessione-nome-options" placeholder="Cerca sessione (id o nome) / nuovo nome" value="Sessione <?= date('Y-m-d H:i') ?>">
    <datalist id="sessione-nome-options"></datalist>
    <input id="sessione-numero-domande" type="number" min="1" value="10" style="width:120px;">
    <select id="sessione-pool-tipo">
        <option value="misto">Misto</option>
        <option value="fisso">Argomento fisso</option>
        <option value="sarabanda">Sarabanda</option>
    </select>
    <select id="sessione-argomento-id" style="min-width:220px;">
        <option value="">Argomento (solo se fisso)</option>
    </select>
    <button id="btnNuova">Nuova Sessione</button>
    <button id="btnSetCorrente">Imposta Corrente</button>
    <button id="btnSalvaSessione">Salva Sessione</button>
    <select id="sessione-select" style="display:none;"></select>
</div>
