/* public/assets/js/screen/polling.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const Clock = window.ChillQuizClock;

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
      S.sarabandaAudioEnabled = !!data.sessione?.sarabanda_audio_enabled;
      S.sarabandaReverseEnabled = !!data.sessione?.sarabanda_reverse_enabled;
      S.sarabandaFastForwardEnabled = !!data.sessione?.sarabanda_fast_forward_enabled;
      S.sarabandaFastForwardRate = Number(data.sessione?.sarabanda_fast_forward_rate || 5);
      if (ScreenApp.state.isDomandaState()) {
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
    ScreenApp.domanda.fetchAudioPreviewStatus();

    if (S.pollStato) clearInterval(S.pollStato);
    S.pollStato = setInterval(() => {
      fetchStato();
      ScreenApp.domanda.fetchAudioPreviewStatus();
    }, Number(S.statoPollMs || 2500));

    if (S.pollMedia) clearInterval(S.pollMedia);
    S.pollMedia = setInterval(fetchMediaAttiva, Number(S.mediaPollMs || 30000));
  }

  document.addEventListener('DOMContentLoaded', start);

  ScreenApp.polling = { fetchMediaAttiva, fetchStato, start };
})();
