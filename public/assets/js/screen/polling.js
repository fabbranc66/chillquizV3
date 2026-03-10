/* public/assets/js/screen/polling.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const Clock = window.ChillQuizClock;

  function getNextPollDelayMs() {
    const stato = String(S.currentState || '');
    const currentType = String(S.currentDomandaData?.tipo_domanda || '').toUpperCase();
    const waitingSarabandaIntro = stato === 'domanda'
      && currentType === 'SARABANDA'
      && Number(S.currentTimerStart || 0) > Clock.nowSec(S);

    return waitingSarabandaIntro
      ? Number(S.statoPollFastMs || 250)
      : Number(S.statoPollMs || 1000);
  }

  function scheduleNextStatePoll() {
    if (S.pollStato) clearTimeout(S.pollStato);
    S.pollStato = setTimeout(async () => {
      await fetchStato();
      if (shouldPollAudioPreview()) {
        ScreenApp.domanda.fetchAudioPreviewStatus();
      }
      scheduleNextStatePoll();
    }, getNextPollDelayMs());
  }

  function shouldPollAudioPreview() {
    if (String(S.currentState || '') !== 'domanda') return false;
    if (S.sarabandaAudioEnabled || S.sarabandaReverseEnabled || S.sarabandaFastForwardEnabled) return true;
    return ScreenApp.domandaSupport?.isSarabandaQuestionType?.(S.currentDomandaData) === true;
  }

  async function fetchMediaAttiva() {
    if (S.mediaRequestInFlight) return;
    S.mediaRequestInFlight = true;

    try {
      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/mediaAttiva`);
      if (!data.success) return;

      S.mediaAttiva = data.media || null;
      if (!ScreenApp.state.isDomandaState() && S.currentState !== 'risultati') {
        ScreenApp.state.renderPlaceholder(S.currentState);
      }
    } catch (error) {
      console.error(error);
    } finally {
      S.mediaRequestInFlight = false;
    }
  }

  async function fetchStato() {
    if (!S.sessioneId) {
      ScreenApp.domanda.hideView();
      ScreenApp.state.resetStageTimer();
      return;
    }
    if (S.statoRequestInFlight) return;

    S.statoRequestInFlight = true;
    try {
      const statoUrl = new URL(`${ScreenApp.api.apiBase}/stato/${S.sessioneId || 0}`, window.location.origin);
      statoUrl.searchParams.set('viewer', 'screen');
      const data = await ScreenApp.api.fetchJson(statoUrl.toString());
      if (!data.success) {
        if (S.currentState === 'risultati') ScreenApp.state.showRisultatiView();
        else ScreenApp.domanda.hideView();
        ScreenApp.state.resetStageTimer();
        ScreenApp.domanda.enforceAudioStateGuard();
        return;
      }

      Clock.updateOffsetFromServerNow(S, data?.server_now || 0);
      S.latestSessioneSnapshot = data.sessione || null;
      S.currentState = data.sessione?.stato || null;
      S.currentTimerStart = Number(data.sessione?.timer_start || 0);
      S.currentTimerMax = Number(data.sessione?.timer_max || 0);
      S.currentTimerQuestionId = Number(data?.domanda?.id || S.currentTimerQuestionId || 0);
      if (S.currentState !== 'domanda') {
        S.sarabandaPreviewStartedQuestionId = 0;
      } else if (Number(data?.domanda?.id || 0) > 0 && Number(data?.domanda?.id || 0) !== Number(S.sarabandaPreviewStartedQuestionId || 0)) {
        const currentType = String(data?.domanda?.tipo_domanda || '').toUpperCase();
        if (currentType === 'SARABANDA' && Number(S.currentTimerStart || 0) <= 0) {
          S.sarabandaPreviewStartedQuestionId = 0;
        }
      }
      S.sarabandaAudioEnabled = !!data.sessione?.sarabanda_audio_enabled;
      S.sarabandaReverseEnabled = !!data.sessione?.sarabanda_reverse_enabled;
      S.sarabandaFastForwardEnabled = !!data.sessione?.sarabanda_fast_forward_enabled;
      S.sarabandaFastForwardRate = Number(data.sessione?.sarabanda_fast_forward_rate || 5);
      if (ScreenApp.state.isDomandaState()) {
        const opzioniNode = document.getElementById('opzioni');
        if (opzioniNode) {
          opzioniNode.innerHTML = '';
          opzioniNode.classList.add('hidden');
        }
        ScreenApp.state.hideRisultatiView();
        if (data.domanda) {
          ScreenApp.domanda.render(data.domanda, data.sessione || null);
        } else {
          ScreenApp.domanda.showLoadingView();
          await ScreenApp.domanda.fetchCurrent();
        }
      } else if (S.currentState === 'risultati' || S.currentState === 'conclusa') {
        ScreenApp.state.showRisultatiView();
        await ScreenApp.risultati.fetchClassifica();
        ScreenApp.domanda.enforceAudioStateGuard();
      } else {
        ScreenApp.domanda.hideView();
        ScreenApp.state.renderStageTimer(data.sessione || null);
        ScreenApp.domanda.enforceAudioStateGuard();
      }
    } catch (error) {
      console.error(error);
      ScreenApp.domanda.hideView();
      ScreenApp.state.resetStageTimer();
      ScreenApp.domanda.enforceAudioStateGuard();
    } finally {
      S.statoRequestInFlight = false;
    }
  }

  function start() {
    const fromUrl = ScreenApp.api.extractSessioneIdFromUrl();
    if (!S.sessioneId && fromUrl) {
      S.sessioneId = fromUrl;
    }

    ScreenApp.state.setupSessionQr();
    ScreenApp.domanda.bindBadgeAudioEvents();
    ScreenApp.domanda.bindUnlockEvents();
    ScreenApp.domanda.hideView();

    fetchMediaAttiva();
    fetchStato();
    if (shouldPollAudioPreview()) {
      ScreenApp.domanda.fetchAudioPreviewStatus();
    }

    scheduleNextStatePoll();

    if (S.pollMedia) clearInterval(S.pollMedia);
    S.pollMedia = setInterval(fetchMediaAttiva, Number(S.mediaPollMs || 30000));
  }

  document.addEventListener('DOMContentLoaded', start);

  ScreenApp.polling = { fetchMediaAttiva, fetchStato, start };
})();
