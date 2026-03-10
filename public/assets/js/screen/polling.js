/* public/assets/js/screen/polling.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const Clock = window.ChillQuizClock;

  function getNextPollDelayMs() {
    const stato = String(S.currentState || '');
    const currentType = String((S.currentDomandaData && S.currentDomandaData.tipo_domanda) || '').toUpperCase();
    const waitingSarabandaIntro = currentType === 'SARABANDA'
      && (stato === 'preview' || (stato === 'domanda' && Number(S.currentTimerStart || 0) > Clock.nowSec(S)));

    if (stato === 'conclusa') {
      return null;
    }

    if (stato === 'risultati') {
      return 2500;
    }

    return waitingSarabandaIntro
      ? Number(S.statoPollFastMs || 250)
      : Number(S.statoPollMs || 1000);
  }

  function clearPreviewBoundaryPoll() {
    if (S.previewBoundaryTimer) {
      clearTimeout(S.previewBoundaryTimer);
      S.previewBoundaryTimer = null;
    }
  }

  function schedulePreviewBoundaryPoll(sessione, domandaId) {
    clearPreviewBoundaryPoll();
    const stato = String((sessione && sessione.stato) || '');
    const timerStart = Number((sessione && sessione.timer_start) || 0);
    const currentDomandaId = Number(domandaId || 0);
    if (stato !== 'preview' || currentDomandaId <= 0 || timerStart <= 0) {
      return;
    }

    const delayMs = Clock.computeDelayMsFromStart(S, timerStart);
    if (delayMs <= 0) {
      return;
    }

    S.previewBoundaryTimer = setTimeout(async () => {
      S.previewBoundaryTimer = null;

      if (
        String(S.currentState || '') === 'preview'
        && Number(S.currentTimerQuestionId || 0) === currentDomandaId
      ) {
        S.currentState = 'domanda';
        if (S.latestSessioneSnapshot && typeof S.latestSessioneSnapshot === 'object') {
          S.latestSessioneSnapshot.stato = 'domanda';
          S.latestSessioneSnapshot.timer_start = timerStart;
        }

        ScreenApp.state.showOnly('domanda');
        if (S.currentDomandaData) {
          ScreenApp.domanda.render(S.currentDomandaData, S.latestSessioneSnapshot || sessione || null);
        }
      }

      await fetchStato();
    }, delayMs + 15);
  }

  function scheduleNextStatePoll() {
    if (S.pollStato) clearTimeout(S.pollStato);
    const delayMs = getNextPollDelayMs();
    if (delayMs === null) {
      S.pollStato = null;
      return;
    }
    S.pollStato = setTimeout(async () => {
      await fetchStato();
      if (shouldPollAudioPreview()) {
        ScreenApp.domanda.fetchAudioPreviewStatus();
      }
      scheduleNextStatePoll();
    }, delayMs);
  }

  function shouldPollAudioPreview() {
    return String(S.currentState || '') === 'preview';
  }

  async function fetchMediaAttiva() {
    if (S.mediaRequestInFlight) return;
    S.mediaRequestInFlight = true;

    try {
      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/mediaAttiva`);
      if (!data.success) return;

      S.mediaAttiva = data.media || null;
      if (!ScreenApp.state.isQuestionStage() && S.currentState !== 'risultati') {
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
      if (String(S.currentState || '') === 'preview') {
        ScreenApp.state.showOnly('domanda');
      }
      S.currentTimerStart = Number(data.sessione?.timer_start || 0);
      S.currentTimerMax = Number(data.sessione?.timer_max || 0);
      const currentDomandaId = Number(data?.domanda?.id || 0);
      if (currentDomandaId > 0 && currentDomandaId !== Number(S.currentTimerQuestionId || 0)) {
        S.sarabandaPreviewConsumedQuestionId = 0;
      }
      S.currentTimerQuestionId = Number(currentDomandaId || S.currentTimerQuestionId || 0);
      if (!ScreenApp.state.isQuestionStage()) {
        S.sarabandaPreviewStartedQuestionId = 0;
        S.sarabandaPreviewConsumedQuestionId = 0;
      }
      if (String(S.currentState || '') !== 'preview') {
        clearPreviewBoundaryPoll();
      }
      S.sarabandaAudioEnabled = !!data.sessione?.sarabanda_audio_enabled;
      S.sarabandaReverseEnabled = !!data.sessione?.sarabanda_reverse_enabled;
      S.sarabandaBrokenRecordEnabled = !!data.sessione?.sarabanda_broken_record_enabled;
      S.sarabandaFastForwardEnabled = !!data.sessione?.sarabanda_fast_forward_enabled;
      S.sarabandaFastForwardRate = Number(data.sessione?.sarabanda_fast_forward_rate || 5);
      schedulePreviewBoundaryPoll(data.sessione || null, currentDomandaId);
      if (ScreenApp.state.isQuestionStage()) {
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
