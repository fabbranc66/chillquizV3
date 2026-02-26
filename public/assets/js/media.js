const MEDIA_BOOTSTRAP = window.MEDIA_BOOTSTRAP || {};
const BASE_PUBLIC_URL = MEDIA_BOOTSTRAP.basePublicUrl || window.location.pathname.replace(/index\.php$/, '');
const API_BASE = MEDIA_BOOTSTRAP.apiBase || `${BASE_PUBLIC_URL}index.php?url=api`;
const ADMIN_TOKEN = MEDIA_BOOTSTRAP.adminToken || 'SUPERSEGRETO123';

function mediaUrl(filePath) {
    if (!filePath) return '';
    const clean = String(filePath).startsWith('/') ? String(filePath).substring(1) : String(filePath);
    return `${BASE_PUBLIC_URL}${clean}`;
}

const mediaListEl = document.getElementById('media-list');
const logEl = document.getElementById('log');

function showLog(msg, ok = true) {
    logEl.textContent = msg;
    logEl.style.color = ok ? '#7CFC8A' : '#ff8a80';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.innerText = value ?? '';
    return div.innerHTML;
}

async function loadMedia() {
    const res = await fetch(`${API_BASE}/admin/media-list/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN }
    });
    const data = await res.json();

    if (!data.success) {
        showLog(data.error || 'Errore caricamento media', false);
        return;
    }

    const items = data.media || [];
    if (items.length === 0) {
        mediaListEl.innerHTML = '<div class="empty">Nessuna immagine caricata.</div>';
        return;
    }

    mediaListEl.innerHTML = items.map((m) => {
        const path = mediaUrl(m.file_path);
        return `
            <div class="media-item">
                <img src="${path}" alt="${escapeHtml(m.titolo)}">
                <div>
                    <strong>${escapeHtml(m.titolo)}</strong>
                    ${Number(m.attiva) === 1 ? '<span class="badge active">ATTIVA</span>' : ''}
                    <div><small>${escapeHtml(m.file_path)}</small></div>
                </div>
                <div class="actions">
                    <button type="button" class="secondary" onclick="toggleMedia(${Number(m.id)}, ${Number(m.attiva) === 1 ? 0 : 1})">${Number(m.attiva) === 1 ? 'Disattiva' : 'Attiva'}</button>
                    <button type="button" class="warn" onclick="eliminaMedia(${Number(m.id)})">Elimina</button>
                </div>
            </div>
        `;
    }).join('');
}

async function toggleMedia(id, attiva) {
    const formData = new FormData();
    formData.append('media_id', String(id));
    formData.append('attiva', String(attiva));

    const res = await fetch(`${API_BASE}/admin/media-attiva/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN },
        body: formData
    });

    const data = await res.json();
    if (!data.success) {
        showLog(data.error || 'Errore attivazione', false);
        return;
    }

    showLog(attiva === 1 ? 'Immagine attivata correttamente' : 'Immagine disattivata correttamente');
    await loadMedia();
}

async function eliminaMedia(id) {
    const formData = new FormData();
    formData.append('media_id', String(id));

    const res = await fetch(`${API_BASE}/admin/media-elimina/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN },
        body: formData
    });

    const data = await res.json();
    if (!data.success) {
        showLog(data.error || 'Errore eliminazione', false);
        return;
    }

    showLog('Immagine eliminata');
    await loadMedia();
}

async function disattivaMedia() {
    const res = await fetch(`${API_BASE}/admin/media-disattiva/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN }
    });

    const data = await res.json();
    if (!data.success) {
        showLog(data.error || 'Errore disattivazione', false);
        return;
    }

    showLog('Nessuna immagine attiva');
    await loadMedia();
}

async function uploadMedia(event) {
    event.preventDefault();

    const form = document.getElementById('upload-form');
    const formData = new FormData(form);

    const fileInput = document.getElementById('immagine');
    if (!fileInput.files || fileInput.files.length === 0) {
        showLog('Seleziona un file', false);
        return;
    }

    const res = await fetch(`${API_BASE}/admin/media-upload/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': ADMIN_TOKEN },
        body: formData
    });

    const data = await res.json();
    if (!data.success) {
        showLog(data.error || 'Errore upload', false);
        return;
    }

    showLog('Immagine caricata con successo');
    form.reset();
    await loadMedia();
}

window.toggleMedia = toggleMedia;
window.eliminaMedia = eliminaMedia;

document.getElementById('upload-form').addEventListener('submit', uploadMedia);
document.getElementById('btn-refresh').addEventListener('click', loadMedia);
document.getElementById('btn-disattiva').addEventListener('click', disattivaMedia);

loadMedia();
