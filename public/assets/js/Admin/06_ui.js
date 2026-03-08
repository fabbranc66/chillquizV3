// admin/06_ui.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;

  Admin.ui = {
    setButton(button, enabled) {
      if (!button) return;
      if (enabled) {
        button.classList.remove('disabled');
        button.classList.add('enabled');
        button.disabled = false;
      } else {
        button.classList.remove('enabled');
        button.classList.add('disabled');
        button.disabled = true;
      }
    },

    aggiornaTimer(sessione) {
      if (S.timerInterval) clearInterval(S.timerInterval);

      const max = Number(sessione.timer_max || sessione.durata_domanda || 0);
      const start = Number(sessione.timer_start || sessione.inizio_domanda || 0);

      if (max <= 0 || start <= 0 || sessione.stato !== 'domanda') {
        if (D.timerIndicator) D.timerIndicator.style.setProperty('--progress', '0deg');
        return;
      }

      function tick() {
        const elapsed = Math.max(0, (Date.now() / 1000) - start);
        const remaining = Math.max(0, max - elapsed);

        const pct = max > 0 ? (remaining / max) : 0;
        const deg = Math.max(0, Math.min(360, pct * 360));

        if (D.timerIndicator) D.timerIndicator.style.setProperty('--progress', `${deg}deg`);

        if (remaining <= 0) {
          clearInterval(S.timerInterval);
          S.timerInterval = null;
        }
      }

      tick();
      S.timerInterval = setInterval(tick, 250);
    },

    aggiornaUI(sessione) {
      if (D.sessioneIdSpan) D.sessioneIdSpan.textContent = String(S.SESSIONE_ID);

      const nomeSessione = String(sessione?.nome_sessione || sessione?.nome || sessione?.titolo || '').trim();
      if (nomeSessione !== '') S.NOME_SESSIONE = nomeSessione;

      if (D.sessioneNomeSpan) {
        D.sessioneNomeSpan.textContent = S.NOME_SESSIONE !== ''
          ? S.NOME_SESSIONE
          : `Sessione nr ${S.SESSIONE_ID} del ${new Date().toLocaleDateString('it-IT')}`;
      }

      if (D.domandaNumero) D.domandaNumero.textContent = String(sessione.domanda_corrente);
      if (D.statoDiv) D.statoDiv.textContent = 'Stato: ' + sessione.stato;
      if (D.conclusaDiv) D.conclusaDiv.style.display = (sessione.stato === 'conclusa') ? 'block' : 'none';
      S.impostoreEnabled = !!sessione.impostore_enabled;

      Admin.ui.setButton(D.btnPuntata, sessione.stato === 'attesa' || sessione.stato === 'risultati');
      Admin.ui.setButton(D.btnDomanda, sessione.stato === 'puntata');
      Admin.ui.setButton(D.btnRisultati, sessione.stato === 'domanda');
      Admin.ui.setButton(D.btnProssima, sessione.stato === 'risultati');

      Admin.ui.setButton(D.btnNuova, true);
      Admin.ui.setButton(D.btnSalvaSessione, true);
      Admin.ui.setButton(D.btnRiavvia, true);
      Admin.ui.setButton(D.btnSchermo, true);
      Admin.ui.setButton(D.btnMedia, true);
      Admin.ui.setButton(D.btnSettings, true);
      Admin.ui.setButton(D.btnQuizConfigV2, true);

      if (D.btnImpostoreToggle) {
        const eligible = !!sessione.impostore_eligible;
        const locked = !!sessione.impostore_locked;
        const enabled = !!sessione.impostore_enabled;
        D.btnImpostoreToggle.textContent = enabled ? 'IMPOSTORE ON' : 'IMPOSTORE OFF';
        D.btnImpostoreToggle.disabled = !eligible || locked;
        D.btnImpostoreToggle.classList.toggle('enabled', enabled && eligible && !locked);
        D.btnImpostoreToggle.classList.toggle('disabled', !enabled || !eligible || locked);
        D.btnImpostoreToggle.title = !eligible
          ? 'Non disponibile per SARABANDA'
          : (locked ? 'Modificabile solo prima dello stato domanda' : 'Applica IMPOSTORE alla domanda corrente');
      }

      Admin.actions.aggiornaPartecipanti();
      Admin.ui.aggiornaTimer(sessione);
    }
  };
})();
