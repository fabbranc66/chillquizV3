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
      S.currentSessionState = sessione || null;
      if (D.sessioneIdSpan) D.sessioneIdSpan.textContent = String(S.SESSIONE_ID);

      const nomeSessione = String(sessione?.nome_sessione || sessione?.nome || sessione?.titolo || '').trim();
      if (nomeSessione !== '') S.NOME_SESSIONE = nomeSessione;

      if (D.sessioneNomeSpan) {
        D.sessioneNomeSpan.textContent = S.NOME_SESSIONE !== ''
          ? S.NOME_SESSIONE
          : `Sessione nr ${S.SESSIONE_ID} del ${new Date().toLocaleDateString('it-IT')}`;
      }

      const statoRaw = String(sessione?.stato || '').trim();
      const statoLabel = statoRaw || 'preview';
      if (D.statoDiv) {
        D.statoDiv.classList.remove(
          'state-banner-attesa',
          'state-banner-puntata',
          'state-banner-preview',
          'state-banner-domanda',
          'state-banner-risultati',
          'state-banner-conclusa',
          'state-banner-pending'
        );
        D.statoDiv.classList.add(`state-banner-${statoLabel}`);
        const value = D.statoDiv.querySelector('.state-banner-value');
        if (value) value.textContent = statoLabel.toUpperCase();
      }
      S.impostoreEnabled = !!sessione.impostore_enabled;
      S.memeEnabled = !!sessione.meme_enabled;
      S.imagePartyEnabled = !!sessione.image_party_enabled;
      S.fadeEnabled = !!sessione.fade_enabled;
      S.sarabandaAudioEnabled = !!sessione.sarabanda_audio_enabled;
      S.sarabandaReverseEnabled = !!sessione.sarabanda_reverse_enabled;
      S.sarabandaBrokenRecordEnabled = !!sessione.sarabanda_broken_record_enabled;
      S.sarabandaFastForwardEnabled = !!sessione.sarabanda_fast_forward_enabled;
      S.sarabandaFastForwardRate = Number(sessione.sarabanda_fast_forward_rate || S.sarabandaFastForwardRate || 5);
      S.memeText = String(sessione.meme_text || '');

      Admin.ui.setButton(D.btnPuntata, sessione.stato === 'attesa' || sessione.stato === 'risultati');
      Admin.ui.setButton(D.btnDomanda, sessione.stato === 'puntata');
      Admin.ui.setButton(D.btnRisultati, sessione.stato === 'preview' || sessione.stato === 'domanda');
      Admin.ui.setButton(D.btnProssima, sessione.stato === 'risultati');

      Admin.ui.setButton(D.btnNuova, true);
      Admin.ui.setButton(D.btnSalvaSessione, true);
      Admin.ui.setButton(D.btnRiavvia, true);
      Admin.ui.setButton(D.btnSchermo, true);
      Admin.ui.setButton(D.btnMedia, true);
      Admin.ui.setButton(D.btnSettings, true);
      if (D.btnImpostoreToggle) {
        const eligible = !!sessione.impostore_eligible;
        const locked = !!sessione.impostore_locked;
        const enabled = !!sessione.impostore_enabled;
        D.btnImpostoreToggle.textContent = enabled ? 'IMPOSTORE ON' : 'IMPOSTORE OFF';
        D.btnImpostoreToggle.disabled = !eligible || locked;
        D.btnImpostoreToggle.classList.toggle('enabled', enabled);
        D.btnImpostoreToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnImpostoreToggle.classList.toggle('is-locked', locked);
        D.btnImpostoreToggle.title = !eligible
          ? 'Non disponibile per SARABANDA'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Applica IMPOSTORE alla domanda corrente');
      }

      if (D.memeTextInput) {
        const locked = !!sessione.meme_locked;
        D.memeTextInput.disabled = locked;
        if (document.activeElement !== D.memeTextInput) {
          const preferredText = S.memeDraftText || S.memeText || '';
          D.memeTextInput.value = preferredText;
        }
      }

      if (D.btnMemeToggle) {
        const eligible = !!sessione.meme_eligible;
        const locked = !!sessione.meme_locked;
        const enabled = !!sessione.meme_enabled;
        D.btnMemeToggle.textContent = enabled ? 'MEME ON' : 'MEME OFF';
        D.btnMemeToggle.disabled = !eligible || locked;
        D.btnMemeToggle.classList.toggle('enabled', enabled);
        D.btnMemeToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnMemeToggle.classList.toggle('is-locked', locked);
        D.btnMemeToggle.title = !eligible
          ? 'Non disponibile per SARABANDA'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Applica MEME alla domanda corrente');
      }

      if (D.btnImagePartyToggle) {
        const eligible = !!sessione.image_party_eligible;
        const locked = !!sessione.image_party_locked;
        const enabled = !!sessione.image_party_enabled;
        D.btnImagePartyToggle.textContent = enabled ? 'PIXELATE ON' : 'PIXELATE OFF';
        D.btnImagePartyToggle.disabled = !eligible || locked;
        D.btnImagePartyToggle.classList.toggle('enabled', enabled);
        D.btnImagePartyToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnImagePartyToggle.classList.toggle('is-locked', locked);
        D.btnImagePartyToggle.title = !eligible
          ? 'Richiede un\'immagine e non e disponibile per SARABANDA'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Applica PIXELATE alla domanda corrente');
      }

      if (D.btnFadeToggle) {
        const eligible = !!sessione.fade_eligible;
        const locked = !!sessione.fade_locked;
        const enabled = !!sessione.fade_enabled;
        D.btnFadeToggle.textContent = enabled ? 'FADE ON' : 'FADE OFF';
        D.btnFadeToggle.disabled = !eligible || locked;
        D.btnFadeToggle.classList.toggle('enabled', enabled);
        D.btnFadeToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnFadeToggle.classList.toggle('is-locked', locked);
        D.btnFadeToggle.title = !eligible
          ? 'Richiede un\'immagine e non e disponibile per SARABANDA'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Applica FADE alla domanda corrente');
      }

      if (D.sarabandaAudioLed) {
        const eligible = !!sessione.sarabanda_audio_eligible;
        const locked = !!sessione.sarabanda_audio_locked;
        const enabled = !!sessione.sarabanda_audio_enabled;
        D.sarabandaAudioLed.textContent = enabled ? 'SARABANDA AUTO' : 'SARABANDA OFF';
        D.sarabandaAudioLed.disabled = true;
        D.sarabandaAudioLed.classList.toggle('enabled', enabled);
        D.sarabandaAudioLed.classList.toggle('disabled', !enabled || !eligible);
        D.sarabandaAudioLed.classList.toggle('is-locked', locked);
        D.sarabandaAudioLed.title = !eligible
          ? 'Disponibile solo per SARABANDA con audio'
          : 'Riconoscimento automatico SARABANDA sulla domanda corrente';
      }

      if (D.btnSarabandaReverseToggle) {
        const eligible = !!sessione.sarabanda_audio_eligible;
        const locked = !!sessione.sarabanda_audio_locked;
        const enabled = !!sessione.sarabanda_reverse_enabled;
        D.btnSarabandaReverseToggle.textContent = enabled ? 'REVERSE ON' : 'REVERSE OFF';
        D.btnSarabandaReverseToggle.disabled = !eligible || locked;
        D.btnSarabandaReverseToggle.classList.toggle('enabled', enabled);
        D.btnSarabandaReverseToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnSarabandaReverseToggle.classList.toggle('is-locked', locked);
        D.btnSarabandaReverseToggle.title = !eligible
          ? 'Disponibile solo per SARABANDA con audio'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Riproduce il brano al contrario');
      }

      if (D.btnSarabandaFastToggle) {
        const eligible = !!sessione.sarabanda_audio_eligible;
        const locked = !!sessione.sarabanda_audio_locked;
        const enabled = !!sessione.sarabanda_fast_forward_enabled;
        D.btnSarabandaFastToggle.textContent = enabled ? 'FAST ON' : 'FAST OFF';
        D.btnSarabandaFastToggle.disabled = !eligible || locked;
        D.btnSarabandaFastToggle.classList.toggle('enabled', enabled);
        D.btnSarabandaFastToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnSarabandaFastToggle.classList.toggle('is-locked', locked);
        D.btnSarabandaFastToggle.title = !eligible
          ? 'Disponibile solo per SARABANDA con audio'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Riproduce il brano in fast forward');
      }
      if (D.btnSarabandaBrokenRecordToggle) {
        const eligible = !!sessione.sarabanda_audio_eligible;
        const locked = !!sessione.sarabanda_audio_locked;
        const enabled = !!sessione.sarabanda_broken_record_enabled;
        D.btnSarabandaBrokenRecordToggle.textContent = enabled ? 'DISCO ROTTO ON' : 'DISCO ROTTO OFF';
        D.btnSarabandaBrokenRecordToggle.disabled = !eligible || locked;
        D.btnSarabandaBrokenRecordToggle.classList.toggle('enabled', enabled);
        D.btnSarabandaBrokenRecordToggle.classList.toggle('disabled', !enabled || !eligible);
        D.btnSarabandaBrokenRecordToggle.classList.toggle('is-locked', locked);
        D.btnSarabandaBrokenRecordToggle.title = !eligible
          ? 'Disponibile solo per SARABANDA con audio'
          : (locked ? 'Modificabile solo prima dello stato preview/domanda' : 'Riproduce il brano con effetto disco rotto');
      }
      if (Array.isArray(D.sarabandaFastRateButtons) && D.sarabandaFastRateButtons.length > 0) {
        const eligible = !!sessione.sarabanda_audio_eligible;
        const locked = !!sessione.sarabanda_audio_locked;
        const fastEnabled = !!sessione.sarabanda_fast_forward_enabled;
        const currentRate = String(Number(sessione.sarabanda_fast_forward_rate || S.sarabandaFastForwardRate || 5));
        D.sarabandaFastRateButtons.forEach((btn) => {
          const isActive = fastEnabled && String(btn.dataset.fastRate || '') === currentRate;
          btn.disabled = !eligible || locked;
          btn.classList.toggle('enabled', isActive && eligible && !locked);
          btn.classList.toggle('disabled', !isActive || !eligible || locked);
          btn.classList.toggle('is-active-rate', isActive);
          btn.classList.toggle('is-locked', locked);
          btn.title = !eligible
            ? 'Disponibile solo per SARABANDA con audio'
            : (locked ? 'Modificabile solo prima dello stato preview/domanda' : `Velocita FAST ${btn.textContent}`);
        });
      }

      Admin.actionsSupport.syncSessioneDomandaInfo();

      Admin.actionsSupport.syncCurrentQuestionHighlight();

      Admin.actions.aggiornaPartecipanti();
      Admin.ui.aggiornaTimer(sessione);
    }
  };
})();
