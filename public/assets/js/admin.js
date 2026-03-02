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
let NOME_SESSIONE = String(ADMIN_BOOTSTRAP.nomeSessione || '').trim();

const ADMIN_TOKEN = String(ADMIN_BOOTSTRAP.adminToken || 'SUPERSEGRETO123');
const API_BASE = String(ADMIN_BOOTSTRAP.apiBase || 'index.php?url=api');

/* ===============================
   BLOCCO LOGICO: BIND DOM STATICO
   =============================== */
const sessioneIdSpan   = document.getElementById('sessione-id');
const sessioneNomeSpan = document.getElementById('sessione-nome-display');
const domandaNumero    = document.getElementById('domanda-numero');
const partecipantiSpan = document.getElementById('partecipanti-numero');
const timerIndicator   = document.getElementById('timer-indicator');

const inputSessioneNome = document.getElementById('sessione-nome');
const btnNuova      = document.getElementById('btnNuova');
const btnSetCorrente = document.getElementById('btnSetCorrente');
const sessioneSelect = document.getElementById('sessione-select');
const btnToggleDomandeSessione = document.getElementById('btnToggleDomandeSessione');
const domandeSessioneWrapper = document.getElementById('domande-sessione-wrapper');
const domandeSessioneList = document.getElementById('domande-sessione-list');
const btnPuntata    = document.getElementById('btnPuntata');
const btnDomanda    = document.getElementById('btnDomanda');
const btnRisultati  = document.getElementById('btnRisultati');
const btnProssima   = document.getElementById('btnProssima');
const btnRiavvia    = document.getElementById('btnRiavvia');
const btnSchermo    = document.getElementById('btnSchermo');
const btnMedia      = document.getElementById('btnMedia');
const btnSettings   = document.getElementById('btnSettings');
const btnQuizConfigV2 = document.getElementById('btnQuizConfigV2');
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
    const nomeSessione = String(sessione?.nome_sessione || sessione?.nome || sessione?.titolo || '').trim();
    if (nomeSessione !== '') {
        NOME_SESSIONE = nomeSessione;
    }

    if (sessioneNomeSpan) {
        sessioneNomeSpan.textContent = NOME_SESSIONE !== ''
            ? NOME_SESSIONE
            : `Sessione nr ${SESSIONE_ID} del ${new Date().toLocaleDateString('it-IT')}`;
    }
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
    setButton(btnQuizConfigV2, true);

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


function nomeSessioneFromRecord(sessione) {
    const raw = String(sessione?.nome_sessione || sessione?.nome || sessione?.titolo || '').trim();
    if (raw !== '') return raw;

    const id = Number(sessione?.id || 0);
    const creata = Number(sessione?.creata_il || 0);
    if (creata > 0) {
        return `Sessione ${id || ''} ${new Date(creata * 1000).toLocaleString('it-IT')}`.trim();
    }

    return id > 0 ? `Sessione ${id}` : 'Sessione';
}

async function caricaSessioni() {
    if (!sessioneSelect) return;

    const res = await fetch(`${API_BASE}/admin/sessioni-lista/0`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        }
    });

    const data = await res.json();
    if (!data.success) return;

    const lista = Array.isArray(data.sessioni) ? data.sessioni : [];
    sessioneSelect.innerHTML = lista.map((s) => {
        const id = Number(s.id || 0);
        const nome = escapeHtml(nomeSessioneFromRecord(s));
        return `<option value="${id}">${id} · ${nome}</option>`;
    }).join('');

    const correnteId = Number(data.sessione_corrente_id || SESSIONE_ID || 0);
    if (correnteId > 0) {
        sessioneSelect.value = String(correnteId);
    }
}

async function impostaSessioneCorrente() {
    if (!sessioneSelect) return;

    const targetId = Number(sessioneSelect.value || 0);
    if (targetId <= 0) {
        addLog({ ok: false, title: 'set-corrente', message: 'Seleziona una sessione valida', data: {} });
        return;
    }

    const formData = new FormData();
    formData.append('sessione_id', String(targetId));

    const res = await fetch(`${API_BASE}/admin/set-corrente/0`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        },
        body: formData
    });

    const data = await res.json();

    addLog({
        ok: !!data.success,
        title: 'set-corrente',
        message: data.success ? `Sessione corrente impostata: ${targetId}` : (data.error || 'Operazione fallita'),
        data
    });

    if (data.success) {
        SESSIONE_ID = targetId;
        await aggiornaStato();
        await aggiornaJoinRichieste();
        await caricaSessioni();
        if (domandeSessioneWrapper && domandeSessioneWrapper.style.display !== 'none') {
            await caricaDomandeSessione(targetId);
        }
    }
}



async function caricaDomandeSessione(sessioneId) {
    if (!domandeSessioneList) return;

    const formData = new FormData();
    formData.append('sessione_id', String(Number(sessioneId || 0)));

    const res = await fetch(`${API_BASE}/admin/domande-sessione/0`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        },
        body: formData
    });

    const data = await res.json();
    if (!data.success) {
        domandeSessioneList.innerHTML = `<div>Errore caricamento domande: ${escapeHtml(data.error || 'errore sconosciuto')}</div>`;
        return;
    }

    const domande = Array.isArray(data.domande) ? data.domande : [];
    if (domande.length === 0) {
        domandeSessioneList.innerHTML = 'Nessuna domanda caricata';
        return;
    }

    domandeSessioneList.innerHTML = domande.map((d) => {
        const posizione = Number(d.posizione || 0);
        const id = Number(d.domanda_id || 0);
        const testo = escapeHtml(String(d.testo || ''));
        return `<div style="padding:4px 0; border-bottom:1px solid #222;">#${posizione} · [${id}] ${testo}</div>`;
    }).join('');
}

function toggleDomandeSessione() {
    if (!domandeSessioneWrapper) return;

    const isHidden = domandeSessioneWrapper.style.display === 'none' || domandeSessioneWrapper.style.display === '';
    domandeSessioneWrapper.style.display = isHidden ? 'block' : 'none';

    if (isHidden) {
        const sessioneId = Number(sessioneSelect?.value || SESSIONE_ID || 0);
        caricaDomandeSessione(sessioneId);
    }
}
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
    const nomeSessione = String(inputSessioneNome?.value || '').trim();
    const formData = new FormData();

    if (nomeSessione !== '') {
        formData.append('nome', nomeSessione);
    }

    const res = await fetch(`${API_BASE}/admin/nuova-sessione/0`, {
        method: 'POST',
        headers: {
            'X-ADMIN-TOKEN': ADMIN_TOKEN
        },
        body: formData
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

        const nomeRisposta = String(data.nome_sessione || '').trim();
        if (nomeRisposta !== '') {
            NOME_SESSIONE = nomeRisposta;
        } else if (nomeSessione !== '') {
            NOME_SESSIONE = nomeSessione;
        } else {
            NOME_SESSIONE = '';
        }

        if (inputSessioneNome) {
            inputSessioneNome.value = '';
        }
        aggiornaStato();
        aggiornaJoinRichieste();
        caricaSessioni();
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


function apriQuizConfigV2() {
    const url = new URL(window.location.href);
    url.searchParams.set('url', 'admin/quizConfigV2');
    window.open(url.toString(), '_blank', 'noopener,noreferrer');

    addLog({
        ok: true,
        title: 'Quiz Config V2',
        message: 'Aperto pannello SQL/API Quiz Config V2',
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
if (btnSetCorrente) {
    btnSetCorrente.onclick = impostaSessioneCorrente;
}
if (btnToggleDomandeSessione) {
    btnToggleDomandeSessione.onclick = toggleDomandeSessione;
}
if (sessioneSelect) {
    sessioneSelect.onchange = () => {
        if (domandeSessioneWrapper && domandeSessioneWrapper.style.display !== 'none') {
            caricaDomandeSessione(Number(sessioneSelect.value || 0));
        }
    };
}
btnPuntata.onclick   = () => callAdmin('avvia-puntata');
btnDomanda.onclick   = () => callAdmin('avvia-domanda');
btnRisultati.onclick = () => callAdmin('risultati');
btnProssima.onclick  = () => callAdmin('prossima');
btnRiavvia.onclick   = () => callAdmin('riavvia');
btnSchermo.onclick   = apriSchermo;
btnMedia.onclick     = apriMedia;
btnSettings.onclick  = apriSettings;
if (btnQuizConfigV2) {
    btnQuizConfigV2.onclick = apriQuizConfigV2;
}

btnClearLog.onclick  = clearLog;

/* ===== start ===== */
setInterval(aggiornaStato, 1000);
setInterval(aggiornaJoinRichieste, 2000);
aggiornaStato();
aggiornaJoinRichieste();
caricaSessioni();