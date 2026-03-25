<?php /*
 * FILE: app/Views/modules/admin/system_actions.php
 */ ?>
<div class="module-debug-tag">admin/system_actions.php</div>

<div class="system-actions-layout">
    <div class="system-actions-form">
        <div class="system-actions-head">
            <div class="system-actions-title">Gestione sessione</div>
            <div class="system-actions-subtitle">Crea, aggiorna e imposta la sessione corrente</div>
        </div>

        <div class="system-actions-grid system-actions-grid-editor">
            <label class="system-field system-field-name">
                <span class="system-field-label">Nome sessione</span>
                <input id="sessione-nome" type="text" list="sessione-nome-options" placeholder="Cerca sessione (id o nome) / nuovo nome" value="Sessione <?= date('Y-m-d H:i') ?>">
            </label>

            <label class="system-field system-field-count">
                <span class="system-field-label">Domande</span>
                <input id="sessione-numero-domande" type="number" min="1" value="10">
            </label>

            <label class="system-field system-field-pool">
                <span class="system-field-label">Pool</span>
                <select id="sessione-pool-tipo">
                    <option value="misto">Misto</option>
                    <option value="fisso">Argomento fisso</option>
                    <option value="sarabanda">Sarabanda</option>
                </select>
            </label>

            <label class="system-field system-field-selection">
                <span class="system-field-label">Selezione</span>
                <select id="sessione-selezione-tipo">
                    <option value="random">Random</option>
                    <option value="manuale">Manuale</option>
                </select>
            </label>

            <label class="system-field system-field-topic">
                <span class="system-field-label">Argomento</span>
                <select id="sessione-argomento-id">
                    <option value="">Argomento (solo se fisso)</option>
                </select>
            </label>

            <label class="system-field system-field-topic-limit">
                <span class="system-field-label">Max per argomento</span>
                <input id="sessione-max-per-argomento" type="number" min="1" placeholder="Solo per misto random">
            </label>
        </div>

        <div class="system-actions-buttons system-actions-buttons-primary">
            <button id="btnNuova">Nuova Sessione</button>
            <button id="btnSetCorrente">Imposta Corrente</button>
            <button id="btnSalvaSessione">Salva Sessione</button>
            <button id="btnRiavvia">Riavvia Sessione</button>
        </div>

        <datalist id="sessione-nome-options"></datalist>
        <select id="sessione-select" style="display:none;"></select>
    </div>
</div>
