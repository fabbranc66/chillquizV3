// 09_classifica.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;

  function getImmediateResult() {
    return (S.lastImmediateResult && typeof S.lastImmediateResult === 'object')
      ? S.lastImmediateResult
      : null;
  }

  function setImmediateResult(result) {
    S.lastImmediateResult = (result && typeof result === 'object') ? result : null;
  }

  function clearImmediateResult() {
    S.lastImmediateResult = null;
  }

  function isMiaRigaClassifica(riga) {
    if (!riga || typeof riga !== 'object') return false;

    if (Number(S.partecipazioneId || 0) > 0) {
      return Number(riga.partecipazione_id || 0) === Number(S.partecipazioneId || 0);
    }

    const nomeGiocatore = ((D.displayName && D.displayName.innerText) || '').trim().toLowerCase();
    if (!nomeGiocatore) return false;

    return (riga.nome || '').trim().toLowerCase() === nomeGiocatore;
  }

  function getMiaRigaClassifica(lista) {
    if (!Array.isArray(lista) || lista.length === 0) return null;
    return lista.find(isMiaRigaClassifica) || null;
  }

  function aggiornaCapitaleDaClassifica(lista) {
    const miaRiga = getMiaRigaClassifica(lista);
    if (!miaRiga) return;

    const capitale = parseIntegerLike(miaRiga.capitale_attuale ?? 0);
    if (Player.utils && typeof Player.utils.setCapitaleRaw === 'function') {
      Player.utils.setCapitaleRaw(capitale);
      return;
    }

    if (D.capitaleValue) D.capitaleValue.innerText = formatNumber(capitale);
  }

  function formatTempoRisposta(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '-';
    return numeric.toFixed(2).replace('.', ',');
  }

  function rowLine(label, value, right = false, extraClass = '') {
    const className = String(extraClass || '').trim();
    return `<div class="risultato-row${right ? ' row-right' : ''}${className ? ` ${className}` : ''}"><span class="riga-testo">${label}: ${value}</span></div>`;
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

  function parseIntegerLike(value) {
    if (typeof value === 'number') {
      return Number.isFinite(value) ? Math.trunc(value) : 0;
    }

    const normalized = String(value ?? '').trim().replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return 0;

    const parsed = Number.parseInt(normalized, 10);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function formatNumber(value) {
    const numeric = parseIntegerLike(value);
    const sign = numeric < 0 ? '-' : '';
    const abs = Math.abs(numeric);
    const grouped = String(abs).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${sign}${grouped}`;
  }

  function formatSignedPoints(value) {
    const numeric = parseIntegerLike(value);
    return numeric >= 0 ? `+${formatNumber(numeric)}` : `${formatNumber(numeric)}`;
  }

  function formatCapitaleBreakdown(capitaleAttuale, vincitaTotale, pointsSymbol = '&#9733;') {
    const capitale = Number(capitaleAttuale);
    const vincita = Number(vincitaTotale);

    if (!Number.isFinite(capitale) || !Number.isFinite(vincita)) {
      return `${pointsSymbol} ${Number.isFinite(capitale) ? formatNumber(capitale) : 0}`;
    }

    const capitalePrecedente = capitale - vincita;
    return `${pointsSymbol} ${formatNumber(capitalePrecedente)} ${formatSignedPoints(vincita)} = ${pointsSymbol} ${formatNumber(capitale)}`;
  }

  Player.classificaSupport = {
    getImmediateResult,
    setImmediateResult,
    clearImmediateResult,
    isMiaRigaClassifica,
    getMiaRigaClassifica,
    aggiornaCapitaleDaClassifica,
    formatTempoRisposta,
    rowLine,
    forceDisplayString,
    formatCoeff,
    formatNumber,
    formatSignedPoints,
    formatCapitaleBreakdown,
  };
})();
