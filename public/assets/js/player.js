/**
 * FILE: public/assets/js/player.js
 * SCOPO: Gestione UI Player (join, polling stato, puntata, risposta, classifica live, risultato personale).
 * UTILIZZATO DA: app/Views/player/index.php tramite <script src="/chillquizV3/public/assets/js/player.js"></script>.
 * CHIAMATO DA: Browser (load player page), setInterval scheduler, eventi click sui pulsanti.
 *
 * METODI PRINCIPALI E CONSUMATORI:
 * - handleJoin(): chiamato da click su #btn-entra.
 * - fetchStato(): chiamato da scheduler 1s + allineamento iniziale.
 * - handlePuntata(): chiamato da click su #btn-punta.
 * - inviaRisposta(): chiamato dai bottoni opzioni renderizzati.
 * - fetchClassifica(): chiamato durante stati risultati/conclusa.
 */

/* ===============================
   BLOCCO LOGICO: BOOTSTRAP CONFIG
   =============================== */
const PLAYER_BOOTSTRAP = window.PLAYER_BOOTSTRAP || {};
const API_BASE = String(PLAYER_BOOTSTRAP.apiBase || '/chillquizV3/public/?url=api');

let sessioneId = Number(PLAYER_BOOTSTRAP.sessioneId || 0);
let partecipazioneId = null;
let currentState = null;
let pollingInterval = null;
let joinRequestPolling = null;

let rispostaInviata = false;
let puntataInviata = false;
let domandaFetchNonce = 0;

/* ===============================
   BLOCCO LOGICO: INIT
   =============================== */
document.addEventListener('DOMContentLoaded', () => {
    if (!sessioneId) {
        const urlParam = new URLSearchParams(window.location.search).get('url');
        if (urlParam && urlParam.startsWith('player/')) {
            sessioneId = Number(urlParam.split('/')[1] || 0);
        }
    }

    if (!sessioneId || Number.isNaN(sessioneId)) {
        alert('Sessione non valida');
        return;
    }

    const btnEntra = document.getElementById('btn-entra');
    const btnPunta = document.getElementById('btn-punta');

    if (btnEntra) btnEntra.addEventListener('click', handleJoin);
    if (btnPunta) btnPunta.addEventListener('click', handlePuntata);
});

/* ===============================
   BLOCCO LOGICO: JOIN
   =============================== */
async function handleJoin() {
    const nome = (document.getElementById('player-name')?.value || '').trim();

    if (!nome) {
        alert('Inserisci un nome');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('nome', nome);

        const response = await fetch(`${API_BASE}/join/${sessioneId}`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            if (data.requires_approval && data.request_id) {
                alert(data.error || 'Richiesta in approvazione');
                watchJoinRequest(data.request_id, nome);
                return;
            }

            alert(data.error || 'Join non riuscito');
            return;
        }

        partecipazioneId = Number(data.partecipazione_id || 0);
        completeJoin(nome, Number(data.capitale || 0));

    } catch (err) {
        console.error(err);
    }
}

function completeJoin(nome, capitale) {
    const displayName = document.getElementById('player-display-name');
    const capitaleValue = document.getElementById('capitale-value');

    if (displayName) displayName.innerText = nome;
    if (capitaleValue) capitaleValue.innerText = String(capitale);

    currentState = null;
    startPolling();

    hideAllScreens();
    show('screen-lobby');

    fetchStato();
}

function watchJoinRequest(requestId, nome) {
    if (joinRequestPolling) {
        clearInterval(joinRequestPolling);
    }

    const checkStatus = async () => {
        try {
            const formData = new FormData();
            formData.append('request_id', requestId);

            const response = await fetch(`${API_BASE}/joinStato/${sessioneId}`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) return;

            if (data.stato === 'approvata' && data.partecipazione_id) {
                partecipazioneId = Number(data.partecipazione_id || 0);
                clearInterval(joinRequestPolling);
                joinRequestPolling = null;
                completeJoin(nome, Number(data.capitale || 0));
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
   BLOCCO LOGICO: POLLING STATO
   =============================== */
function startPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }

    pollingInterval = setInterval(fetchStato, 1000);
}

async function fetchStato() {
    if (!sessioneId) return;

    try {
        const response = await fetch(`${API_BASE}/stato/${sessioneId}`);
        const data = await response.json();

        if (!data.success || !data.sessione) return;

        const stato = data.sessione.stato;

        if (stato !== currentState) {
            currentState = stato;
            rispostaInviata = false;
            puntataInviata = false;
        }

        renderState(stato);

        if (stato === 'domanda') {
            fetchDomanda();
        }

    } catch (err) {
        console.error(err);
    }
}

/* ===============================
   BLOCCO LOGICO: RENDER STATO
   =============================== */
function renderState(stato) {
    hideAllScreens();

    if (!isDomandaAttiva(stato)) {
        domandaFetchNonce++;
        resetDomandaView();
    }

    switch (stato) {
        case 'domanda':
            show('screen-domanda');
            break;

        case 'risultati':
        case 'conclusa':
            show('screen-risultati');
            fetchClassifica();
            break;

        case 'attesa':
            show('screen-lobby');
            break;

        case 'puntata':
            show('screen-puntata');
            break;

        default:
            show('screen-lobby');
            break;
    }
}

/* ===============================
   BLOCCO LOGICO: DOMANDA/RISPOSTA
   =============================== */
async function fetchDomanda() {
    if (!isDomandaAttiva()) return;

    const requestNonce = ++domandaFetchNonce;

    try {
        const response = await fetch(`${API_BASE}/domanda/${sessioneId}`);
        const data = await response.json();

        if (!data.success || !isDomandaAttiva() || requestNonce !== domandaFetchNonce) return;

        renderDomanda(data.domanda);

    } catch (err) {
        console.error(err);
    }
}

function renderDomanda(domanda) {
    if (!isDomandaAttiva()) return;

    if (!domanda || !Array.isArray(domanda.opzioni)) {
        resetDomandaView();
        return;
    }

    const domandaTesto = document.getElementById('domanda-testo');
    const opzioniDiv = document.getElementById('opzioni');

    if (!domandaTesto || !opzioniDiv) return;

    domandaTesto.innerText = domanda.testo || '';
    opzioniDiv.innerHTML = '';

    domanda.opzioni.forEach((opzione, index) => {
        const btn = document.createElement('button');
        btn.innerText = opzione.testo || '';
        btn.dataset.id = String(opzione.id || '');

        const paletteIndex = (index % 4) + 1;
        btn.classList.add(`opzione-kahoot-${paletteIndex}`);

        btn.onclick = () => inviaRisposta(domanda.id, opzione.id);
        opzioniDiv.appendChild(btn);
    });
}

function isDomandaAttiva(stato = currentState) {
    return stato === 'domanda';
}

function resetDomandaView() {
    const domandaTesto = document.getElementById('domanda-testo');
    const opzioniDiv = document.getElementById('opzioni');

    if (domandaTesto) domandaTesto.innerText = '';
    if (opzioniDiv) opzioniDiv.innerHTML = '';
}

async function inviaRisposta(domandaId, opzioneId) {
    if (rispostaInviata) return;
    rispostaInviata = true;

    const buttons = document.querySelectorAll('#opzioni button');
    buttons.forEach(btn => {
        btn.disabled = true;

        if (String(btn.dataset.id) === String(opzioneId)) {
            btn.classList.add('selected');
        } else {
            btn.classList.add('dimmed');
        }
    });

    try {
        const formData = new FormData();
        formData.append('partecipazione_id', String(partecipazioneId || 0));
        formData.append('domanda_id', String(domandaId || 0));
        formData.append('opzione_id', String(opzioneId || 0));

        const response = await fetch(`${API_BASE}/risposta/${sessioneId}`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            alert(data.error || 'Errore invio risposta');
            rispostaInviata = false;
            return;
        }

        renderRisultatoPersonaleImmediato(data.risultato);

    } catch (err) {
        console.error(err);
        rispostaInviata = false;
    }
}

/* ===============================
   BLOCCO LOGICO: PUNTATA
   =============================== */
async function handlePuntata() {
    if (puntataInviata) return;

    const importoRaw = document.getElementById('puntata')?.value;
    const importo = Number(importoRaw);

    if (!importo || importo <= 0) {
        alert('Inserisci importo valido');
        return;
    }

    puntataInviata = true;

    try {
        const formData = new FormData();
        formData.append('partecipazione_id', String(partecipazioneId || 0));
        formData.append('puntata', String(parseInt(String(importo), 10)));

        const response = await fetch(`${API_BASE}/puntata/${sessioneId}`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            alert(data.error || 'Errore puntata');
            puntataInviata = false;
        }

    } catch (err) {
        console.error(err);
        puntataInviata = false;
    }
}

/* ===============================
   BLOCCO LOGICO: CLASSIFICA/RISULTATO PERSONALE
   =============================== */
function getMiaRigaClassifica(lista) {
    if (!Array.isArray(lista) || lista.length === 0) return null;

    if (partecipazioneId) {
        const byId = lista.find((riga) => Number(riga.partecipazione_id || 0) === Number(partecipazioneId));
        if (byId) return byId;
    }

    const nomeGiocatore = (document.getElementById('player-display-name')?.innerText || '').trim().toLowerCase();
    if (!nomeGiocatore) return null;

    return lista.find((riga) => (riga.nome || '').trim().toLowerCase() === nomeGiocatore) || null;
}

function aggiornaCapitaleDaClassifica(lista) {
    const miaRiga = getMiaRigaClassifica(lista);
    if (!miaRiga) return;

    const capitale = Number(miaRiga.capitale_attuale ?? 0);
    const capitaleEl = document.getElementById('capitale-value');
    if (capitaleEl) capitaleEl.innerText = String(capitale);
}

function renderRisultatoPersonaleDaClassifica(lista) {
    const container = document.getElementById('risultato-personale');
    if (!container) return;

    const miaRiga = getMiaRigaClassifica(lista);

    if (!miaRiga) {
        container.classList.remove('esito-corretta', 'esito-errata');
        container.innerHTML = '<div>Risultato personale non disponibile.</div>';
        return;
    }

    const esito = miaRiga.esito ?? '-';
    const ultimaPuntata = Number(miaRiga.ultima_puntata ?? 0);
    const vincita = miaRiga.vincita_domanda === null || miaRiga.vincita_domanda === undefined
        ? '-'
        : Number(miaRiga.vincita_domanda);
    const tempo = miaRiga.tempo_risposta === null || miaRiga.tempo_risposta === undefined
        ? '-'
        : Number(miaRiga.tempo_risposta);
    const capitale = Number(miaRiga.capitale_attuale ?? 0);

    container.classList.remove('esito-corretta', 'esito-errata');
    if (esito === 'corretta') {
        container.classList.add('esito-corretta');
    } else if (esito === 'errata') {
        container.classList.add('esito-errata');
    }

    container.innerHTML = `
        <div class="risultato-row"><strong>Esito:</strong><span>${esito}</span></div>
        <div class="risultato-row"><strong>Puntata:</strong><span class="valore-numerico">💰 ${ultimaPuntata}</span></div>
        <div class="risultato-row"><strong>Vincita domanda:</strong><span class="valore-numerico">${vincita}</span></div>
        <div class="risultato-row"><strong>Tempo risposta:</strong><span class="valore-numerico">${tempo}</span></div>
        <div class="risultato-row"><strong>Capitale attuale:</strong><span class="valore-numerico">💰 ${capitale}</span></div>
    `;
}

function renderRisultatoPersonaleImmediato(risultato) {
    const container = document.getElementById('risultato-personale');
    if (!container || !risultato || typeof risultato !== 'object') return;

    const esito = risultato.corretta ? 'corretta' : 'errata';
    const punti = Number(risultato.punti ?? 0);
    const tempo = Number(risultato.tempo_risposta ?? 0);
    const capitale = Number(risultato.capitale ?? 0);

    container.classList.remove('esito-corretta', 'esito-errata');
    container.classList.add(risultato.corretta ? 'esito-corretta' : 'esito-errata');

    container.innerHTML = `
        <div class="risultato-row"><strong>Esito:</strong><span>${esito}</span></div>
        <div class="risultato-row"><strong>Punti:</strong><span class="valore-numerico">${punti}</span></div>
        <div class="risultato-row"><strong>Tempo risposta:</strong><span class="valore-numerico">${tempo}</span></div>
        <div class="risultato-row"><strong>Capitale attuale:</strong><span class="valore-numerico">💰 ${capitale}</span></div>
    `;
}

async function fetchClassifica() {
    try {
        const response = await fetch(`${API_BASE}/classifica/${sessioneId}`);
        const data = await response.json();

        if (!data.success) return;

        const classificaOrdinata = Array.isArray(data.classifica)
            ? [...data.classifica].sort((a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0))
            : [];

        aggiornaCapitaleDaClassifica(classificaOrdinata);
        renderRisultatoPersonaleDaClassifica(classificaOrdinata);

        const container = document.getElementById('classifica');
        if (!container) return;

        container.innerHTML = '';

        if (classificaOrdinata.length === 0) {
            container.innerHTML = '<div>Nessun giocatore</div>';
            return;
        }

        classificaOrdinata.forEach((riga, index) => {
            const div = document.createElement('div');
            div.className = 'classifica-item';

            const nome = riga.nome || '-';
            const capitale = Number(riga.capitale_attuale ?? 0);

            div.innerHTML = `
                <strong>${index + 1}.</strong>
                ${nome}
                <span>💰 ${capitale}</span>
            `;

            container.appendChild(div);
        });

    } catch (err) {
        console.error('Errore classifica:', err);
    }
}

/* ===============================
   BLOCCO LOGICO: UTILITY UI
   =============================== */
function hideAllScreens() {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.add('hidden');
        screen.style.display = 'none';
    });
}

function show(id) {
    const screen = document.getElementById(id);
    if (!screen) return;

    screen.classList.remove('hidden');
    screen.style.display = 'block';
}
