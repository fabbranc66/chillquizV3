/**
 * FILE: public/assets/js/screen.js
 * SCOPO: Gestione UI Schermo (stato sessione, domanda, classifica risultati, media placeholder, QR join).
 * UTILIZZATO DA: app/Views/screen/index.php tramite <script src="/chillquizV3/public/assets/js/screen.js"></script>.
 * CHIAMATO DA: Browser (caricamento pagina schermo), setInterval scheduler.
 *
 * METODI PRINCIPALI E CONSUMATORI:
 * - fetchStato(): chiamato da bootstrap iniziale + scheduler stato.
 * - fetchDomandaIfActive(): chiamato quando stato = domanda.
 * - fetchClassificaRisultati(): chiamato quando stato = risultati.
 * - fetchMediaAttiva(): chiamato da bootstrap iniziale + scheduler media.
 */

/* ===============================
   BLOCCO LOGICO: BOOTSTRAP CONFIG
   =============================== */
const SCREEN_BOOTSTRAP = window.SCREEN_BOOTSTRAP || {};
const BASE_PUBLIC_URL = String(SCREEN_BOOTSTRAP.basePublicUrl || window.location.pathname.replace(/index\.php$/, ''));
const API_BASE = `${BASE_PUBLIC_URL}index.php?url=api`;
let sessioneId = Number(SCREEN_BOOTSTRAP.sessioneId || 0);
let currentState = null;
let pollStato = null;
let pollMedia = null;
let domandaRenderizzata = false;
let mediaAttiva = null;
const STATO_POLL_MS = 1000;
const MEDIA_POLL_MS = 10000;

/* ===============================
   BLOCCO LOGICO: SESSIONE/QR
   =============================== */
function extractSessioneIdFromUrl() {
    const raw = new URLSearchParams(window.location.search).get('url') || '';
    if (raw.startsWith('screen/')) {
        const id = parseInt(raw.split('/')[1], 10);
        if (!Number.isNaN(id) && id > 0) return id;
    }
    return 0;
}

function setupSessionQr() {
    if (!sessioneId) return;

    const qrImg = document.getElementById('sessione-qr');
    if (!qrImg) return;

    const joinUrl = `${window.location.origin}${BASE_PUBLIC_URL}index.php?url=player/${sessioneId}`;
    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(joinUrl)}`;
}

/* ===============================
   BLOCCO LOGICO: PLACEHOLDER/MEDIA
   =============================== */
function getStateMeta(state) {
    if (state === 'classifica') {
        return {
            message: 'Classifica in aggiornamento...'
        };
    }

    if (state === 'risultati') {
        return {
            message: 'Risultati del round'
        };
    }

    if (state === 'conclusa' || state === 'fine') {
        return {
            message: 'Quiz terminato'
        };
    }

    return {
        message: 'In attesa della prossima domanda...'
    };
}

function renderStateImage(state) {
    if (state === 'risultati') {
        return;
    }

    const img = document.getElementById('state-image');
    const message = document.getElementById('placeholder-message');
    if (!img || !message) return;

    const meta = getStateMeta(state);
    message.innerText = meta.message;

    if (mediaAttiva && mediaAttiva.file_path) {
        const mediaPath = mediaAttiva.file_path.startsWith('/') ? mediaAttiva.file_path.substring(1) : mediaAttiva.file_path;
        img.src = `${window.location.origin}${BASE_PUBLIC_URL}${mediaPath}`;
        img.alt = mediaAttiva.titolo || `Immagine stato: ${meta.message}`;
        return;
    }

    img.removeAttribute('src');
    img.alt = `Immagine stato: ${meta.message}`;
}


/* ===============================
   BLOCCO LOGICO: RISULTATI CLASSIFICA
   =============================== */
function hideRisultatiView() {
    document.getElementById('screen-risultati').classList.add('hidden');
}

function showRisultatiView() {
    document.getElementById('screen-placeholder').classList.add('hidden');
    document.getElementById('screen-domanda').classList.add('hidden');
    document.getElementById('screen-risultati').classList.remove('hidden');

    const stateImage = document.getElementById('state-image');
    if (stateImage) {
        stateImage.removeAttribute('src');
    }
}

function renderClassificaRisultati(classifica) {
    const listEl = document.getElementById('scoreboard-list');
    if (!listEl) return;

    if (!Array.isArray(classifica) || classifica.length === 0) {
        listEl.innerHTML = '<div class="scoreboard-empty">Nessun giocatore in classifica.</div>';
        return;
    }

    const ordinata = [...classifica].sort((a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0));

    listEl.innerHTML = ordinata.map((p, index) => {
        const nome = p.nome || 'Giocatore';
        const punti = Number(p.capitale_attuale ?? 0);
        return `
            <div class="scoreboard-item">
                <div class="score-rank">#${index + 1}</div>
                <div>${nome}</div>
                <div class="score-points">${punti}</div>
            </div>
        `;
    }).join('');
}

async function fetchClassificaRisultati() {
    if (!sessioneId) return;

    try {
        const r = await fetch(`${API_BASE}/classifica/${sessioneId}`);
        const data = await r.json();

        if (!data.success) {
            renderClassificaRisultati([]);
            return;
        }

        renderClassificaRisultati(data.classifica || []);
    } catch (e) {
        console.error(e);
    }
}

/* ===============================
   BLOCCO LOGICO: DOMANDA VIEW
   =============================== */
function hideDomandaView() {
    hideRisultatiView();
    document.getElementById('screen-domanda').classList.add('hidden');
    document.getElementById('screen-placeholder').classList.remove('hidden');
    document.getElementById('domanda-testo').innerText = '';
    document.getElementById('opzioni').innerHTML = '';
    domandaRenderizzata = false;

    if (currentState !== 'domanda') {
        renderStateImage(currentState);
    }
}

function showDomandaView() {
    document.getElementById('screen-placeholder').classList.add('hidden');
    document.getElementById('screen-domanda').classList.remove('hidden');

    const stateImage = document.getElementById('state-image');
    if (stateImage) {
        stateImage.removeAttribute('src');
    }
}

function showDomandaLoadingView() {
    showDomandaView();

    // Se la domanda è già renderizzata, evita qualsiasi intercalamento col loading
    if (domandaRenderizzata) return;

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (!titolo || !opzioni) return;

    titolo.innerText = 'Caricamento domanda...';

    if (opzioni.children.length > 0) return;

    opzioni.innerHTML = '';
    for (let i = 0; i < 4; i += 1) {
        const el = document.createElement('div');
        el.className = 'opzione';
        el.innerText = '...';
        opzioni.appendChild(el);
    }
}

function renderDomanda(domanda) {
    if (!domanda || !Array.isArray(domanda.opzioni)) {
        showDomandaLoadingView();
        return;
    }

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');

    titolo.innerText = domanda.testo || '';
    opzioni.innerHTML = '';

    domanda.opzioni.forEach((o) => {
        const el = document.createElement('div');
        el.className = 'opzione';
        el.innerText = o.testo || '';
        opzioni.appendChild(el);
    });

    domandaRenderizzata = true;
    showDomandaView();
}

async function fetchDomandaIfActive() {
    if (currentState !== 'domanda') {
        hideDomandaView();
        return;
    }

    try {
        const r = await fetch(`${API_BASE}/domanda/${sessioneId}`);
        const data = await r.json();
        if (currentState !== 'domanda') return;

        if (!data.success) {
            if (!domandaRenderizzata) showDomandaLoadingView();
            return;
        }

        renderDomanda(data.domanda);
    } catch (e) {
        console.error(e);
    }
}


/* ===============================
   BLOCCO LOGICO: API MEDIA/STATO
   =============================== */
async function fetchMediaAttiva() {
    try {
        const r = await fetch(`${API_BASE}/mediaAttiva`);
        const data = await r.json();

        if (!data.success) return;

        mediaAttiva = data.media || null;

        if (currentState !== 'domanda' && currentState !== 'risultati') {
            renderStateImage(currentState);
        }
    } catch (e) {
        console.error(e);
    }
}

async function fetchStato() {
    if (!sessioneId) {
        hideDomandaView();
        return;
    }

    try {
        const r = await fetch(`${API_BASE}/stato/${sessioneId}`);
        const data = await r.json();
        if (!data.success) {
            if (currentState === 'risultati') {
                showRisultatiView();
            } else {
                hideDomandaView();
            }
            return;
        }

        currentState = data.sessione?.stato || null;

        if (currentState === 'domanda') {
            hideRisultatiView();
            showDomandaLoadingView();
            fetchDomandaIfActive();
        } else if (currentState === 'risultati' || currentState === 'conclusa') {
            showRisultatiView();
            fetchClassificaRisultati();
        } else {
            hideDomandaView();
        }
    } catch (e) {
        console.error(e);
        hideDomandaView();
    }
}

/* ===============================
   BLOCCO LOGICO: BOOTSTRAP START
   =============================== */
document.addEventListener('DOMContentLoaded', () => {
    if (!sessioneId) {
        sessioneId = extractSessioneIdFromUrl();
    }

    setupSessionQr();
    hideDomandaView();
    fetchMediaAttiva();
    fetchStato();

    if (pollStato) clearInterval(pollStato);
    pollStato = setInterval(() => {
        fetchStato();
    }, STATO_POLL_MS);

    if (pollMedia) clearInterval(pollMedia);
    pollMedia = setInterval(() => {
        fetchMediaAttiva();
    }, MEDIA_POLL_MS);
});
