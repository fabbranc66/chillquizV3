<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz V3 - Gestione Media</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #101014; color: #eee; margin: 0; }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        h1 { margin-top: 0; }
        .toolbar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        button, input[type="text"], input[type="file"] { border-radius: 6px; border: 1px solid #333; }
        button { background: #2e7d32; color: #fff; padding: 10px 14px; cursor: pointer; border: none; }
        button.secondary { background: #37474f; }
        button.warn { background: #b71c1c; }
        .card { background: #171a21; border: 1px solid #252935; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
        .media-grid { display: grid; gap: 12px; }
        .media-item { display: grid; grid-template-columns: 140px 1fr auto; gap: 14px; align-items: center; }
        .media-item img { width: 140px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #2a2a2a; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; margin-left: 6px; }
        .badge.active { background: #1b5e20; }
        .empty { opacity: .8; }
        .actions { display: flex; gap: 8px; }
        .upload-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; }
        .upload-form input[type="text"], .upload-form input[type="file"] { background: #0f1218; color: #fff; padding: 10px; }
        .log { margin-top: 12px; font-size: 14px; min-height: 20px; }
        a.back { color: #9ecbff; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <p><a class="back" href="index.php?url=admin/index">← Torna alla regia</a></p>
    <h1>🖼 Gestione media screen</h1>

    <div class="card">
        <form id="upload-form" class="upload-form">
            <input id="titolo" type="text" name="titolo" placeholder="Titolo immagine (opzionale)">
            <input id="immagine" type="file" name="immagine" accept="image/*" required>
            <button type="submit">Carica</button>
        </form>
        <div class="toolbar">
            <button id="btn-refresh" type="button" class="secondary">Aggiorna lista</button>
            <button id="btn-disattiva" type="button" class="warn">Disattiva tutte</button>
        </div>
        <div id="log" class="log"></div>
    </div>

    <div id="media-list" class="media-grid card">
        <div class="empty">Nessuna immagine caricata.</div>
    </div>
</div>

<script>
const BASE_PUBLIC_URL = window.location.pathname.replace(/index\.php$/, '');
const API_BASE = `${BASE_PUBLIC_URL}index.php?url=api`;

function mediaUrl(filePath) {
    if (!filePath) return '';
    const clean = String(filePath).startsWith('/') ? String(filePath).substring(1) : String(filePath);
    return `${BASE_PUBLIC_URL}${clean}`;
}
const ADMIN_TOKEN = 'SUPERSEGRETO123';

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

document.getElementById('upload-form').addEventListener('submit', uploadMedia);
document.getElementById('btn-refresh').addEventListener('click', loadMedia);
document.getElementById('btn-disattiva').addEventListener('click', disattivaMedia);

loadMedia();
</script>
</body>
</html>
