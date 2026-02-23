<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz V3 - Regia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #eee;
            padding: 40px;
            text-align: center;
        }

        h2 { margin-bottom: 10px; }

        .info-bar { margin-bottom: 20px; }

        .badge {
            display: inline-block;
            padding: 8px 16px;
            background: #333;
            border-radius: 20px;
            font-size: 14px;
            margin: 5px;
        }

        button {
            padding: 12px 20px;
            margin: 8px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            color: white;
            border-radius: 6px;
            transition: 0.2s;
        }

        .enabled { background: #2ecc71; }
        .disabled { background: #c0392b; }

        button:hover { opacity: 0.85; }

        #stato { font-size: 20px; margin-bottom: 10px; }
        #conclusa {
            font-size: 28px;
            color: #f1c40f;
            margin-top: 20px;
            display: none;
        }

        .row { margin-top: 25px; }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .timer {
            font-weight: bold;
            color: #f39c12;
        }

        /* ===== LOG LEGGIBILE ===== */
        .log-wrap{
            margin-top: 30px;
            text-align: left;
        }

        .log-head{
            display:flex;
            align-items:center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .log-title{
            font-size: 16px;
            opacity: .9;
        }

        .log-actions button{
            padding: 8px 12px;
            font-size: 13px;
            border-radius: 6px;
            background: #2c3e50;
        }

        .log {
            background: #151515;
            border: 1px solid #222;
            border-radius: 10px;
            padding: 12px;
            max-height: 320px;
            overflow: auto;
        }

        .log-item{
            background: #1c1c1c;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .log-top{
            display:flex;
            align-items:center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }

        .pill{
            display:inline-flex;
            align-items:center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid #333;
            background: #222;
            white-space: nowrap;
        }

        .pill.ok{
            border-color: #1f7a3a;
            background: rgba(46, 204, 113, 0.12);
        }

        .pill.err{
            border-color: #7a1f1f;
            background: rgba(192, 57, 43, 0.12);
        }

        .log-time{
            font-size: 12px;
            opacity: .7;
            white-space: nowrap;
        }

        .log-main{
            font-size: 14px;
            line-height: 1.35;
            opacity: .95;
        }

        .log-sub{
            margin-top: 6px;
            font-size: 13px;
            opacity: .85;
        }

        details{
            margin-top: 10px;
        }

        summary{
            cursor: pointer;
            user-select: none;
            font-size: 13px;
            opacity: .85;
        }

        pre.json {
            background: #101010;
            border: 1px solid #222;
            padding: 10px;
            border-radius: 8px;
            overflow: auto;
            font-size: 12px;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="container">

<h2>🎛 ChillQuiz V3 – Regia</h2>

<div class="info-bar">
    <div class="badge">
        Sessione ID: <strong id="sessione-id">30</strong>
    </div>
    <div class="badge">
        Domanda: <strong id="domanda-numero">1</strong>
    </div>
    <div class="badge">
        Partecipanti: <strong id="partecipanti-numero">0</strong>
    </div>
    <div class="badge">
        Timer: <strong id="timer" class="timer">--</strong>
    </div>
</div>

<div id="stato">Stato: ...</div>
<div id="conclusa">🎉 SESSIONE CONCLUSA</div>

<!-- FASI -->
<div class="row">
    <button id="btnPuntata">Avvia Puntata</button>
    <button id="btnDomanda">Avvia Domanda</button>
    <button id="btnRisultati">Chiudi Domanda</button>
    <button id="btnProssima">Prossima Fase</button>
</div>

<!-- SISTEMA -->
<div class="row">
    <button id="btnNuova">Nuova Sessione</button>
    <button id="btnRiavvia">Riavvia</button>
</div>

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

</div>

<script>

let SESSIONE_ID = <?= (int)$sessioneId ?>;

const ADMIN_TOKEN = "SUPERSEGRETO123";
const API_BASE = 'index.php?url=api';

const sessioneIdSpan   = document.getElementById('sessione-id');
const domandaNumero    = document.getElementById('domanda-numero');
const partecipantiSpan = document.getElementById('partecipanti-numero');
const timerSpan        = document.getElementById('timer');

const btnNuova      = document.getElementById('btnNuova');
const btnPuntata    = document.getElementById('btnPuntata');
const btnDomanda    = document.getElementById('btnDomanda');
const btnRisultati  = document.getElementById('btnRisultati');
const btnProssima   = document.getElementById('btnProssima');
const btnRiavvia    = document.getElementById('btnRiavvia');
const btnClearLog   = document.getElementById('btnClearLog');

const statoDiv    = document.getElementById('stato');
const conclusaDiv = document.getElementById('conclusa');
const logEl       = document.getElementById('log');

let timerInterval = null;

/* ===== LOG UTIL ===== */
function nowTime() {
    const d = new Date();
    return d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function addLog({ ok, title, message, data }) {

    const time = nowTime();
    const pillClass = ok ? 'ok' : 'err';
    const icon = ok ? '✅' : '❌';

    const item = document.createElement('div');
    item.className = 'log-item';

    const safeTitle = title ?? 'Azione';
    const safeMsg = message ?? '';

    const json = (data !== undefined) ? JSON.stringify(data, null, 2) : '';

    item.innerHTML = `
        <div class="log-top">
            <div class="pill ${pillClass}">${icon} ${safeTitle}</div>
            <div class="log-time">${time}</div>
        </div>
        <div class="log-main">${safeMsg}</div>
        ${json ? `
            <details>
                <summary>Dettagli</summary>
                <pre class="json">${json}</pre>
            </details>
        ` : ''}
    `;

    logEl.prepend(item);
}

function clearLog() {
    logEl.innerHTML = '';
}

/* ===== UI ===== */
function setButton(button, enabled) {
    if (enabled) {
        button.classList.remove('disabled');
        button.classList.add('enabled');
        button.disabled = false;
    } else {
        button.classList.remove('enabled');
        button.classList.add('disabled');
        button.disabled = true;
    }
}

function aggiornaUI(sessione) {

    sessioneIdSpan.textContent = SESSIONE_ID;
    domandaNumero.textContent  = sessione.domanda_corrente;

    statoDiv.textContent = "Stato: " + sessione.stato;
    conclusaDiv.style.display = (sessione.stato === 'conclusa') ? 'block' : 'none';

    setButton(btnPuntata,  sessione.stato === 'attesa' || sessione.stato === 'risultati');
    setButton(btnDomanda,  sessione.stato === 'puntata');
    setButton(btnRisultati, sessione.stato === 'domanda');
    setButton(btnProssima, sessione.stato === 'risultati');

    setButton(btnNuova, true);
    setButton(btnRiavvia, true);

    aggiornaPartecipanti();
    aggiornaTimer(sessione);
}

let ultimoNumeroPartecipanti = 0;

async function aggiornaPartecipanti() {
    try {

        const res = await fetch(`${API_BASE}/classifica/${SESSIONE_ID}`);
        const data = await res.json();

        if (!data.success) return;

        const lista = data.classifica;
        const numeroAttuale = lista.length;

        partecipantiSpan.textContent = numeroAttuale;

        // 🔔 NUOVO PLAYER ENTRATO
        if (numeroAttuale > ultimoNumeroPartecipanti) {

            const nuovi = lista.slice(0, numeroAttuale - ultimoNumeroPartecipanti);

            nuovi.forEach(p => {
                addLog({
                    ok: true,
                    title: 'Nuovo giocatore',
                    message: `${p.nome} si è unito alla sessione`,
                    data: p
                });
            });
        }

        ultimoNumeroPartecipanti = numeroAttuale;

    } catch (e) {
        // silenzioso
    }
}
function aggiornaTimer(sessione) {

    clearInterval(timerInterval);
    timerSpan.textContent = "--";

    if (sessione.stato !== 'domanda' || !sessione.inizio_domanda) return;

    const durata = 120; // se vuoi dopo lo leggiamo da configurazioni_sistema
    timerInterval = setInterval(() => {

        const elapsed = Math.floor(Date.now()/1000) - sessione.inizio_domanda;
        const remaining = Math.max(0, durata - elapsed);

        timerSpan.textContent = remaining + "s";

        if (remaining <= 0) {
            clearInterval(timerInterval);
        }

    }, 1000);
}

/* ===== API calls ===== */
async function callAdmin(action) {

    const startedAt = nowTime();

    const res = await fetch(`${API_BASE}/admin/${action}/${SESSIONE_ID}`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        }
    });

    const data = await res.json();

    addLog({
        ok: !!data.success,
        title: `Admin: ${action}`,
        message: data.success
            ? `OK su sessione ${SESSIONE_ID} (alle ${startedAt})`
            : (data.error ? `Errore: ${data.error}` : `Errore sconosciuto`),
        data
    });

    aggiornaStato();
}

async function nuovaSessione() {

    const res = await fetch(`${API_BASE}/admin/nuova-sessione/0`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        }
    });

    const data = await res.json();

    addLog({
        ok: !!data.success,
        title: 'Admin: nuova-sessione',
        message: data.success
            ? `Creata nuova sessione: ${data.sessione_id}`
            : (data.error ? `Errore: ${data.error}` : `Errore sconosciuto`),
        data
    });

    if (data.success) {
        SESSIONE_ID = data.sessione_id;
        aggiornaStato();
    }
}

async function aggiornaStato() {
    const res = await fetch(`${API_BASE}/stato/${SESSIONE_ID}`);
    const data = await res.json();

    if (data.success) {
        aggiornaUI(data.sessione);
    }
}

/* ===== bind ===== */
btnNuova.onclick     = nuovaSessione;
btnPuntata.onclick   = () => callAdmin('avvia-puntata');
btnDomanda.onclick   = () => callAdmin('avvia-domanda');
btnRisultati.onclick = () => callAdmin('risultati');
btnProssima.onclick  = () => callAdmin('prossima');
btnRiavvia.onclick   = () => callAdmin('riavvia');

btnClearLog.onclick  = clearLog;

/* ===== start ===== */
setInterval(aggiornaStato, 2000);
aggiornaStato();

</script>

</body>
</html>