<?php /*
 * FILE: app/Views/modules/admin/system_actions.php
 */ ?>
<div class="module-debug-tag">admin/system_actions.php</div>
<div class="row">
    <button id="btnRiavvia">Riavvia</button>
    <button id="btnSchermo">Attiva Schermo</button>
    <button id="btnMedia">Gestione Media</button>
    <button id="btnSettings">Settings</button>
</div><div class="row">
    <input id="sessione-nome" type="text" placeholder="Nome sessione" value="Sessione <?= date('Y-m-d H:i') ?>">
    <input id="sessione-numero-domande" type="number" min="1" value="10" style="width:120px;">
    <select id="sessione-pool-tipo">
        <option value="misto">Misto</option>
        <option value="fisso">Argomento fisso</option>
    </select>
    <input id="sessione-argomento-id" type="number" min="1" placeholder="ID argomento (se fisso)" style="width:170px;">
    <button id="btnNuova">Nuova Sessione</button>
    <button id="btnSetCorrente">Imposta Corrente</button>
    <select id="sessione-select" style="min-width:220px;"></select>
</div>
<div class="row">
    <button id="btnToggleDomandeSessione">Mostra/Nascondi domande selezionate</button>
</div>
<div id="domande-sessione-wrapper" style="display:none; margin:8px 0 12px 0; padding:10px; border:1px solid #2a2a2a; border-radius:8px; background:#111;">
    <div style="font-weight:700; margin-bottom:8px;">Domande selezionate sessione</div>
    <div id="domande-sessione-list">Nessuna domanda caricata</div>
</div>
