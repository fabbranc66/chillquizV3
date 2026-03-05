// admin/02_dom.js
(() => {
  const Admin = window.Admin;
  Admin.dom = {
    sessioneIdSpan: document.getElementById('sessione-id'),
    sessioneNomeSpan: document.getElementById('sessione-nome-display'),
    domandaNumero: document.getElementById('domanda-numero'),
    partecipantiSpan: document.getElementById('partecipanti-numero'),
    timerIndicator: document.getElementById('timer-indicator'),

    inputSessioneNome: document.getElementById('sessione-nome'),
    inputSessioneNumeroDomande: document.getElementById('sessione-numero-domande'),
    inputSessionePoolTipo: document.getElementById('sessione-pool-tipo'),
    inputSessioneArgomentoId: document.getElementById('sessione-argomento-id'),
    btnNuova: document.getElementById('btnNuova'),
    btnSetCorrente: document.getElementById('btnSetCorrente'),
    sessioneSelect: document.getElementById('sessione-select'),

    btnToggleDomandeSessione: document.getElementById('btnToggleDomandeSessione'),
    domandeSessioneWrapper: document.getElementById('domande-sessione-wrapper'),
    domandeSessioneList: document.getElementById('domande-sessione-list'),

    btnPuntata: document.getElementById('btnPuntata'),
    btnDomanda: document.getElementById('btnDomanda'),
    btnRisultati: document.getElementById('btnRisultati'),
    btnProssima: document.getElementById('btnProssima'),

    btnRiavvia: document.getElementById('btnRiavvia'),
    btnSchermo: document.getElementById('btnSchermo'),
    btnMedia: document.getElementById('btnMedia'),
    btnSettings: document.getElementById('btnSettings'),
    btnQuizConfigV2: document.getElementById('btnQuizConfigV2'),
    btnClearLog: document.getElementById('btnClearLog'),

    statoDiv: document.getElementById('stato'),
    conclusaDiv: document.getElementById('conclusa'),
    logEl: document.getElementById('log'),
    classificaLiveEl: document.getElementById('classifica-live'),
    joinRichiesteEl: document.getElementById('join-richieste'),
  };
})();
