// 09a_classifica_render.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const POINTS_SYMBOL = '&#9733;';
  const Copy = Player.copy;
  const Support = Player.classificaSupport;

  function renderRisultatoPersonaleDaClassifica(lista) {
    const container = D.risultatoPersonale;
    if (!container) return;

    const immediateResult = Support.getImmediateResult();
    if (immediateResult) {
      renderRisultatoPersonaleImmediato(immediateResult);
      return;
    }

    const miaRiga = Support.getMiaRigaClassifica(lista);

    if (!miaRiga) {
      container.classList.remove('esito-corretta', 'esito-errata');
      container.innerHTML = `<div>${Copy.personalResultUnavailable}</div>`;
      return;
    }

    const esito = miaRiga.esito ?? '-';
    const puntata = miaRiga.ultima_puntata ?? 0;
    const vincitaDifficolta = miaRiga.vincita_difficolta ?? 0;
    const vincitaVelocita = miaRiga.vincita_velocita ?? 0;
    const bonusPrimo = miaRiga.bonus_primo ?? 0;
    const bonusImpostore = miaRiga.bonus_impostore ?? 0;
    const vincitaTotale = (miaRiga.vincita_domanda === null || miaRiga.vincita_domanda === undefined)
      ? '-'
      : (miaRiga.vincita_domanda);
    const tempo = Support.forceDisplayString(miaRiga.tempo_risposta_display || Support.formatTempoRisposta(miaRiga.tempo_risposta));
    const capitale = miaRiga.capitale_attuale ?? 0;
    container.classList.remove('esito-corretta', 'esito-errata');
    if (esito === 'corretta') container.classList.add('esito-corretta');
    else if (esito === 'errata') container.classList.add('esito-errata');

    container.innerHTML = [
      Support.rowLine('Esito', esito),
      Support.rowLine('Tempo risposta', tempo),
      Support.rowLine('Puntata', `${POINTS_SYMBOL} ${Support.formatNumber(puntata)}`),
      Support.rowLine('Vincita difficolta', `${Support.formatNumber(vincitaDifficolta)}`, true),
      Support.rowLine('Vincita velocita', `${Support.formatNumber(vincitaVelocita)}`, true),
      Support.rowLine('Bonus primo', `${Support.formatNumber(bonusPrimo)}`, true),
      Support.rowLine('Bonus impostore', `${Support.formatNumber(bonusImpostore)}`, true),
      Support.rowLine('Vincita totale', `${vincitaTotale === '-' ? '-' : Support.formatNumber(vincitaTotale)}`, true, 'row-vincita-totale'),
      Support.rowLine('Punti', `${POINTS_SYMBOL} ${Support.formatNumber(capitale)}`, false, 'row-punti-totali'),
    ].join('');
  }

  function renderRisultatoPersonaleImmediato(risultato) {
    const container = D.risultatoPersonale;
    if (!container || !risultato || typeof risultato !== 'object') return;
    Support.setImmediateResult(risultato);

    const esito = risultato.corretta ? 'corretta' : 'errata';
    const puntata = risultato.puntata ?? 0;
    const vincitaDifficolta = risultato.vincita_difficolta ?? 0;
    const vincitaVelocita = risultato.vincita_velocita ?? 0;
    const bonusPrimo = risultato.bonus_primo ?? 0;
    const bonusImpostore = risultato.bonus_impostore ?? 0;
    const punti = risultato.punti ?? 0;
    const tempo = Support.forceDisplayString(risultato.tempo_risposta_display || Support.formatTempoRisposta(risultato.tempo_risposta ?? 0));
    const capitale = risultato.capitale ?? 0;
    container.classList.remove('esito-corretta', 'esito-errata');
    container.classList.add(risultato.corretta ? 'esito-corretta' : 'esito-errata');

    container.innerHTML = [
      Support.rowLine('Esito', esito),
      Support.rowLine('Tempo risposta', tempo),
      Support.rowLine('Puntata', `${POINTS_SYMBOL} ${Support.formatNumber(puntata)}`),
      Support.rowLine('Vincita difficolta', `${Support.formatNumber(vincitaDifficolta)}`, true),
      Support.rowLine('Vincita velocita', `${Support.formatNumber(vincitaVelocita)}`, true),
      Support.rowLine('Bonus primo', `${Support.formatNumber(bonusPrimo)}`, true),
      Support.rowLine('Bonus impostore', `${Support.formatNumber(bonusImpostore)}`, true),
      Support.rowLine('Vincita totale', `${Support.formatNumber(punti)}`, true, 'row-vincita-totale'),
      Support.rowLine('Punti', `${POINTS_SYMBOL} ${Support.formatNumber(capitale)}`, false, 'row-punti-totali'),
    ].join('');
  }

  async function fetchClassifica() {
    try {
      const response = await fetch(`${S.API_BASE}/classifica/${S.sessioneId || 0}`);
      const data = await response.json();
      if (!data.success) return;

      const classificaOrdinata = Array.isArray(data.classifica)
        ? [...data.classifica].sort((a, b) => {
          const parseScore = (value) => {
            const direct = Number(value);
            if (Number.isFinite(direct)) return direct;
            const normalized = String(value ?? '').replace(/[^\d-]/g, '');
            if (normalized === '' || normalized === '-') return 0;
            const parsed = Number.parseInt(normalized, 10);
            return Number.isFinite(parsed) ? parsed : 0;
          };
          return parseScore(b.capitale_attuale ?? 0) - parseScore(a.capitale_attuale ?? 0);
        })
        : [];

      Support.aggiornaCapitaleDaClassifica(classificaOrdinata);
      renderRisultatoPersonaleDaClassifica(classificaOrdinata);

      const container = D.classifica;
      if (!container) return;

      container.innerHTML = '';

      if (classificaOrdinata.length === 0) {
        container.innerHTML = `<div>${Copy.noPlayers}</div>`;
        return;
      }

      classificaOrdinata.forEach((riga, index) => {
        const div = document.createElement('div');
        div.className = 'classifica-item';

        const nome = riga.nome || '-';
        const capitale = riga.capitale_attuale ?? 0;
        const isMe = typeof Support.isMiaRigaClassifica === 'function'
          ? Support.isMiaRigaClassifica(riga)
          : false;

        if (isMe) {
          div.classList.add('classifica-item--me');
          div.setAttribute('aria-current', 'true');
          div.title = 'La tua posizione in classifica';
        }

        const nomeLabel = `<span class="classifica-name${isMe ? ' classifica-name--me' : ''}">${nome}</span>`;

        div.innerHTML = `
          <strong>${index + 1}.</strong>
          ${nomeLabel}
          <span>${POINTS_SYMBOL} ${Support.formatNumber(capitale)}</span>
        `;

        container.appendChild(div);
      });
    } catch (err) {
      console.error('Errore classifica:', err);
    }
  }

  Player.classifica = {
    fetchClassifica,
    renderRisultatoPersonaleImmediato,
    renderRisultatoPersonaleDaClassifica,
    setImmediateResult: Support.setImmediateResult,
    clearImmediateResult: Support.clearImmediateResult,
    aggiornaCapitaleDaClassifica: Support.aggiornaCapitaleDaClassifica,
    getMiaRigaClassifica: Support.getMiaRigaClassifica,
  };
})();
