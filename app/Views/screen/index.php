<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChillQuiz Screen</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top, #1f2a44 0%, #0f1115 60%);
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
        }

        .stage {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 18px;
            padding: 22px;
            position: relative;
        }

        .stage-header,
        .stage-footer {
            text-align: center;
            opacity: 0.9;
        }

        .stage-header h1 {
            margin: 0;
            font-size: clamp(28px, 3vw, 44px);
            letter-spacing: .8px;
        }

        .stage-header p {
            margin: 8px 0 0;
            color: #b7c1d1;
            font-size: clamp(14px, 1.3vw, 18px);
        }

        .stage-main {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #screen-domanda {
            width: min(1180px, 96vw);
            text-align: center;
        }

        #domanda-testo {
            font-size: clamp(28px, 4vw, 52px);
            margin: 0 0 34px;
            line-height: 1.25;
        }

        .grid-opzioni {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .opzione {
            border: 0;
            border-radius: 14px;
            padding: 28px 18px;
            color: #fff;
            font-size: clamp(20px, 2.4vw, 34px);
            font-weight: 700;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* Kahoot style colors */
        .grid-opzioni .opzione:nth-child(1) { background: #e84118; }
        .grid-opzioni .opzione:nth-child(2) { background: #0097e6; }
        .grid-opzioni .opzione:nth-child(3) { background: #fbc531; color: #111; }
        .grid-opzioni .opzione:nth-child(4) { background: #4cd137; }

        .stage-placeholder {
            color: #b7c1d1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        #placeholder-message {
            margin: 0;
            font-size: clamp(20px, 2vw, 30px);
        }

        .state-image {
            width: min(560px, 70vw);
            max-height: 56vh;
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
        }

        .stage-qr {
            position: absolute;
            top: 22px;
            right: 22px;
            display: grid;
            justify-items: center;
            gap: 8px;
            z-index: 3;
        }

        .stage-qr img {
            width: 130px;
            height: 130px;
            background: #fff;
            border-radius: 12px;
            padding: 8px;
        }

        .stage-qr small {
            color: #b7c1d1;
            font-size: 12px;
            text-align: center;
            max-width: 140px;
        }

        .scoreboard-wrap {
            width: min(1200px, 96vw);
        }

        .scoreboard-title {
            margin: 0 0 20px;
            font-size: clamp(28px, 3vw, 46px);
            text-align: center;
        }

        .scoreboard-list {
            display: grid;
            gap: 12px;
        }

        .scoreboard-item {
            border-radius: 14px;
            padding: 16px 20px;
            display: grid;
            grid-template-columns: 64px 1fr auto;
            align-items: center;
            gap: 14px;
            font-weight: 700;
            font-size: clamp(18px, 2vw, 30px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.25);
        }

        .score-rank {
            font-size: clamp(20px, 2.4vw, 34px);
            text-align: center;
        }

        .score-points {
            font-size: clamp(20px, 2.2vw, 34px);
        }

        .scoreboard-item:nth-child(1) { background: #e84118; }
        .scoreboard-item:nth-child(2) { background: #0097e6; }
        .scoreboard-item:nth-child(3) { background: #fbc531; color: #111; }
        .scoreboard-item:nth-child(4) { background: #4cd137; }
        .scoreboard-item:nth-child(n+5) { background: #6c5ce7; }

        .scoreboard-empty {
            text-align: center;
            color: #d8deea;
            font-size: clamp(18px, 1.8vw, 28px);
            padding: 20px;
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>

<div class="stage">
    <header class="stage-header">
        <h1>ChillQuiz</h1>
        <p>Schermo regia</p>
    </header>

    <div id="screen-qr" class="stage-qr">
        <img id="sessione-qr" alt="QR accesso sessione">
        <small>Scansiona per entrare nella sessione</small>
    </div>

    <main class="stage-main">
        <div id="screen-domanda" class="hidden">
            <h2 id="domanda-testo"></h2>
            <div id="opzioni" class="grid-opzioni"></div>
        </div>

        <div id="screen-risultati" class="scoreboard-wrap hidden">
            <h2 class="scoreboard-title">🏆 Classifica</h2>
            <div id="scoreboard-list" class="scoreboard-list"></div>
        </div>

        <div id="screen-placeholder" class="stage-placeholder">
            <p id="placeholder-message">In attesa della prossima domanda...</p>
            <img id="state-image" class="state-image" alt="Stato sessione">
        </div>
    </main>

    <footer class="stage-footer">
        Le opzioni sono visibili solo durante lo stato <strong>domanda</strong>.
    </footer>
</div>

<script>
const BASE_PUBLIC_URL = window.location.pathname.replace(/index\.php$/, '');
const API_BASE = `${BASE_PUBLIC_URL}index.php?url=api`;
let sessioneId = <?= (int)($sessioneId ?? 0) ?>;
let currentState = null;
let poll = null;
let domandaRenderizzata = false;
let mediaAttiva = null;

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

    if (state === 'fine') {
        return {
            message: 'Quiz terminato'
        };
    }

    return {
        message: 'In attesa della prossima domanda...'
    };
}

function renderStateImage(state) {
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


async function fetchMediaAttiva() {
    try {
        const r = await fetch(`${API_BASE}/mediaAttiva`);
        const data = await r.json();

        if (!data.success) return;

        mediaAttiva = data.media || null;

        if (currentState !== 'domanda') {
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
            hideDomandaView();
            return;
        }

        currentState = data.sessione?.stato || null;

        if (currentState === 'domanda') {
            hideRisultatiView();
            showDomandaLoadingView();
            fetchDomandaIfActive();
        } else if (currentState === 'risultati') {
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

document.addEventListener('DOMContentLoaded', () => {
    if (!sessioneId) {
        sessioneId = extractSessioneIdFromUrl();
    }

    setupSessionQr();
    hideDomandaView();
    fetchMediaAttiva();
    fetchStato();

    if (poll) clearInterval(poll);
    poll = setInterval(() => {
        fetchStato();
        fetchMediaAttiva();
    }, 1000);
});
</script>

</body>
</html>
