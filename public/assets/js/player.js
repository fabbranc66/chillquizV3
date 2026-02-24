// ==========================================
// CHILLQUIZ V3 - PLAYER UI (QR READY)
// ==========================================

const API_BASE = '/chillquizV3/public/?url=api';

let sessioneId = null;
let partecipazioneId = null;
let currentState = null;
let pollingInterval = null;
let joinRequestPolling = null;

let rispostaInviata = false;
let puntataInviata = false;

/* ===============================
   INIT
=============================== */

document.addEventListener('DOMContentLoaded', () => {

    const urlParam = new URLSearchParams(window.location.search).get('url');

    if (urlParam && urlParam.startsWith('player/')) {
        sessioneId = urlParam.split('/')[1];
    }

    console.log('Sessione ID:', sessioneId);

    if (!sessioneId || isNaN(sessioneId)) {
        alert('Sessione non valida');
        return;
    }

    sessioneId = parseInt(sessioneId);

    document.getElementById('btn-entra').addEventListener('click', handleJoin);
    document.getElementById('btn-punta').addEventListener('click', handlePuntata);
});

/* ===============================
   JOIN
=============================== */

async function handleJoin() {

    const nome = document.getElementById('player-name').value.trim();

    if (!nome) {
        alert('Inserisci un nome');
        return;
    }

    try {

        const formData = new FormData();
        formData.append('nome', nome);

        const response = await fetch(
            `${API_BASE}/join/${sessioneId}`,
            {
                method: 'POST',
                body: formData
            }
        );

        const data = await response.json();

        if (!data.success) {
            if (data.requires_approval && data.request_id) {
                alert(data.error);
                watchJoinRequest(data.request_id, nome);
                return;
            }

            alert(data.error);
            return;
        }

        partecipazioneId = data.partecipazione_id;
        completeJoin(nome, data.capitale);

    } catch (err) {
        console.error(err);
    }
}


function completeJoin(nome, capitale) {
    document.getElementById('player-display-name').innerText = nome;
    document.getElementById('capitale-value').innerText = capitale;
    startPolling();
    show('screen-lobby');
}

function watchJoinRequest(requestId, nome) {
    if (joinRequestPolling) {
        clearInterval(joinRequestPolling);
    }

    const checkStatus = async () => {
        try {
            const formData = new FormData();
            formData.append('request_id', requestId);

            const response = await fetch(
                `${API_BASE}/joinStato/${sessioneId}`,
                {
                    method: 'POST',
                    body: formData
                }
            );

            const data = await response.json();

            if (!data.success) return;

            if (data.stato === 'approvata' && data.partecipazione_id) {
                partecipazioneId = data.partecipazione_id;
                clearInterval(joinRequestPolling);
                joinRequestPolling = null;
                completeJoin(nome, data.capitale ?? 0);
                return;
            }

            if (data.stato === 'rifiutata') {
                clearInterval(joinRequestPolling);
                joinRequestPolling = null;
                alert('Richiesta di accesso rifiutata dalla regia');
            }
        } catch (err) {
            console.error(err);
        }
    };

    checkStatus();
    joinRequestPolling = setInterval(checkStatus, 2000);
}

/* ===============================
   POLLING STATO
=============================== */

function startPolling() {
    pollingInterval = setInterval(fetchStato, 1000);
}

async function fetchStato() {

    if (!sessioneId) return;

    try {

        const response = await fetch(
            `${API_BASE}/stato/${sessioneId}`
        );

        const data = await response.json();

        if (!data.success) return;

        const stato = data.sessione.stato;

        if (stato !== currentState) {
            currentState = stato;
            rispostaInviata = false;
            puntataInviata = false;
            renderState(stato);
        }

        if (stato === 'domanda') {
            fetchDomanda();
        }

        if (stato === 'risultati') {
            fetchClassifica();
        }

    } catch (err) {
        console.error(err);
    }
}

/* ===============================
   RENDER STATO
=============================== */

function renderState(stato) {

    hideAllScreens();

    switch (stato) {

        case 'attesa':
            show('screen-lobby');
            break;

        case 'puntata':
            show('screen-puntata');
            break;

        case 'domanda':
            show('screen-domanda');
            break;

        case 'risultati':
            show('screen-risultati');

            // aspettiamo un micro tick DOM prima di renderizzare
            setTimeout(() => {
                fetchClassifica();
            }, 100);

            break;

case 'conclusa':
    show('screen-risultati');
    fetchClassifica();
    break;    }
}
/* ===============================
   DOMANDA
=============================== */

async function fetchDomanda() {

    try {

        const response = await fetch(
            `${API_BASE}/domanda/${sessioneId}`
        );

        const data = await response.json();

        if (!data.success) return;

        renderDomanda(data.domanda);

    } catch (err) {
        console.error(err);
    }
}

function renderDomanda(domanda) {

    if (!domanda) return;

    document.getElementById('domanda-testo').innerText = domanda.testo || '';

    const opzioniDiv = document.getElementById('opzioni');
    opzioniDiv.innerHTML = '';

    if (!domanda.opzioni || !Array.isArray(domanda.opzioni)) {
        console.warn('Opzioni non valide:', domanda);
        return;
    }

    domanda.opzioni.forEach((opzione, index) => {

        const btn = document.createElement('button');
        btn.innerText = opzione.testo;

btn.dataset.id = opzione.id;
btn.onclick = () => inviaRisposta(domanda.id, opzione.id);
        opzioniDiv.appendChild(btn);
    });
}
async function inviaRisposta(domandaId, opzioneId) {

    if (rispostaInviata) return;
    rispostaInviata = true;

    // evidenzia scelta
    const buttons = document.querySelectorAll('#opzioni button');
    buttons.forEach(btn => {
        btn.disabled = true;

        if (btn.dataset.id == opzioneId) {
            btn.classList.add('selected');
        } else {
            btn.classList.add('dimmed');
        }
    });

    try {

        const formData = new FormData();
        formData.append('partecipazione_id', partecipazioneId);
        formData.append('domanda_id', domandaId);
        formData.append('opzione_id', opzioneId);

        const response = await fetch(
            `/chillquizV3/public/?url=api/risposta/${sessioneId}`,
            {
                method: 'POST',
                body: formData
            }
        );

        const data = await response.json();

        if (!data.success) {
            alert(data.error);
            rispostaInviata = false;
        }

if (data.success && data.risultato) {
    const capitaleSpan = document.getElementById('capitale-value');
    const attuale = parseInt(capitaleSpan.innerText);
    capitaleSpan.innerText = attuale + data.risultato.punti;
}
    } catch (err) {
        console.error(err);
        rispostaInviata = false;
    }
}
/* ===============================
   PUNTATA
=============================== */

async function handlePuntata() {

    if (puntataInviata) return;

    const importo = document.getElementById('puntata').value;

    if (!importo || importo <= 0) {
        alert('Inserisci importo valido');
        return;
    }

    puntataInviata = true;

    try {

        const formData = new FormData();
        formData.append('partecipazione_id', partecipazioneId);
        formData.append('puntata', parseInt(importo));

        const response = await fetch(
            `${API_BASE}/puntata/${sessioneId}`,
            {
                method: 'POST',
                body: formData
            }
        );

        const data = await response.json();

        if (!data.success) {
            alert(data.error);
            puntataInviata = false;
        }

    } catch (err) {
        console.error(err);
        puntataInviata = false;
    }
}

/* ===============================
   CLASSIFICA
=============================== */

async function fetchClassifica() {

    try {

        const response = await fetch(
            `${API_BASE}/classifica/${sessioneId}`
        );

        const data = await response.json();

        console.log("CLASSIFICA RESPONSE:", data);

        if (!data.success) return;

        const container = document.getElementById('classifica');

        if (!container) {
            console.warn("Container classifica non trovato");
            return;
        }

        container.innerHTML = '';

        if (!data.classifica || data.classifica.length === 0) {
            container.innerHTML = "<div>Nessun giocatore</div>";
            return;
        }

        data.classifica.forEach((riga, index) => {

            const div = document.createElement('div');
            div.className = "classifica-item";

            div.innerHTML = `
                <strong>${index + 1}.</strong> 
                ${riga.nome} 
                <span>💰 ${riga.capitale_attuale}</span>
            `;

            container.appendChild(div);
        });

    } catch (err) {
        console.error("Errore classifica:", err);
    }
}
/* ===============================
   UTILITY
=============================== */

function hideAllScreens() {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.add('hidden');
    });
}

function show(id) {
    document.getElementById(id).classList.remove('hidden');
}
