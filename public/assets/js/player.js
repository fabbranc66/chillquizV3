// ==========================================
// CHILLQUIZ V3 - PLAYER UI CORE
// ==========================================

const API_BASE = '/chillquizV3/public/?url=api';

let currentState = null;
let pollingInterval = null;
let playerId = null;

// ===============================
// INIT
// ===============================

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('btn-entra').addEventListener('click', handleAccess);

    startPolling();

});

// ===============================
// POLLING STATO
// ===============================

function startPolling() {
    pollingInterval = setInterval(fetchState, 1000);
}

async function fetchState() {
    try {

        const response = await fetch(`${API_BASE}/stato`);
        const data = await response.json();

        if (!data.success) {
            console.error(data.error);
            return;
        }

        if (data.stato !== currentState) {
            currentState = data.stato;
            renderState(currentState, data);
        }

    } catch (error) {
        console.error('Errore fetch stato:', error);
    }
}
// ===============================
// RENDER STATO
// ===============================

function renderState(state, data) {

    hideAllScreens();

    switch (state) {

        case 'attesa':
            show('screen-lobby');
            break;

        case 'puntata':
            show('screen-puntata');
            break;

        case 'domanda':
            show('screen-domanda');
            renderDomanda(data);
            break;

        case 'risultati':
            show('screen-risultati');
            break;

        case 'conclusa':
            show('screen-fine');
            break;

        default:
            show('screen-accesso');
    }
}

// ===============================
// DOMANDA
// ===============================

function renderDomanda(data) {

    if (!data.domanda) return;

    document.getElementById('domanda-testo').innerText = data.domanda.testo;

    const opzioniDiv = document.getElementById('opzioni');
    opzioniDiv.innerHTML = '';

    data.domanda.opzioni.forEach(opzione => {
        const btn = document.createElement('button');
        btn.innerText = opzione.testo;
        btn.onclick = () => console.log('Risposta scelta:', opzione.id);
        opzioniDiv.appendChild(btn);
    });
}

// ===============================
// ACCESSO
// ===============================

function handleAccess() {
    const nome = document.getElementById('player-name').value;

    if (!nome) {
        alert('Inserisci un nome');
        return;
    }

    console.log('Accesso con nome:', nome);
    // Qui faremo chiamata API join
}

// ===============================
// UTILITY
// ===============================

function hideAllScreens() {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.add('hidden');
    });
}

function show(id) {
    document.getElementById(id).classList.remove('hidden');
}