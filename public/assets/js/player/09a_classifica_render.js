// 09a_classifica_render.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const POINTS_SYMBOL = '&#9733;';
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
      container.innerHTML = '<div>Risultato personale non disponibile.</div>';
      return;
    }

    const esito = miaRiga.esito ?? '-';
    const puntata = Number(miaRiga.ultima_puntata ?? 0);
    const difficolta = Number(miaRiga.difficolta_domanda ?? 0);
    const fattoreVelocita = Number(miaRiga.fattore_velocita ?? 0);
    const vincitaDifficolta = Number(miaRiga.vincita_difficolta ?? 0);
    const vincitaVelocita = Number(miaRiga.vincita_velocita ?? 0);
    const bonusPrimo = Number(miaRiga.bonus_primo ?? 0);
    const bonusImpostore = Number(miaRiga.bonus_impostore ?? 0);
    const vincitaTotale = (miaRiga.vincita_domanda === null || miaRiga.vincita_domanda === undefined)
      ? '-'
      : Number(miaRiga.vincita_domanda);
    const tempo = Support.forceDisplayString(miaRiga.tempo_risposta_display || Support.formatTempoRisposta(miaRiga.tempo_risposta));
    const capitale = Number(miaRiga.capitale_attuale ?? 0);
    const rispostaData = Support.forceDisplayString(miaRiga.risposta_data_testo || '-');
    const rispostaCorretta = Support.forceDisplayString(miaRiga.risposta_corretta_testo || '-');

    container.classList.remove('esito-corretta', 'esito-errata');
    if (esito === 'corretta') container.classList.add('esito-corretta');
    else if (esito === 'errata') container.classList.add('esito-errata');

    container.innerHTML = [
      Support.rowLine('Esito', esito),
      Support.rowLine('Risposta data', rispostaData),
      Support.rowLine('Risposta corretta', rispostaCorretta),
      Support.rowLine('Tempo risposta', tempo),
      Support.rowLine('Puntata', `${POINTS_SYMBOL} ${puntata}`),
      Support.rowLine('Coeff. difficolta', `x${Support.formatCoeff(difficolta)}`),
      Support.rowLine('Vincita difficolta', `${vincitaDifficolta}`, true),
      Support.rowLine('Coeff. velocita', `x${Support.formatCoeff(fattoreVelocita)}`),
      Support.rowLine('Vincita velocita', `${vincitaVelocita}`, true),
      Support.rowLine('Bonus primo', `${bonusPrimo}`, true),
      Support.rowLine('Bonus impostore', `${bonusImpostore}`, true),
      Support.rowLine('Vincita totale', `${vincitaTotale}`, true),
      Support.rowLine('Punti attuali', Support.formatCapitaleBreakdown(capitale, vincitaTotale, POINTS_SYMBOL)),
    ].join('');
  }

  function renderRisultatoPersonaleImmediato(risultato) {
    const container = D.risultatoPersonale;
    if (!container || !risultato || typeof risultato !== 'object') return;
    Support.setImmediateResult(risultato);

    const esito = risultato.corretta ? 'corretta' : 'errata';
    const puntata = Number(risultato.puntata ?? 0);
    const difficolta = Number(risultato.difficolta_domanda ?? 0);
    const fattoreVelocita = Number(risultato.fattore_velocita ?? 0);
    const vincitaDifficolta = Number(risultato.vincita_difficolta ?? 0);
    const vincitaVelocita = Number(risultato.vincita_velocita ?? 0);
    const bonusPrimo = Number(risultato.bonus_primo ?? 0);
    const bonusImpostore = Number(risultato.bonus_impostore ?? 0);
    const punti = Number(risultato.punti ?? 0);
    const tempo = Support.forceDisplayString(risultato.tempo_risposta_display || Support.formatTempoRisposta(risultato.tempo_risposta ?? 0));
    const capitale = Number(risultato.capitale ?? 0);
    const rispostaData = Support.forceDisplayString(risultato.risposta_data_testo || '-');
    const rispostaCorretta = Support.forceDisplayString(risultato.risposta_corretta_testo || '-');

    container.classList.remove('esito-corretta', 'esito-errata');
    container.classList.add(risultato.corretta ? 'esito-corretta' : 'esito-errata');

    container.innerHTML = [
      Support.rowLine('Esito', esito),
      Support.rowLine('Risposta data', rispostaData),
      Support.rowLine('Risposta corretta', rispostaCorretta),
      Support.rowLine('Tempo risposta', tempo),
      Support.rowLine('Puntata', `${POINTS_SYMBOL} ${puntata}`),
      Support.rowLine('Coeff. difficolta', `x${Support.formatCoeff(difficolta)}`),
      Support.rowLine('Vincita difficolta', `${vincitaDifficolta}`, true),
      Support.rowLine('Coeff. velocita', `x${Support.formatCoeff(fattoreVelocita)}`),
      Support.rowLine('Vincita velocita', `${vincitaVelocita}`, true),
      Support.rowLine('Bonus primo', `${bonusPrimo}`, true),
      Support.rowLine('Bonus impostore', `${bonusImpostore}`, true),
      Support.rowLine('Vincita totale', `${punti}`, true),
      Support.rowLine('Punti attuali', Support.formatCapitaleBreakdown(capitale, punti, POINTS_SYMBOL)),
    ].join('');
  }

  async function fetchClassifica() {
    try {
      const response = await fetch(`${S.API_BASE}/classifica/${S.sessioneId || 0}`);
      const data = await response.json();
      if (!data.success) return;

      const classificaOrdinata = Array.isArray(data.classifica)
        ? [...data.classifica].sort((a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0))
        : [];

      Support.aggiornaCapitaleDaClassifica(classificaOrdinata);
      renderRisultatoPersonaleDaClassifica(classificaOrdinata);

      const container = D.classifica;
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
          <span>${POINTS_SYMBOL} ${capitale}</span>
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
