const SETTINGS_BOOTSTRAP = window.SETTINGS_BOOTSTRAP || {};
const API_BASE = SETTINGS_BOOTSTRAP.apiBase || 'index.php?url=api';
const ADMIN_TOKEN = SETTINGS_BOOTSTRAP.adminToken || 'SUPERSEGRETO123';

const toggleEl = document.getElementById('show-module-tags');
const logEl = document.getElementById('log');
const settingsListEl = document.getElementById('settings-list');

let configurazioniCache = {};

function showLog(msg, ok = true) {
    logEl.textContent = msg;
    logEl.style.color = ok ? '#7CFC8A' : '#ff8a80';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.innerText = value ?? '';
    return div.innerHTML;
}

function renderConfigurazioni(configurazioni) {
    const entries = Object.entries(configurazioni || {});

    if (entries.length === 0) {
        settingsListEl.innerHTML = '<div class="setting-desc">Nessuna configurazione presente in configurazioni_sistema.</div>';
        return;
    }

    settingsListEl.innerHTML = entries.map(([key, value]) => `
        <div class="config-row">
            <label for="cfg-${escapeHtml(key)}">${escapeHtml(key)}</label>
            <input id="cfg-${escapeHtml(key)}" data-config-key="${escapeHtml(key)}" type="text" value="${escapeHtml(value)}">
        </div>
    `).join('');
}

function readConfigurazioniForm() {
    const inputs = settingsListEl.querySelectorAll('[data-config-key]');
    const out = {};

    inputs.forEach((input) => {
        const key = input.getAttribute('data-config-key');
        if (!key) return;
        out[key] = input.value ?? '';
    });

    out.show_module_tags = toggleEl.checked ? '1' : '0';

    return out;
}

async function loadSettings() {
    const res = await fetch(`${API_BASE}/admin/settings-get/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN }
    });

    const data = await res.json();

    if (!data.success) {
        showLog(data.error || 'Errore caricamento impostazioni', false);
        return;
    }

    const settings = data.settings || {};
    configurazioniCache = settings.configurazioni_sistema || {};

    toggleEl.checked = !!settings.show_module_tags;
    renderConfigurazioni(configurazioniCache);
    showLog('Impostazioni caricate');
}

async function saveSettings() {
    const configurazioni = readConfigurazioniForm();

    const formData = new FormData();
    formData.append('show_module_tags', toggleEl.checked ? '1' : '0');
    formData.append('configurazioni_json', JSON.stringify(configurazioni));

    const res = await fetch(`${API_BASE}/admin/settings-save/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN },
        body: formData
    });

    const data = await res.json();

    if (!data.success) {
        showLog(data.error || 'Errore salvataggio impostazioni', false);
        return;
    }

    const settings = data.settings || {};
    configurazioniCache = settings.configurazioni_sistema || configurazioni;
    toggleEl.checked = !!settings.show_module_tags;
    renderConfigurazioni(configurazioniCache);

    showLog('Impostazioni salvate. Ricarica le view aperte per applicarle.');
}

document.getElementById('btn-save').addEventListener('click', saveSettings);
document.getElementById('btn-refresh').addEventListener('click', loadSettings);

loadSettings();
