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

        h2 {
            margin-bottom: 10px;
        }

        .info-bar {
            margin-bottom: 20px;
        }

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

        pre {
            background: #222;
            padding: 20px;
            margin-top: 30px;
            min-height: 120px;
            text-align: left;
            border-radius: 6px;
        }

        #stato {
            font-size: 20px;
            margin-bottom: 10px;
        }

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

<pre id="output">In attesa di chiamata API...</pre>

</div>

<script>

let SESSIONE_ID = <?= (int)$sessioneId ?>;

const ADMIN_TOKEN = "SUPERSEGRETO123";
const API_BASE = 'index.php?url=api';

const sessioneIdSpan  = document.getElementById('sessione-id');
const domandaNumero   = document.getElementById('domanda-numero');

const btnNuova      = document.getElementById('btnNuova');
const btnPuntata    = document.getElementById('btnPuntata');
const btnDomanda    = document.getElementById('btnDomanda');
const btnRisultati  = document.getElementById('btnRisultati');
const btnProssima   = document.getElementById('btnProssima');
const btnRiavvia    = document.getElementById('btnRiavvia');

const statoDiv      = document.getElementById('stato');
const conclusaDiv   = document.getElementById('conclusa');
const output        = document.getElementById('output');

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
}

async function callAdmin(action) {

    const res = await fetch(`${API_BASE}/admin/${action}/${SESSIONE_ID}`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        }
    });

    const data = await res.json();
    output.textContent = JSON.stringify(data, null, 4);

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
    output.textContent = JSON.stringify(data, null, 4);

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

btnNuova.onclick     = nuovaSessione;
btnPuntata.onclick   = () => callAdmin('avvia-puntata');
btnDomanda.onclick   = () => callAdmin('avvia-domanda');
btnRisultati.onclick = () => callAdmin('risultati');
btnProssima.onclick  = () => callAdmin('prossima');
btnRiavvia.onclick   = () => callAdmin('riavvia');

setInterval(aggiornaStato, 2000);
aggiornaStato();

</script>

</body>
</html>