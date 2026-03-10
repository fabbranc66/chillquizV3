const SETTINGS_BOOTSTRAP = window.SETTINGS_BOOTSTRAP || {};
const rawBaseApi = String(
    SETTINGS_BOOTSTRAP.apiBase
    || `${String(window.location.pathname || '').replace(/index\.php.*$/i, '').replace(/\/?$/, '/') }index.php?url=api`
);
const API_BASE = rawBaseApi;
const PUBLIC_BASE_URL = String(SETTINGS_BOOTSTRAP.publicBaseUrl || '').replace(/\/?$/, '/');

const toggleEl = document.getElementById('show-module-tags');
const logEl = document.getElementById('log');
const settingsListEl = document.getElementById('settings-list');
const logoPreviewEl = document.getElementById('logo-preview');
const logoFileEl = document.getElementById('logo-file');
const uploadLogoButtonEl = document.getElementById('btn-upload-logo');

let configurazioniCache = {};

const GROUPS = [
    {
        id: 'sessione',
        title: 'Sessione',
        description: 'Impostazioni base di avvio e sessione corrente.',
        keys: ['sessione_corrente_id', 'capitale_iniziale', 'logo']
    },
    {
        id: 'tempo',
        title: 'Tempo domanda',
        description: 'Durata round e velocita del punteggio.',
        keys: ['durata_domanda', 'fattore_velocita_max']
    },
    {
        id: 'punteggi',
        title: 'Punteggi e bonus',
        description: 'Bonus del primo e coefficienti di rientro.',
        keys: ['bonus_primo_attivo', 'coefficiente_bonus_primo', 'coefficiente_rientro_zero']
    },
    {
        id: 'altre',
        title: 'Altre configurazioni',
        description: 'Chiavi presenti nel sistema ma non ancora raggruppate.',
        keys: []
    }
];

const FIELD_META = {
    sessione_corrente_id: { label: 'Sessione corrente ID', hint: 'ID della sessione caricata di default.' },
    capitale_iniziale: { label: 'Capitale iniziale', hint: 'Capitale assegnato a ogni giocatore a inizio partita.' },
    logo: { label: 'Logo admin', hint: 'Path pubblico del logo da mostrare nell\'header admin.' },
    durata_domanda: { label: 'Durata domanda', hint: 'Durata del timer di risposta, in secondi.' },
    fattore_velocita_max: { label: 'Fattore velocita max', hint: 'Moltiplicatore massimo del bonus velocita.' },
    bonus_primo_attivo: { label: 'Bonus primo attivo', hint: 'Abilita il bonus per il primo che risponde correttamente.' },
    coefficiente_bonus_primo: { label: 'Coefficiente bonus primo', hint: 'Moltiplicatore usato per il bonus del primo.' },
    coefficiente_rientro_zero: { label: 'Coefficiente rientro zero', hint: 'Recupero capitale per chi resta a zero a fine fase.' }
};

function showLog(msg, ok = true) {
    logEl.textContent = msg;
    logEl.style.color = ok ? '#7CFC8A' : '#ff8a80';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.innerText = value ?? '';
    return div.innerHTML;
}

function publicMediaUrl(filePath) {
    if (!filePath) return '';
    const clean = String(filePath).startsWith('/') ? String(filePath).substring(1) : String(filePath);
    return `${PUBLIC_BASE_URL}${clean}`;
}

function renderLogoPreview(filePath) {
    if (!logoPreviewEl) return;
    const path = String(filePath || '').trim();
    logoPreviewEl.src = publicMediaUrl(path || 'upload/image/logo-chillquiz-1773183162-5169.png');
}

function renderConfigurazioni(configurazioni) {
    const entries = Object.entries(configurazioni || {});

    if (entries.length === 0) {
        settingsListEl.innerHTML = '<div class="setting-desc">Nessuna configurazione presente in configurazioni_sistema.</div>';
        return;
    }

    const groupedKeys = new Set();
    const groups = GROUPS.map((group) => {
        const items = group.keys
            .filter((key) => Object.prototype.hasOwnProperty.call(configurazioni, key))
            .map((key) => {
                groupedKeys.add(key);
                return [key, configurazioni[key]];
            });

        return Object.assign({}, group, { items });
    });

    const otherItems = entries.filter(([key]) => !groupedKeys.has(key));
    const htmlGroups = groups
        .filter((group) => group.items.length > 0 || group.id === 'altre')
        .map((group) => {
            const items = group.id === 'altre' ? otherItems : group.items;
            if (!items.length) return '';

            return `
                <section class="settings-section">
                    <div class="settings-section-head">
                        <h2>${escapeHtml(group.title)}</h2>
                        <p>${escapeHtml(group.description)}</p>
                    </div>
                    <div class="settings-section-body">
                        ${items.map(([key, value]) => renderConfigField(key, value)).join('')}
                    </div>
                </section>
            `;
        })
        .join('');

    settingsListEl.innerHTML = htmlGroups;
}

function renderConfigField(key, value) {
    const meta = FIELD_META[key] || {};
    const label = meta.label || key;
    const hint = meta.hint || '';

    return `
        <div class="config-row">
            <label for="cfg-${escapeHtml(key)}">
                <span class="config-label">${escapeHtml(label)}</span>
                <span class="config-key">${escapeHtml(key)}</span>
                ${hint ? `<span class="config-hint">${escapeHtml(hint)}</span>` : ''}
            </label>
            <input id="cfg-${escapeHtml(key)}" data-config-key="${escapeHtml(key)}" type="text" value="${escapeHtml(value)}">
        </div>
    `;
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

function updateLogoField(filePath) {
    const logoInput = document.querySelector('[data-config-key="logo"]');
    if (logoInput) {
        logoInput.value = filePath || '';
    }
}

async function loadSettings() {
    const res = await fetch(`${API_BASE}/admin/settings-get/0`, {
        method: 'POST'
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
    renderLogoPreview(configurazioniCache.logo || SETTINGS_BOOTSTRAP.currentLogoPath || '');
    showLog('Impostazioni caricate');
}

async function saveSettings() {
    const configurazioni = readConfigurazioniForm();

    const formData = new FormData();
    formData.append('show_module_tags', toggleEl.checked ? '1' : '0');
    formData.append('configurazioni_json', JSON.stringify(configurazioni));

    const res = await fetch(`${API_BASE}/admin/settings-save/0`, {
        method: 'POST',
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

async function uploadLogo() {
    if (!logoFileEl || !logoFileEl.files || logoFileEl.files.length === 0) {
        showLog('Seleziona un file logo', false);
        return;
    }

    const formData = new FormData();
    formData.append('logo_file', logoFileEl.files[0]);

    const res = await fetch(`${API_BASE}/admin/settings-logo-upload/0`, {
        method: 'POST',
        body: formData
    });

    const data = await res.json();

    if (!data.success) {
        showLog(data.error || 'Errore upload logo', false);
        return;
    }

    const settings = data.settings || {};
    configurazioniCache = settings.configurazioni_sistema || configurazioniCache;
    renderConfigurazioni(configurazioniCache);
    updateLogoField(data.logo_path || configurazioniCache.logo || '');
    renderLogoPreview(data.logo_path || configurazioniCache.logo || '');
    logoFileEl.value = '';
    showLog('Logo caricato correttamente');
}

document.getElementById('btn-save').addEventListener('click', saveSettings);
document.getElementById('btn-refresh').addEventListener('click', loadSettings);
if (uploadLogoButtonEl) {
    uploadLogoButtonEl.addEventListener('click', uploadLogo);
}

loadSettings();
