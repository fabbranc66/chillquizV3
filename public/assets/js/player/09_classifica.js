// 09_classifica.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;

  function getMiaRigaClassifica(lista) {
    if (!Array.isArray(lista) || lista.length === 0) return null;

    if (S.partecipazioneId) {
      const byId = lista.find((r) => Number(r.partecipazione_id || 0) === Number(S.partecipazioneId));
      if (byId) return byId;
    }

    const nomeGiocatore = (D.displayName?.innerText || '').trim().toLowerCase();
    if (!nomeGiocatore) return null;

    return lista.find((r) => (r.nome || '').trim().toLowerCase() === nomeGiocatore) || null;
  }

  function aggiornaCapitaleDaClassifica(lista) {
    const miaRiga = getMiaRigaClassifica(lista);
    if (!miaRiga) return;

    const capitale = Number(miaRiga.capitale_attuale ?? 0);
    if (D.capitaleValue) D.capitaleValue.innerText = String(capitale);
  }

  function formatTempoRisposta(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '-';
    return numeric.toFixed(2).replace('.', ',');
  }

  function rowLine(label, value, right = false) {
    return `<div class="risultato-row${right ? ' row-right' : ''}"><span class="riga-testo">${label}: ${value}</span></div>`;
  }

  function forceDisplayString(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    return String(value);
  }

  function formatCoeff(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0.00';
    return numeric.toFixed(2).replace('.', ',');
  }

  function formatSignedPoints(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0';
    return numeric >= 0 ? `+${numeric}` : `${numeric}`;
  }

  function formatCapitaleBreakdown(capitaleAttuale, vincitaTotale, pointsSymbol = '&#9733;') {
    const capitale = Number(capitaleAttuale);
    const vincita = Number(vincitaTotale);

    if (!Number.isFinite(capitale) || !Number.isFinite(vincita)) {
      return `${pointsSymbol} ${Number.isFinite(capitale) ? capitale : 0}`;
    }

    const capitalePrecedente = capitale - vincita;
    return `${pointsSymbol} ${capitalePrecedente} ${formatSignedPoints(vincita)} = ${pointsSymbol} ${capitale}`;
  }

  Player.classificaSupport = {
    getMiaRigaClassifica,
    aggiornaCapitaleDaClassifica,
    formatTempoRisposta,
    rowLine,
    forceDisplayString,
    formatCoeff,
    formatSignedPoints,
    formatCapitaleBreakdown,
  };
})();
