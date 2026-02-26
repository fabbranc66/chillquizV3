/**
 * FILE: public/assets/js/admin.js
 * SCOPO: Gestione UI regia/admin (stato sessione, comandi admin, classifica live, join richieste, log operazioni).
 * UTILIZZATO DA: app/Views/admin/index.php tramite <script src="/chillquizV3/public/assets/js/admin.js"></script>.
 * CHIAMATO DA: Browser (caricamento pagina admin), setInterval scheduler, eventi click sui pulsanti.
 *
 * METODI PRINCIPALI E CONSUMATORI:
 * - aggiornaStato(): chiamato da bootstrap iniziale + scheduler 1s.
 * - callAdmin(action): chiamato dai pulsanti fase/sistema.
 * - nuovaSessione(): chiamato da btnNuova.
 * - aggiornaJoinRichieste()/gestisciJoin(): polling + azioni approva/rifiuta.
 * - aggiornaUI(sessione): chiamato da aggiornaStato() per render centralizzato.
 * - renderClassificaLive(lista): chiamato da aggiornaUI().
 */

/* ===============================
   BLOCCO LOGICO: BOOTSTRAP CONFIG
   =============================== */
const ADMIN_BOOTSTRAP = window.ADMIN_BOOTSTRAP || {};
let SESSIONE_ID = Number(ADMIN_BOOTSTRAP.sessioneId || 0);

const ADMIN_TOKEN = String(ADMIN_BOOTSTRAP.adminToken || 'SUPERSEGRETO123');
const API_BASE = String(ADMIN_BOOTSTRAP.apiBase || 'index.php?url=api');

/* ===============================
   BLOCCO LOGICO: BIND DOM STATICO
   =============================== */
const sessioneIdSpan   = document.getElementById('sessione-id');
const domandaNumero    = document.getElementById('domanda-numero');
const partecipantiSpan = document.getElementById('partecipanti-numero');
const timerIndicator   = document.getElementById('timer-indicator');

const btnNuova      = document.getElementById('btnNuova');
const btnPuntata    = document.getElementById('btnPuntata');
const btnDomanda    = document.getElementById('btnDomanda');
const btnRisultati  = document.getElementById('btnRisultati');
const btnProssima   = document.getElementById('btnProssima');
const btnRiavvia    = document.getElementById('btnRiavvia');
const btnSchermo    = document.getElementById('btnSchermo');
const btnMedia      = document.getElementById('btnMedia');
const btnSettings   = document.getElementById('btnSettings');
const btnClearLog   = document.getElementById('btnClearLog');

const statoDiv    = document.getElementById('stato');
const conclusaDiv = document.getElementById('conclusa');
const logEl       = document.getElementById('log');
const classificaLiveEl = document.getElementById('classifica-live');
const joinRichiesteEl = document.getElementById('join-richieste');

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

function escapeHtml(value) {
    const div = document.createElement('div');
    div.innerText = value ?? '';
    return div.innerHTML;
}

function renderClassificaLive(lista) {
    if (!Array.isArray(lista) || lista.length === 0) {
        classificaLiveEl.innerHTML = '<tr><td colspan="7">Nessun partecipante</td></tr>';
        return;
    }

    const primoVeloceCorretto = lista
        .filter((p) => p.esito === 'corretta' && p.tempo_risposta !== null && p.tempo_risposta !== undefined)
        .reduce((best, p) => {
            if (!best) return p;
            return Number(p.tempo_risposta) < Number(best.tempo_risposta) ? p : best;
        }, null);

    classificaLiveEl.innerHTML = lista.map((p, index) => {
        const capitale = Number(p.capitale_attuale ?? 0);
        const puntata = Number(p.ultima_puntata ?? 0);
        const esito = p.esito ?? '-';
        const tempo = (p.tempo_risposta === null || p.tempo_risposta === undefined) ? '-' : Number(p.tempo_risposta);
        const vincita = (p.vincita_domanda === null || p.vincita_domanda === undefined) ? '-' : Number(p.vincita_domanda);
        const isPrimoVincente = primoVeloceCorretto && Number(primoVeloceCorretto.partecipazione_id) === Number(p.partecipazione_id);
        const rowClass = isPrimoVincente ? 'live-row-primo' : '';
        const nomeSafe = escapeHtml(p.nome ?? '-');
        const nomeConIcona = isPrimoVincente
            ? `<span class="first-win-icon" title="Primo a rispondere correttamente">🥇⚡</span>${nomeSafe}`
            : nomeSafe;

        return `
            <tr class="${rowClass}">
                <td>${index + 1}</td>
                <td>${nomeConIcona}<\/td>
                <td>${capitale}<\/td>
                <td>${puntata}<\/td>
                <td>${esito}<\/td>
                <td>${tempo}<\/td>
                <td>${vincita}<\/td>
            </tr>
        `;
    }).join('');
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
    setButton(btnSchermo, true);
    setButton(btnMedia, true);
    setButton(btnSettings, true);

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
        renderClassificaLive(lista);

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
        aggiornaJoinRichieste();

    } catch (e) {
        // silenzioso
    }
}

function renderJoinRichieste(lista) {
    if (!Array.isArray(lista) || lista.length === 0) {
        joinRichiesteEl.innerHTML = '<div class="join-item">Nessuna richiesta pending</div>';
        return;
    }

    joinRichiesteEl.innerHTML = lista.map((r) => `
        <div class="join-item">
            <div>
                <strong>${r.nome}</strong> · richiesta #${r.id}
            </div>
            <div class="join-actions">
                <button class="btn-join-ok" onclick="gestisciJoin(${r.id}, 'approva-join')">Approva</button>
                <button class="btn-join-no" onclick="gestisciJoin(${r.id}, 'rifiuta-join')">Rifiuta</button>
            </div>
        </div>
    `).join('');
}

async function aggiornaJoinRichieste() {
    const res = await fetch(`${API_BASE}/admin/join-richieste/${SESSIONE_ID}`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        }
    });

    const data = await res.json();

    if (!data.success) {
        return;
    }

    renderJoinRichieste(data.richieste ?? []);
}

async function gestisciJoin(requestId, action) {
    const formData = new FormData();
    formData.append('request_id', requestId);

    const res = await fetch(`${API_BASE}/admin/${action}/${SESSIONE_ID}`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        },
        body: formData
    });

    const data = await res.json();

    addLog({
        ok: !!data.success,
        title: action === 'approva-join' ? 'Join approvata' : 'Join rifiutata',
        message: data.success
            ? `Richiesta #${requestId} ${action === 'approva-join' ? 'approvata' : 'rifiutata'}`
            : (data.error || 'Operazione fallita'),
        data
    });

    if (data.success) {
        aggiornaJoinRichieste();
        aggiornaPartecipanti();
    }
}

window.gestisciJoin = gestisciJoin;

function aggiornaTimer(sessione) {

    if (timerInterval) clearInterval(timerInterval);

    const max = Number(sessione.timer_max || 0);
    const start = Number(sessione.timer_start || 0);

    if (max <= 0 || start <= 0 || sessione.stato !== 'domanda') {
        timerIndicator.style.setProperty('--progress', '0deg');
        return;
    }

    function tick() {
        const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - start);
        const remaining = Math.max(0, max - elapsed);

        const pct = max > 0 ? (remaining / max) : 0;
        const deg = Math.max(0, Math.min(360, pct * 360));

        timerIndicator.style.setProperty('--progress', `${deg}deg`);

        if (remaining <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    tick();
    timerInterval = setInterval(tick, 250);
}

async function callAdmin(action) {
    const res = await fetch(`${API_BASE}/admin/${action}/${SESSIONE_ID}`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        }
    });

    const data = await res.json();

    addLog({
        ok: !!data.success,
        title: action,
        message: data.success
            ? `Azione "${action}" eseguita`
            : (data.error ? `Errore: ${data.error}` : `Errore sconosciuto`),
        data
    });

    aggiornaStato();
    aggiornaJoinRichieste();
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
        title: 'nuova-sessione',
        message: data.success
            ? `Creata nuova sessione: ${data.sessione_id}`
            : (data.error ? `Errore: ${data.error}` : `Errore sconosciuto`),
        data
    });

    if (data.success) {
        SESSIONE_ID = data.sessione_id;
        aggiornaStato();
        aggiornaJoinRichieste();
    }
}

function apriMedia() {
    const url = new URL(window.location.href);
    url.searchParams.set('url', 'admin/media');
    window.open(url.toString(), '_blank', 'noopener,noreferrer');

    addLog({
        ok: true,
        title: 'Media',
        message: 'Aperta gestione media',
        data: {}
    });
}

function apriSchermo() {
    const url = new URL(window.location.href);
    url.searchParams.set('url', `screen/${SESSIONE_ID}`);
    window.open(url.toString(), '_blank', 'noopener,noreferrer');

    addLog({
        ok: true,
        title: 'Screen',
        message: `Schermo attivato per sessione ${SESSIONE_ID}`,
        data: { sessione_id: SESSIONE_ID }
    });
}


function apriSettings() {
    const url = new URL(window.location.href);
    url.searchParams.set('url', 'admin/settings');
    window.open(url.toString(), '_blank', 'noopener,noreferrer');

    addLog({
        ok: true,
        title: 'Settings',
        message: 'Aperto pannello settings',
        data: {}
    });
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
btnSchermo.onclick   = apriSchermo;
btnMedia.onclick     = apriMedia;
btnSettings.onclick  = apriSettings;

btnClearLog.onclick  = clearLog;

/* ===== start ===== */
setInterval(aggiornaStato, 1000);
setInterval(aggiornaJoinRichieste, 2000);
aggiornaStato();
aggiornaJoinRichieste();
