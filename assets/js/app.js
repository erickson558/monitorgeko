(function () {
  'use strict';

  var config = window.MONITOR_APP_CONFIG || {};
  var endpoints = config.endpoints || {};
  var ALLOWED_THEMES = ['dark', 'glass', 'emerald', 'sunset', 'graphite', 'arctic'];
  var pollIntervalMs = parseInt(config.pollIntervalMs, 10);
  if (!pollIntervalMs || pollIntervalMs < 100) {
    pollIntervalMs = 5000;
  }
  var DEFAULT_THEME = normalizeTheme(config.themeDefault || 'dark');
  var THEME_STORAGE_KEY = 'monitorgeko.theme';
  var UI_POLL_STORAGE_KEY = 'monitorgeko.ui_poll_ms';
  var CARD_ORDER_STORAGE_KEY = 'monitorgeko.card_order';
  var CARD_DENSITY_STORAGE_KEY = 'monitorgeko.card_density';
  var CONTROLS_PANEL_WIDTH_STORAGE_KEY = 'monitorgeko.controls_panel_width';
  var DEFAULT_CONTROLS_PANEL_WIDTH = 390;
  var MIN_CONTROLS_PANEL_WIDTH = 300;
  var MAX_CONTROLS_PANEL_WIDTH = 620;
  var DEFAULT_LOG_VIEW_LINE_LIMIT = 1000;
  var MAX_LOG_VIEW_LINE_LIMIT = 5000;
  var LOG_SEARCH_DEBOUNCE_MS = 320;
  var MAX_PULL_BATCH_SIZE = 12;
  var CARD_ACTION_HOVER_ANIMATION_CLASSES = [
    'btn-hover-rand-pop',
    'btn-hover-rand-swing',
    'btn-hover-rand-flash',
    'btn-hover-rand-jelly'
  ];
  var defaultDevicePollSeconds = 8;
  var defaultUiPollMs = normalizeUiPollMs(pollIntervalMs, 5000);

  var state = {
    devices: [],
    selected: {},
    loading: false,
    fetchingPull: false,
    fetchingView: false,
    uiTimer: null,
    lastSyncOk: false,
    histories: {},
    maxHistoryPoints: 120,
    sampleSeq: 0,
    chartFrame: null,
    theme: DEFAULT_THEME,
    cardDensity: 'amplio',
    uiPollMs: defaultUiPollMs,
    cardOrder: [],
    draggingId: '',
    dragOverId: '',
    dragInsertBefore: true,
    detailDeviceId: '',
    detailOpen: false,
    detailHistoryHours: 168,
    detailLogContext: null,
    detailLogSearchDebounceTimer: null,
    detailLogRequestSeq: 0,
    detailHistoryRequestSeq: 0,
    detailEventsRequestSeq: 0,
    detailLinuxLogsRequestSeq: 0,
    detailWindowsLogsRequestSeq: 0,
    controlsPanelWidth: DEFAULT_CONTROLS_PANEL_WIDTH,
    resizingControls: false,
    controlsResizeStartX: 0,
    controlsResizeStartWidth: DEFAULT_CONTROLS_PANEL_WIDTH
  };

  var refs = {
    flash: byId('flash'),
    livePill: byId('livePill'),
    lastSync: byId('lastSync'),
    refreshBtn: byId('refreshBtn'),
    themeMode: byId('themeMode'),
    cardDensityMode: byId('cardDensityMode'),
    uiPollMs: byId('uiPollMs'),
    saveUiPollBtn: byId('saveUiPollBtn'),
    pollInfo: byId('pollInfo'),
    searchInput: byId('searchInput'),
    bulkDeleteBtn: byId('bulkDeleteBtn'),
    deviceGrid: byId('deviceGrid'),
    layout: document.querySelector('.layout'),
    controlsPanel: document.querySelector('.controls-panel'),
    controlsResizer: byId('controlsResizer'),
    controlsPanelWidth: byId('controlsPanelWidth'),
    controlsPanelWidthReset: byId('controlsPanelWidthReset'),
    controlsPanelWidthHint: byId('controlsPanelWidthHint'),

    form: byId('deviceForm'),
    deviceId: byId('deviceId'),
    name: byId('deviceName'),
    host: byId('deviceHost'),
    group: byId('deviceGroup'),
    pollSeconds: byId('devicePollSeconds'),
    mode: byId('deviceMode'),
    pushFields: byId('pushFields'),
    pullFields: byId('pullFields'),
    pullUrlRow: byId('pullUrlRow'),
    sshFields: byId('sshFields'),
    token: byId('deviceToken'),
    pullUrl: byId('devicePullUrl'),
    username: byId('deviceUsername'),
    password: byId('devicePassword'),
    sshPort: byId('deviceSshPort'),
    sshOs: byId('deviceSshOs'),
    sshKeyPath: byId('deviceSshKeyPath'),
    expectIis: byId('expectIis'),
    expectIisPorts: byId('expectIisPorts'),
    expectJavaPorts: byId('expectJavaPorts'),
    serviceChecks: byId('serviceChecks'),
    clearPassword: byId('clearPassword'),
    enabled: byId('deviceEnabled'),

    cpuWarning: byId('cpuWarning'),
    cpuCritical: byId('cpuCritical'),
    ramWarning: byId('ramWarning'),
    ramCritical: byId('ramCritical'),
    diskWarning: byId('diskWarning'),
    diskCritical: byId('diskCritical'),

    bulkInput: byId('bulkInput'),
    bulkAddBtn: byId('bulkAddBtn'),
    cancelEditBtn: byId('cancelEditBtn'),
    regenTokenBtn: byId('regenTokenBtn'),
    detailModal: byId('deviceDetailModal'),
    detailCloseBtn: byId('detailCloseBtn'),
    detailTitle: byId('detailTitle'),
    detailSubtitle: byId('detailSubtitle'),
    detailRefreshBtn: byId('detailRefreshBtn'),
    detailDownloadEventsBtn: byId('detailDownloadEventsBtn'),
    detailLoadLinuxLogsBtn: byId('detailLoadLinuxLogsBtn'),
    detailLoadWindowsLogsBtn: byId('detailLoadWindowsLogsBtn'),
    detailHistoryCanvas: byId('detailHistoryCanvas'),
    detailHistoryHint: byId('detailHistoryHint'),
    detailEventsList: byId('detailEventsList'),
    detailLinuxLogsDir: byId('detailLinuxLogsDir'),
    detailLinuxLogsList: byId('detailLinuxLogsList'),
    detailWindowsLogsDir: byId('detailWindowsLogsDir'),
    detailWindowsLogsList: byId('detailWindowsLogsList'),
    detailLogViewerFile: byId('detailLogViewerFile'),
    detailLogLineLimit: byId('detailLogLineLimit'),
    detailLogSearchInput: byId('detailLogSearchInput'),
    detailLogSearchBtn: byId('detailLogSearchBtn'),
    detailLogReloadBtn: byId('detailLogReloadBtn'),
    detailLogViewerHint: byId('detailLogViewerHint'),
    detailLogViewer: byId('detailLogViewer'),

    sumTotal: byId('sumTotal'),
    sumGreen: byId('sumGreen'),
    sumYellow: byId('sumYellow'),
    sumRed: byId('sumRed')
  };

  init();

  function init() {
    if (!refs.form || !refs.deviceGrid) {
      return;
    }

    applyTheme(readStoredTheme(), false);
    applyCardDensity(readStoredCardDensity(), false);
    state.controlsPanelWidth = readStoredControlsPanelWidth();
    applyControlsPanelWidth(state.controlsPanelWidth, false);
    state.uiPollMs = readStoredUiPollMs();
    state.cardOrder = readStoredCardOrder();
    syncUiPollControl();

    refs.form.addEventListener('submit', onSaveDevice);
    refs.mode.addEventListener('change', onModeChange);
    refs.sshOs.addEventListener('change', onSshOsChange);
    refs.cancelEditBtn.addEventListener('click', resetForm);
    refs.regenTokenBtn.addEventListener('click', onRegenToken);
    refs.refreshBtn.addEventListener('click', function () {
      triggerPullTick(true);
      loadState(true, true, { requestPull: false, maxPull: 1 });
    });
    refs.themeMode.addEventListener('change', onThemeChange);
    if (refs.cardDensityMode) {
      refs.cardDensityMode.addEventListener('change', onCardDensityChange);
    }
    refs.saveUiPollBtn.addEventListener('click', onSaveUiPoll);
    if (refs.controlsPanelWidth) {
      refs.controlsPanelWidth.addEventListener('input', onControlsPanelWidthInput);
      refs.controlsPanelWidth.addEventListener('change', onControlsPanelWidthChange);
    }
    if (refs.controlsPanelWidthReset) {
      refs.controlsPanelWidthReset.addEventListener('click', onControlsPanelWidthReset);
    }
    if (refs.controlsResizer) {
      refs.controlsResizer.addEventListener('mousedown', onControlsResizeStart);
    }

    refs.bulkAddBtn.addEventListener('click', onBulkAdd);
    refs.bulkDeleteBtn.addEventListener('click', onBulkDelete);
    refs.searchInput.addEventListener('input', render);

    refs.deviceGrid.addEventListener('click', onGridClick);
    refs.deviceGrid.addEventListener('change', onGridChange);
    refs.deviceGrid.addEventListener('mouseover', onGridButtonHover);
    refs.deviceGrid.addEventListener('animationend', onGridButtonAnimationEnd);
    refs.deviceGrid.addEventListener('dragstart', onGridDragStart);
    refs.deviceGrid.addEventListener('dragover', onGridDragOver);
    refs.deviceGrid.addEventListener('drop', onGridDrop);
    refs.deviceGrid.addEventListener('dragleave', onGridDragLeave);
    refs.deviceGrid.addEventListener('dragend', onGridDragEnd);
    if (refs.detailCloseBtn) {
      refs.detailCloseBtn.addEventListener('click', closeDeviceDetail);
    }
    if (refs.detailRefreshBtn) {
      refs.detailRefreshBtn.addEventListener('click', function () {
        reloadCurrentDeviceDetail();
      });
    }
    if (refs.detailLoadLinuxLogsBtn) {
      refs.detailLoadLinuxLogsBtn.addEventListener('click', function () {
        var device = findDevice(state.detailDeviceId || '');
        if (!device) {
          return;
        }
        loadLinuxLogsForDevice(device, true);
      });
    }
    if (refs.detailLoadWindowsLogsBtn) {
      refs.detailLoadWindowsLogsBtn.addEventListener('click', function () {
        var device = findDevice(state.detailDeviceId || '');
        if (!device) {
          return;
        }
        loadWindowsIisLogsForDevice(device, true);
      });
    }
    if (refs.detailLogSearchBtn) {
      refs.detailLogSearchBtn.addEventListener('click', function () {
        searchInCurrentLogPreview();
      });
    }
    if (refs.detailLogReloadBtn) {
      refs.detailLogReloadBtn.addEventListener('click', function () {
        reloadCurrentLogPreview();
      });
    }
    if (refs.detailLogSearchInput) {
      refs.detailLogSearchInput.addEventListener('input', onDetailLogSearchInput);
      refs.detailLogSearchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          searchInCurrentLogPreview();
        }
      });
    }
    if (refs.detailLogLineLimit) {
      refs.detailLogLineLimit.addEventListener('change', function () {
        reloadCurrentLogPreview();
      });
    }
    if (refs.detailModal) {
      refs.detailModal.addEventListener('click', function (event) {
        var viewBtn = event.target.closest('[data-action="view-remote-log"]');
        if (viewBtn) {
          event.preventDefault();
          openLogPreviewFromButton(viewBtn);
          return;
        }
        var closeTarget = event.target.closest('[data-action="close-detail"]');
        if (closeTarget) {
          closeDeviceDetail();
        }
      });
    }
    window.addEventListener('resize', scheduleTaskCharts);
    window.addEventListener('mousemove', onControlsResizeMove);
    window.addEventListener('mouseup', onControlsResizeEnd);
    window.addEventListener('keydown', onGlobalKeyDown);

    applyMode('push');
    resetForm();
    applyUiPollInterval(state.uiPollMs);
    updatePollInfo();
    triggerPullTick(true);
    loadState(true, false, { requestPull: false, maxPull: 1 });
  }

  function byId(id) {
    return document.getElementById(id);
  }

  function onModeChange() {
    var parsed = parseModeSelection(refs.mode.value, refs.sshOs.value);
    if (parsed.mode === 'pull_ssh') {
      refs.sshOs.value = parsed.sshOs;
    }
    applyMode(refs.mode.value);
  }

  function onSshOsChange() {
    var parsed = parseModeSelection(refs.mode.value, refs.sshOs.value);
    if (parsed.mode === 'pull_ssh') {
      refs.mode.value = modeSelectionValue('pull_ssh', refs.sshOs.value);
    }
  }

  function onThemeChange() {
    applyTheme(refs.themeMode.value, true);
  }

  function onCardDensityChange() {
    applyCardDensity(refs.cardDensityMode.value, true);
    render();
  }

  function onSaveUiPoll() {
    var nextMs = normalizeUiPollMs(refs.uiPollMs.value, state.uiPollMs);
    applyUiPollInterval(nextMs);
    syncUiPollControl();
    storeUiPollMs(state.uiPollMs);
    showFlash('Refresco dashboard actualizado a ' + state.uiPollMs + ' ms.', 'success', 2600);
    triggerPullTick(true);
    loadState(false, true, { requestPull: false, background: true, quiet: true, maxPull: 1 });
  }

  function normalizeControlsPanelWidth(value, fallback) {
    var base = parseInt(fallback, 10);
    if (!isFinite(base)) {
      base = DEFAULT_CONTROLS_PANEL_WIDTH;
    }

    var width = parseInt(value, 10);
    if (!isFinite(width)) {
      width = base;
    }

    if (width < MIN_CONTROLS_PANEL_WIDTH) {
      width = MIN_CONTROLS_PANEL_WIDTH;
    }
    if (width > MAX_CONTROLS_PANEL_WIDTH) {
      width = MAX_CONTROLS_PANEL_WIDTH;
    }

    return width;
  }

  function readStoredControlsPanelWidth() {
    try {
      var stored = window.localStorage.getItem(CONTROLS_PANEL_WIDTH_STORAGE_KEY);
      if (stored !== null && stored !== '') {
        return normalizeControlsPanelWidth(stored, DEFAULT_CONTROLS_PANEL_WIDTH);
      }
    } catch (error) {}
    return DEFAULT_CONTROLS_PANEL_WIDTH;
  }

  function storeControlsPanelWidth(width) {
    try {
      window.localStorage.setItem(
        CONTROLS_PANEL_WIDTH_STORAGE_KEY,
        String(normalizeControlsPanelWidth(width, DEFAULT_CONTROLS_PANEL_WIDTH))
      );
    } catch (error) {}
  }

  function applyControlsPanelWidth(width, persist) {
    var nextWidth = normalizeControlsPanelWidth(width, state.controlsPanelWidth);
    state.controlsPanelWidth = nextWidth;
    document.documentElement.style.setProperty('--controls-panel-width', nextWidth + 'px');

    if (refs.controlsPanelWidth) {
      refs.controlsPanelWidth.value = String(nextWidth);
    }
    if (refs.controlsPanelWidthHint) {
      refs.controlsPanelWidthHint.textContent = nextWidth + ' px';
    }

    if (persist) {
      storeControlsPanelWidth(nextWidth);
    }
  }

  function onControlsPanelWidthInput() {
    if (!refs.controlsPanelWidth) {
      return;
    }
    applyControlsPanelWidth(refs.controlsPanelWidth.value, false);
  }

  function onControlsPanelWidthChange() {
    if (!refs.controlsPanelWidth) {
      return;
    }
    applyControlsPanelWidth(refs.controlsPanelWidth.value, true);
  }

  function onControlsPanelWidthReset() {
    applyControlsPanelWidth(DEFAULT_CONTROLS_PANEL_WIDTH, true);
  }

  function canResizeControlsPanel() {
    return !!refs.controlsPanel && window.innerWidth > 1250;
  }

  function onControlsResizeStart(event) {
    if (!canResizeControlsPanel()) {
      return;
    }
    if (!event || event.button !== 0) {
      return;
    }

    event.preventDefault();
    var rect = refs.controlsPanel.getBoundingClientRect();
    state.resizingControls = true;
    state.controlsResizeStartX = event.clientX;
    state.controlsResizeStartWidth = normalizeControlsPanelWidth(rect.width, state.controlsPanelWidth);
    document.body.classList.add('controls-resizing');
  }

  function onControlsResizeMove(event) {
    if (!state.resizingControls) {
      return;
    }
    if (!event) {
      return;
    }

    var delta = event.clientX - state.controlsResizeStartX;
    var nextWidth = state.controlsResizeStartWidth + delta;
    applyControlsPanelWidth(nextWidth, false);
  }

  function onControlsResizeEnd() {
    if (!state.resizingControls) {
      return;
    }
    state.resizingControls = false;
    document.body.classList.remove('controls-resizing');
    applyControlsPanelWidth(state.controlsPanelWidth, true);
  }

  function onGlobalKeyDown(event) {
    if (!event) {
      return;
    }
    if (event.key === 'Escape' && state.detailOpen) {
      closeDeviceDetail();
    }
  }

  function applyMode(mode) {
    var parsed = parseModeSelection(mode, refs.sshOs.value);
    var normalized = parsed.mode;
    if (normalized === 'pull_ssh') {
      refs.sshOs.value = parsed.sshOs;
    }

    if (normalized === 'push') {
      refs.pushFields.hidden = false;
      refs.pullFields.hidden = true;
      refs.sshFields.hidden = true;
      refs.pullUrlRow.hidden = false;
      refs.pullUrl.required = false;
    } else if (normalized === 'pull_http') {
      refs.pushFields.hidden = true;
      refs.pullFields.hidden = false;
      refs.sshFields.hidden = true;
      refs.pullUrlRow.hidden = false;
      refs.pullUrl.required = true;
    } else {
      refs.pushFields.hidden = true;
      refs.pullFields.hidden = false;
      refs.sshFields.hidden = false;
      refs.pullUrlRow.hidden = true;
      refs.pullUrl.required = false;
    }
  }

  function onRegenToken() {
    refs.token.value = randomToken(32);
  }

  function randomToken(length) {
    var chars = 'abcdef0123456789';
    var result = '';
    for (var i = 0; i < length; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
  }

  function setLoading(isLoading) {
    state.loading = !!isLoading;
    refs.refreshBtn.disabled = state.loading;
    refs.bulkAddBtn.disabled = state.loading;
    refs.saveUiPollBtn.disabled = state.loading;
  }

  function setLive(ok) {
    state.lastSyncOk = !!ok;
    if (ok) {
      refs.livePill.classList.remove('stale');
    } else {
      refs.livePill.classList.add('stale');
    }
  }

  function loadState(manual, force, options) {
    var opts = options || {};
    var requestPull = opts.requestPull !== false;
    var background = !!opts.background;
    var quiet = !!opts.quiet;
    var maxPull = parseInt(opts.maxPull, 10);
    if (!isFinite(maxPull) || maxPull < 1) {
      maxPull = computePullBatchSize();
    }
    if (maxPull > MAX_PULL_BATCH_SIZE) {
      maxPull = MAX_PULL_BATCH_SIZE;
    }
    var inFlightKey = requestPull ? 'fetchingPull' : 'fetchingView';

    if (state[inFlightKey] && !force) {
      return Promise.resolve();
    }

    state[inFlightKey] = true;
    if (!background) {
      setLoading(true);
    }

    var url = appendQueryParam(endpoints.state, 'ui_poll_ms', state.uiPollMs);
    if (!requestPull) {
      url = appendQueryParam(url, 'pull', '0');
    } else {
      url = appendQueryParam(url, 'max_pull', maxPull);
    }

    return getJSON(withCacheBust(url)).then(function (response) {
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No fue posible obtener estado');
      }

      state.devices = Array.isArray(response.devices) ? response.devices : [];
      syncCardOrderWithDevices(state.devices);
      applySettingsFromResponse(response.settings || null);
      updateHistories(state.devices, response.generated_at || '');
      pruneSelection();

      updateSummary(response.summary || {});
      updateLastSync(response.generated_at || '');
      updatePollInfo();
      render();
      if (state.detailOpen) {
        var detailDevice = findDevice(state.detailDeviceId || '');
        if (detailDevice) {
          updateDetailHeader(detailDevice);
        } else {
          closeDeviceDetail();
        }
      }
      setLive(true);

      if (manual) {
        showFlash('Estado actualizado en tiempo real.', 'success', 2600);
      }
    }).catch(function (err) {
      setLive(false);
      if (!quiet) {
        showFlash(err.message || 'Error consultando estado.', 'error', 5000);
      }
    }).finally(function () {
      state[inFlightKey] = false;
      if (!background) {
        setLoading(false);
      }
    });
  }

  function withCacheBust(url) {
    var separator = url.indexOf('?') >= 0 ? '&' : '?';
    return url + separator + '_ts=' + Date.now();
  }

  function appendQueryParam(url, key, value) {
    var separator = url.indexOf('?') >= 0 ? '&' : '?';
    return url + separator + encodeURIComponent(key) + '=' + encodeURIComponent(String(value));
  }

  function computePullBatchSize() {
    var pullable = 0;
    for (var i = 0; i < state.devices.length; i++) {
      var device = state.devices[i] || {};
      if (device.enabled === false) {
        continue;
      }

      var mode = normalizeMode(device.mode || 'push');
      if (mode === 'pull_http' || mode === 'pull_ssh') {
        pullable++;
      }
    }

    if (pullable < 1) {
      return 1;
    }

    // Smaller UI intervals can process more devices per cycle without feeling stale.
    var intervalMs = normalizeUiPollMs(state.uiPollMs, defaultUiPollMs);
    var targetBatch = 2;
    if (intervalMs <= 500) {
      targetBatch = 8;
    } else if (intervalMs <= 1000) {
      targetBatch = 6;
    } else if (intervalMs <= 2000) {
      targetBatch = 4;
    }

    if (targetBatch < 1) {
      targetBatch = 1;
    }
    if (targetBatch > MAX_PULL_BATCH_SIZE) {
      targetBatch = MAX_PULL_BATCH_SIZE;
    }

    return Math.min(pullable, targetBatch);
  }

  function triggerPullTick(force) {
    var shouldForce = !!force;
    if (state.fetchingPull && !shouldForce) {
      return Promise.resolve();
    }

    return loadState(false, shouldForce, {
      requestPull: true,
      background: true,
      quiet: true,
      maxPull: computePullBatchSize()
    }).then(function () {
      return loadState(false, true, {
        requestPull: false,
        background: true,
        quiet: true,
        maxPull: 1
      });
    }).catch(function () {
      return Promise.resolve();
    });
  }

  function onSaveDevice(event) {
    event.preventDefault();

    var parsedMode = parseModeSelection(refs.mode.value, refs.sshOs.value);
    var mode = parsedMode.mode;
    if (mode === 'pull_http' && refs.pullUrl.value.trim() === '') {
      showFlash('Debes indicar URL para modo pull_http.', 'error', 4000);
      return;
    }
    if (mode === 'pull_ssh' && refs.username.value.trim() === '') {
      showFlash('Debes indicar usuario para modo pull_ssh.', 'error', 4000);
      return;
    }

    var payload = {
      action: 'upsert',
      device: collectFormData()
    };

    setLoading(true);
    postJSON(endpoints.devices, payload).then(function (response) {
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No fue posible guardar el equipo');
      }

      var message = refs.deviceId.value ? 'Equipo actualizado.' : 'Equipo agregado.';
      showFlash(message, 'success', 3000);
      resetForm();
      triggerPullTick(true);
      return loadState(false, true, { requestPull: false, maxPull: 1 });
    }).catch(function (err) {
      showFlash(err.message || 'Error guardando equipo.', 'error', 5000);
    }).finally(function () {
      setLoading(false);
    });
  }

  function collectFormData() {
    var parsedMode = parseModeSelection(refs.mode.value, refs.sshOs.value);
    var iisPorts = normalizePortListFromValue(refs.expectIisPorts.value);
    var javaPorts = normalizePortListFromValue(refs.expectJavaPorts.value);
    var customServices = parseServiceChecksText(refs.serviceChecks.value);
    return {
      id: refs.deviceId.value.trim(),
      name: refs.name.value.trim(),
      host: refs.host.value.trim(),
      group: refs.group.value.trim(),
      poll_interval_seconds: refs.pollSeconds.value.trim(),
      mode: parsedMode.mode,
      token: refs.token.value.trim(),
      pull_url: refs.pullUrl.value.trim(),
      username: refs.username.value.trim(),
      password: refs.password.value,
      ssh_port: refs.sshPort.value,
      ssh_os: parsedMode.sshOs,
      ssh_key_path: refs.sshKeyPath.value.trim(),
      expect_iis: refs.expectIis.checked,
      expect_iis_ports: iisPorts.join(','),
      expect_java_ports: javaPorts.join(','),
      expect_java_port: javaPorts.length > 0 ? String(javaPorts[0]) : '',
      service_checks: customServices,
      clear_password: refs.clearPassword.checked,
      enabled: refs.enabled.checked,
      cpu_warning: refs.cpuWarning.value,
      cpu_critical: refs.cpuCritical.value,
      ram_warning: refs.ramWarning.value,
      ram_critical: refs.ramCritical.value,
      disk_warning: refs.diskWarning.value,
      disk_critical: refs.diskCritical.value
    };
  }

  function resetForm() {
    refs.form.reset();
    refs.deviceId.value = '';
    refs.mode.value = 'push';
    refs.pollSeconds.value = '';
    refs.token.value = '';
    refs.pullUrl.value = '';
    refs.username.value = '';
    refs.password.value = '';
    refs.sshPort.value = 22;
    refs.sshOs.value = 'auto';
    refs.sshKeyPath.value = '';
    refs.expectIis.checked = false;
    refs.expectIisPorts.value = '';
    refs.expectJavaPorts.value = '';
    refs.serviceChecks.value = '';
    refs.clearPassword.checked = false;
    refs.cpuWarning.value = 70;
    refs.cpuCritical.value = 90;
    refs.ramWarning.value = 75;
    refs.ramCritical.value = 90;
    refs.diskWarning.value = 80;
    refs.diskCritical.value = 95;
    refs.enabled.checked = true;

    applyMode('push');
    refs.name.focus();
  }

  function onBulkAdd() {
    var text = refs.bulkInput.value;
    if (!text || text.trim() === '') {
      showFlash('Debes ingresar filas para carga masiva.', 'error', 4000);
      return;
    }

    setLoading(true);
    postJSON(endpoints.devices, {
      action: 'bulk_upsert_text',
      text: text
    }).then(function (response) {
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'Error en carga masiva');
      }

      var summary = 'Procesadas: ' + response.processed + ', nuevas: ' + response.created + ', actualizadas: ' + response.updated;
      if (Array.isArray(response.validation_errors) && response.validation_errors.length > 0) {
        summary += '. Con errores de validacion: ' + response.validation_errors.length;
      }
      if (Array.isArray(response.parse_errors) && response.parse_errors.length > 0) {
        summary += '. Errores de parseo: ' + response.parse_errors.length;
      }

      showFlash(summary, 'success', 5000);
      refs.bulkInput.value = '';
      triggerPullTick(true);
      return loadState(false, true, { requestPull: false, maxPull: 1 });
    }).catch(function (err) {
      showFlash(err.message || 'No se pudo ejecutar la carga masiva.', 'error', 5000);
    }).finally(function () {
      setLoading(false);
    });
  }

  function onBulkDelete() {
    var ids = Object.keys(state.selected);
    if (ids.length === 0) {
      return;
    }

    var confirmed = window.confirm('Se eliminaran ' + ids.length + ' equipos. Continuar?');
    if (!confirmed) {
      return;
    }

    setLoading(true);
    postJSON(endpoints.devices, {
      action: 'bulk_delete',
      ids: ids
    }).then(function (response) {
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No fue posible eliminar en masivo');
      }

      state.selected = {};
      showFlash('Equipos eliminados: ' + response.deleted_count, 'success', 3500);
      triggerPullTick(true);
      return loadState(false, true, { requestPull: false, maxPull: 1 });
    }).catch(function (err) {
      showFlash(err.message || 'Error en borrado masivo.', 'error', 5000);
    }).finally(function () {
      setLoading(false);
    });
  }

  function onGridClick(event) {
    var target = getEventElementTarget(event);
    if (!target) {
      return;
    }

    var actionTarget = target.closest('button[data-action], a[data-action], [role="button"][data-action]');
    if (!actionTarget) {
      return;
    }

    var action = actionTarget.getAttribute('data-action');
    if (action !== 'edit' && action !== 'delete' && action !== 'copy_token' && action !== 'details') {
      return;
    }

    if (actionTarget.tagName === 'A' && typeof event.preventDefault === 'function') {
      event.preventDefault();
    }

    var deviceId = normalizeDeviceId(actionTarget.getAttribute('data-id'));
    var device = findDevice(deviceId);
    if (!device) {
      var card = actionTarget.closest('.device-card[data-device-id]');
      if (card) {
        device = findDevice(card.getAttribute('data-device-id'));
      }
    }

    if (!device) {
      showFlash('No se encontro el equipo seleccionado. Recarga el dashboard e intenta de nuevo.', 'error', 4200);
      return;
    }

    if (action === 'edit') {
      fillForm(device);
      return;
    }

    if (action === 'delete') {
      onDeleteDevice(device);
      return;
    }

    if (action === 'copy_token') {
      onCopyToken(device);
      return;
    }

    if (action === 'details') {
      openDeviceDetail(device);
    }
  }

  function onGridChange(event) {
    var checkbox = event.target;
    if (!checkbox || !checkbox.matches('input[data-role="select-device"]')) {
      return;
    }

    var id = checkbox.getAttribute('data-id');
    if (!id) {
      return;
    }

    if (checkbox.checked) {
      state.selected[id] = true;
    } else {
      delete state.selected[id];
    }

    updateBulkDeleteState();
  }

  function onGridButtonHover(event) {
    var target = getEventElementTarget(event);
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var button = target.closest('.device-card .device-actions .btn');
    if (!button || button.disabled) {
      return;
    }

    var related = event.relatedTarget;
    if (related && isNodeInside(button, related)) {
      return;
    }

    playRandomCardActionHover(button);
  }

  function onGridButtonAnimationEnd(event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var button = target.closest('.device-card .device-actions .btn');
    if (!button) {
      return;
    }

    clearCardActionHoverClasses(button);
    button.removeAttribute('data-hover-anim');
  }

  function isNodeInside(root, node) {
    var current = node;
    while (current) {
      if (current === root) {
        return true;
      }
      current = current.parentNode;
    }
    return false;
  }

  function playRandomCardActionHover(button) {
    if (!button || !button.classList) {
      return;
    }

    var previous = button.getAttribute('data-hover-anim') || '';
    var nextAnimation = pickRandomCardActionHoverClass(previous);
    if (!nextAnimation) {
      return;
    }

    clearCardActionHoverClasses(button);
    // Force reflow so the same animation can restart on rapid hovers.
    void button.offsetWidth;
    button.classList.add(nextAnimation);
    button.setAttribute('data-hover-anim', nextAnimation);
  }

  function pickRandomCardActionHoverClass(previous) {
    if (!CARD_ACTION_HOVER_ANIMATION_CLASSES.length) {
      return '';
    }

    var maxIndex = CARD_ACTION_HOVER_ANIMATION_CLASSES.length;
    var nextIndex = Math.floor(Math.random() * maxIndex);
    var next = CARD_ACTION_HOVER_ANIMATION_CLASSES[nextIndex];
    if (maxIndex > 1 && next === previous) {
      nextIndex = (nextIndex + 1 + Math.floor(Math.random() * (maxIndex - 1))) % maxIndex;
      next = CARD_ACTION_HOVER_ANIMATION_CLASSES[nextIndex];
    }
    return next;
  }

  function clearCardActionHoverClasses(button) {
    if (!button || !button.classList) {
      return;
    }

    for (var i = 0; i < CARD_ACTION_HOVER_ANIMATION_CLASSES.length; i++) {
      button.classList.remove(CARD_ACTION_HOVER_ANIMATION_CLASSES[i]);
    }
  }

  function onGridDragStart(event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var blocked = target.closest('button, input, textarea, select, a, label');
    if (blocked) {
      event.preventDefault();
      if (typeof event.stopPropagation === 'function') {
        event.stopPropagation();
      }
      return;
    }

    var card = target.closest('.device-card[data-device-id]');
    if (!card) {
      return;
    }

    var deviceId = card.getAttribute('data-device-id') || '';
    if (!deviceId) {
      return;
    }

    state.draggingId = deviceId;
    card.classList.add('dragging');
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', deviceId);
    }
  }

  function onGridDragOver(event) {
    if (!state.draggingId) {
      return;
    }

    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var card = target.closest('.device-card[data-device-id]');
    if (!card) {
      return;
    }

    var targetId = card.getAttribute('data-device-id') || '';
    if (!targetId || targetId === state.draggingId) {
      return;
    }

    event.preventDefault();
    state.dragOverId = targetId;
    state.dragInsertBefore = shouldInsertBefore(card, event.clientY);
    applyDragOverMarker(card, state.dragInsertBefore);
  }

  function onGridDrop(event) {
    if (!state.draggingId) {
      return;
    }

    event.preventDefault();
    var target = event.target;
    var card = target && typeof target.closest === 'function'
      ? target.closest('.device-card[data-device-id]')
      : null;
    var targetId = '';
    if (card) {
      targetId = card.getAttribute('data-device-id') || '';
    }
    if (!targetId) {
      targetId = state.dragOverId;
    }

    var moved = false;
    if (targetId && targetId !== state.draggingId) {
      moveCardOrder(state.draggingId, targetId, state.dragInsertBefore);
      moved = true;
    }

    clearDragState();
    if (moved) {
      render();
    }
  }

  function onGridDragLeave(event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var current = target.closest('.device-card[data-device-id]');
    if (!current) {
      return;
    }

    var related = event.relatedTarget;
    if (related && current.contains(related)) {
      return;
    }

    current.classList.remove('drag-over-before');
    current.classList.remove('drag-over-after');
  }

  function onGridDragEnd() {
    var hadDragging = !!state.draggingId;
    clearDragState();
    if (hadDragging) {
      render();
    }
  }

  function clearDragState() {
    state.draggingId = '';
    state.dragOverId = '';
    state.dragInsertBefore = true;
    clearDragMarkers();
  }

  function clearDragMarkers() {
    var cards = refs.deviceGrid.querySelectorAll('.device-card');
    for (var i = 0; i < cards.length; i++) {
      cards[i].classList.remove('dragging');
      cards[i].classList.remove('drag-over-before');
      cards[i].classList.remove('drag-over-after');
    }
  }

  function applyDragOverMarker(card, before) {
    clearDragMarkers();
    if (!card) {
      return;
    }

    var dragging = refs.deviceGrid.querySelector('.device-card[data-device-id="' + escapeSelector(state.draggingId) + '"]');
    if (dragging) {
      dragging.classList.add('dragging');
    }
    card.classList.add(before ? 'drag-over-before' : 'drag-over-after');
  }

  function shouldInsertBefore(card, clientY) {
    var rect = card.getBoundingClientRect();
    var midpoint = rect.top + rect.height / 2;
    return clientY < midpoint;
  }

  function readStoredCardOrder() {
    try {
      var raw = window.localStorage.getItem(CARD_ORDER_STORAGE_KEY);
      if (!raw) {
        return [];
      }

      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return [];
      }

      var cleaned = [];
      for (var i = 0; i < parsed.length; i++) {
        var id = String(parsed[i] || '').trim();
        if (id !== '' && cleaned.indexOf(id) < 0) {
          cleaned.push(id);
        }
      }
      return cleaned;
    } catch (error) {
      return [];
    }
  }

  function storeCardOrder() {
    try {
      window.localStorage.setItem(CARD_ORDER_STORAGE_KEY, JSON.stringify(state.cardOrder));
    } catch (error) {}
  }

  function syncCardOrderWithDevices(devices) {
    var list = Array.isArray(devices) ? devices : [];
    var allowed = {};
    var orderedIds = [];

    for (var i = 0; i < list.length; i++) {
      var id = String(list[i].id || '').trim();
      if (!id || allowed[id]) {
        continue;
      }
      allowed[id] = true;
      orderedIds.push(id);
    }

    var next = [];
    var seen = {};

    for (var oldIdx = 0; oldIdx < state.cardOrder.length; oldIdx++) {
      var oldId = state.cardOrder[oldIdx];
      if (!allowed[oldId] || seen[oldId]) {
        continue;
      }
      seen[oldId] = true;
      next.push(oldId);
    }

    for (var orderedIdx = 0; orderedIdx < orderedIds.length; orderedIdx++) {
      var orderedId = orderedIds[orderedIdx];
      if (seen[orderedId]) {
        continue;
      }
      seen[orderedId] = true;
      next.push(orderedId);
    }

    if (!sameIdOrder(state.cardOrder, next)) {
      state.cardOrder = next;
      storeCardOrder();
    }
  }

  function moveCardOrder(dragId, targetId, before) {
    var order = state.cardOrder.slice();
    if (order.indexOf(dragId) < 0) {
      order.push(dragId);
    }
    if (order.indexOf(targetId) < 0) {
      order.push(targetId);
    }

    var dragIndex = order.indexOf(dragId);
    if (dragIndex >= 0) {
      order.splice(dragIndex, 1);
    }

    var targetIndex = order.indexOf(targetId);
    if (targetIndex < 0) {
      order.push(dragId);
    } else {
      var insertAt = before ? targetIndex : targetIndex + 1;
      order.splice(insertAt, 0, dragId);
    }

    state.cardOrder = order;
    storeCardOrder();
  }

  function sameIdOrder(a, b) {
    if (!Array.isArray(a) || !Array.isArray(b)) {
      return false;
    }
    if (a.length !== b.length) {
      return false;
    }
    for (var i = 0; i < a.length; i++) {
      if (a[i] !== b[i]) {
        return false;
      }
    }
    return true;
  }

  function onDeleteDevice(device) {
    var confirmed = window.confirm('Eliminar equipo "' + device.name + '"?');
    if (!confirmed) {
      return;
    }

    setLoading(true);
    postJSON(endpoints.devices, {
      action: 'delete',
      id: device.id
    }).then(function (response) {
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No se pudo eliminar el equipo');
      }

      delete state.selected[device.id];
      showFlash('Equipo eliminado.', 'success', 2500);
      triggerPullTick(true);
      return loadState(false, true, { requestPull: false, maxPull: 1 });
    }).catch(function (err) {
      showFlash(err.message || 'Error eliminando equipo.', 'error', 5000);
    }).finally(function () {
      setLoading(false);
    });
  }

  function onCopyToken(device) {
    var token = device.token || '';
    if (!token) {
      showFlash('Este equipo no tiene token disponible.', 'error', 3500);
      return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(token).then(function () {
        showFlash('Token copiado al portapapeles.', 'success', 2200);
      }).catch(function () {
        fallbackCopy(token);
      });
      return;
    }

    fallbackCopy(token);
  }

  function fallbackCopy(value) {
    var tmp = document.createElement('textarea');
    tmp.value = value;
    tmp.style.position = 'fixed';
    tmp.style.opacity = '0';
    document.body.appendChild(tmp);
    tmp.select();

    try {
      document.execCommand('copy');
      showFlash('Token copiado al portapapeles.', 'success', 2200);
    } catch (error) {
      showFlash('No se pudo copiar automaticamente.', 'error', 3500);
    }

    document.body.removeChild(tmp);
  }

  function fillForm(device) {
    refs.deviceId.value = device.id || '';
    refs.name.value = device.name || '';
    refs.host.value = device.host || '';
    refs.group.value = device.group || '';
    refs.pollSeconds.value = normalizeDevicePollInput(device.poll_interval_seconds);
    refs.sshOs.value = normalizeSshOs(device.ssh_os || 'auto');
    refs.mode.value = modeSelectionValue(device.mode || 'push', refs.sshOs.value);
    refs.token.value = device.token || '';
    refs.pullUrl.value = device.pull_url || '';
    refs.username.value = device.username || '';
    refs.password.value = '';
    refs.sshPort.value = normalizePort(device.ssh_port, 22);
    refs.sshKeyPath.value = device.ssh_key_path || '';
    refs.expectIis.checked = isTruthy(device.expect_iis);
    refs.expectIisPorts.value = formatPortListValue(device.expect_iis_ports);
    if (refs.expectIisPorts.value === '' && refs.expectIis.checked) {
      refs.expectIisPorts.value = '80,443';
    }
    refs.expectJavaPorts.value = formatPortListValue(device.expect_java_ports);
    if (refs.expectJavaPorts.value === '') {
      refs.expectJavaPorts.value = normalizeOptionalPort(device.expect_java_port);
    }
    refs.serviceChecks.value = formatServiceChecksText(device.service_checks);
    refs.clearPassword.checked = false;
    refs.enabled.checked = !!device.enabled;

    var t = device.thresholds || {};
    var cpu = t.cpu || {};
    var ram = t.ram || {};
    var disk = t.disk || {};

    refs.cpuWarning.value = normalizeThreshold(cpu.warning, 70);
    refs.cpuCritical.value = normalizeThreshold(cpu.critical, 90);
    refs.ramWarning.value = normalizeThreshold(ram.warning, 75);
    refs.ramCritical.value = normalizeThreshold(ram.critical, 90);
    refs.diskWarning.value = normalizeThreshold(disk.warning, 80);
    refs.diskCritical.value = normalizeThreshold(disk.critical, 95);

    if (device.password_configured) {
      refs.password.placeholder = 'Deja vacio para conservar password';
    } else {
      refs.password.placeholder = 'Solo si aplica';
    }

    applyMode(refs.mode.value);
    refs.name.focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function normalizeMode(mode) {
    return parseModeSelection(mode, 'auto').mode;
  }

  function normalizePort(value, fallback) {
    var port = parseInt(value, 10);
    if (!isFinite(port) || port < 1 || port > 65535) {
      return fallback;
    }
    return port;
  }

  function normalizeOptionalPort(value) {
    var port = parseInt(value, 10);
    if (!isFinite(port) || port < 1 || port > 65535) {
      return '';
    }
    return String(port);
  }

  function normalizePortListFromValue(value) {
    var tokens = [];
    if (Array.isArray(value)) {
      tokens = value;
    } else if (value !== null && value !== undefined) {
      var raw = String(value).trim();
      if (raw !== '') {
        tokens = raw.split(/[\s,;|]+/);
      }
    }

    var list = [];
    var seen = {};
    for (var i = 0; i < tokens.length; i++) {
      var port = normalizeProcessPort(tokens[i]);
      if (port === 0) {
        continue;
      }
      var key = String(port);
      if (seen[key]) {
        continue;
      }
      seen[key] = true;
      list.push(port);
    }

    return list;
  }

  function formatPortListValue(value) {
    var ports = normalizePortListFromValue(value);
    if (ports.length === 0) {
      return '';
    }
    return ports.join(',');
  }

  function mergePortLists(base, extra) {
    var merged = [];
    var seen = {};
    var addMany = function (items) {
      for (var i = 0; i < items.length; i++) {
        var port = normalizeProcessPort(items[i]);
        if (port === 0) {
          continue;
        }
        var key = String(port);
        if (seen[key]) {
          continue;
        }
        seen[key] = true;
        merged.push(port);
      }
    };

    addMany(normalizePortListFromValue(base));
    addMany(normalizePortListFromValue(extra));
    return merged;
  }

  function normalizeServiceChecks(value) {
    var rawItems = [];
    if (Array.isArray(value)) {
      rawItems = value.slice();
    } else {
      var text = value === null || value === undefined ? '' : String(value);
      var rows = text.split(/\r\n|\n|\r/);
      for (var rowIdx = 0; rowIdx < rows.length; rowIdx++) {
        var row = String(rows[rowIdx] || '').trim();
        if (!row) {
          continue;
        }
        var chunks = row.split(/\s*[;|]+\s*/);
        for (var chunkIdx = 0; chunkIdx < chunks.length; chunkIdx++) {
          var chunk = String(chunks[chunkIdx] || '').trim();
          if (chunk) {
            rawItems.push(chunk);
          }
        }
      }
    }

    var list = [];
    var seen = {};
    for (var i = 0; i < rawItems.length; i++) {
      var item = rawItems[i];
      var label = '';
      var port = 0;

      if (item && typeof item === 'object' && !Array.isArray(item)) {
        label = String(item.label || item.name || item.descripcion || item.description || '').trim();
        port = normalizeProcessPort(item.port);
      } else {
        var line = String(item || '').trim();
        if (!line || line.charAt(0) === '#') {
          continue;
        }

        var match = line.match(/^(.*?)[\s]*[:=,][\s]*([0-9]{1,5})$/);
        if (match) {
          label = String(match[1] || '').trim();
          port = normalizeProcessPort(match[2]);
        } else {
          port = normalizeProcessPort(line);
        }
      }

      if (port === 0) {
        continue;
      }
      if (!label) {
        label = 'Servicio ' + port;
      }

      var key = label.toLowerCase() + '|' + String(port);
      if (seen[key]) {
        continue;
      }
      seen[key] = true;
      list.push({ label: label, port: port });
      if (list.length >= 40) {
        break;
      }
    }

    return list;
  }

  function parseServiceChecksText(text) {
    return normalizeServiceChecks(text);
  }

  function formatServiceChecksText(value) {
    var checks = normalizeServiceChecks(value);
    if (checks.length === 0) {
      return '';
    }

    var lines = [];
    for (var i = 0; i < checks.length; i++) {
      var item = checks[i];
      lines.push(item.label + ':' + item.port);
    }
    return lines.join('\n');
  }

  function isTruthy(value) {
    if (value === true) {
      return true;
    }
    if (typeof value === 'number') {
      return value > 0;
    }
    if (typeof value === 'string') {
      var text = value.trim().toLowerCase();
      return text === '1' || text === 'true' || text === 'yes' || text === 'si' || text === 'on';
    }
    return false;
  }

  function normalizeSshOs(value) {
    if (value === 'linux' || value === 'windows') {
      return value;
    }
    return 'auto';
  }

  function parseModeSelection(modeValue, sshOsFallback) {
    var raw = String(modeValue || '').toLowerCase();
    if (raw === 'pull_http') {
      return { mode: 'pull_http', sshOs: normalizeSshOs(sshOsFallback || 'auto') };
    }

    if (raw === 'pull_ssh_windows') {
      return { mode: 'pull_ssh', sshOs: 'windows' };
    }
    if (raw === 'pull_ssh_linux') {
      return { mode: 'pull_ssh', sshOs: 'linux' };
    }
    if (raw === 'pull_ssh_auto' || raw === 'pull_ssh') {
      return { mode: 'pull_ssh', sshOs: 'auto' };
    }

    return { mode: 'push', sshOs: normalizeSshOs(sshOsFallback || 'auto') };
  }

  function modeSelectionValue(mode, sshOs) {
    var normalizedMode = normalizeMode(mode);
    if (normalizedMode !== 'pull_ssh') {
      return normalizedMode;
    }

    var normalizedSshOs = normalizeSshOs(sshOs);
    if (normalizedSshOs === 'windows') {
      return 'pull_ssh_windows';
    }
    if (normalizedSshOs === 'linux') {
      return 'pull_ssh_linux';
    }

    return 'pull_ssh_auto';
  }

  function normalizeThreshold(value, fallback) {
    var num = parseFloat(value);
    if (isNaN(num)) {
      return fallback;
    }
    return String(Math.max(1, Math.min(100, num)));
  }

  function normalizeUiPollMs(value, fallback) {
    var base = parseInt(fallback, 10);
    if (!isFinite(base) || base < 100) {
      base = 5000;
    }

    var ms = parseInt(value, 10);
    if (!isFinite(ms)) {
      ms = base;
    }

    if (ms < 100) {
      ms = 100;
    }
    if (ms > 30000) {
      ms = 30000;
    }

    return ms;
  }

  function formatMsLabel(ms) {
    var safeMs = normalizeUiPollMs(ms, defaultUiPollMs);
    if (safeMs < 1000) {
      return safeMs + ' ms';
    }

    var seconds = safeMs / 1000;
    var secText = Math.abs(seconds - Math.round(seconds)) < 0.0001
      ? String(Math.round(seconds))
      : seconds.toFixed(1);
    return secText + ' s (' + safeMs + ' ms)';
  }

  function readStoredUiPollMs() {
    try {
      var stored = window.localStorage.getItem(UI_POLL_STORAGE_KEY);
      if (stored !== null && stored !== '') {
        return normalizeUiPollMs(stored, defaultUiPollMs);
      }
    } catch (error) {}
    return defaultUiPollMs;
  }

  function storeUiPollMs(ms) {
    try {
      window.localStorage.setItem(UI_POLL_STORAGE_KEY, String(normalizeUiPollMs(ms, defaultUiPollMs)));
    } catch (error) {}
  }

  function syncUiPollControl() {
    if (!refs.uiPollMs) {
      return;
    }
    refs.uiPollMs.value = String(normalizeUiPollMs(state.uiPollMs, defaultUiPollMs));
  }

  function applyUiPollInterval(ms) {
    state.uiPollMs = normalizeUiPollMs(ms, state.uiPollMs);
    applyMetricMotionCadence(state.uiPollMs);
    if (state.uiTimer !== null) {
      window.clearInterval(state.uiTimer);
      state.uiTimer = null;
    }
    state.uiTimer = window.setInterval(function () {
      triggerPullTick(false);
      loadState(false, false, { requestPull: false, background: true, quiet: true, maxPull: 1 });
    }, state.uiPollMs);
    updatePollInfo();
  }

  function applyMetricMotionCadence(pollMs) {
    var safePollMs = normalizeUiPollMs(pollMs, defaultUiPollMs);
    // Keep bar motion visually aligned with chart refresh rhythm.
    var transitionMs = Math.round(safePollMs * 0.55);
    if (transitionMs < 40) {
      transitionMs = 40;
    }
    if (transitionMs > 220) {
      transitionMs = 220;
    }
    document.documentElement.style.setProperty('--metric-fill-transition-ms', transitionMs + 'ms');
  }

  function updatePollInfo() {
    if (!refs.pollInfo) {
      return;
    }

    refs.pollInfo.textContent = 'Captura y refresco sincronizados cada ' + formatMsLabel(state.uiPollMs);
  }

  function normalizeSeconds(value, fallback, minValue, maxValue) {
    var min = typeof minValue === 'number' ? minValue : 2;
    var max = typeof maxValue === 'number' ? maxValue : 600;
    var defaultValue = typeof fallback === 'number' ? fallback : min;

    var num = parseInt(value, 10);
    if (!isFinite(num)) {
      num = defaultValue;
    }

    if (num < min) {
      num = min;
    }
    if (num > max) {
      num = max;
    }

    return num;
  }

  function normalizeDevicePollInput(value) {
    var raw = String(value === null || value === undefined ? '' : value).trim();
    if (raw === '') {
      return '';
    }

    var parsed = parseInt(raw, 10);
    if (!isFinite(parsed) || parsed <= 0) {
      return '';
    }

    return String(normalizeSeconds(parsed, defaultDevicePollSeconds, 2, 600));
  }

  function normalizeTheme(value) {
    var candidate = String(value || '').toLowerCase();
    for (var i = 0; i < ALLOWED_THEMES.length; i++) {
      if (ALLOWED_THEMES[i] === candidate) {
        return candidate;
      }
    }
    return 'dark';
  }

  function readStoredTheme() {
    try {
      var stored = window.localStorage.getItem(THEME_STORAGE_KEY);
      if (stored) {
        return normalizeTheme(stored);
      }
    } catch (error) {}

    return DEFAULT_THEME;
  }

  function applyTheme(theme, persist) {
    var nextTheme = normalizeTheme(theme);
    state.theme = nextTheme;
    document.body.setAttribute('data-theme', nextTheme);
    refs.themeMode.value = nextTheme;

    if (!persist) {
      return;
    }

    try {
      window.localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    } catch (error) {}
  }

  function normalizeCardDensity(value) {
    var candidate = String(value || '').toLowerCase();
    if (candidate === 'compacto' || candidate === 'compact') {
      return 'compacto';
    }
    return 'amplio';
  }

  function readStoredCardDensity() {
    try {
      var stored = window.localStorage.getItem(CARD_DENSITY_STORAGE_KEY);
      if (stored) {
        return normalizeCardDensity(stored);
      }
    } catch (error) {}
    return 'amplio';
  }

  function storeCardDensity(mode) {
    try {
      window.localStorage.setItem(CARD_DENSITY_STORAGE_KEY, normalizeCardDensity(mode));
    } catch (error) {}
  }

  function applyCardDensity(mode, persist) {
    var nextMode = normalizeCardDensity(mode);
    state.cardDensity = nextMode;
    document.body.setAttribute('data-card-density', nextMode);
    if (refs.cardDensityMode) {
      refs.cardDensityMode.value = nextMode;
    }
    if (persist) {
      storeCardDensity(nextMode);
      scheduleTaskCharts();
    }
  }

  function applySettingsFromResponse(settings) {
    if (!settings || typeof settings !== 'object') {
      updatePollInfo();
      return;
    }
    updatePollInfo();
  }

  function updateHistories(devices, generatedAt) {
    var keep = {};
    var list = Array.isArray(devices) ? devices : [];
    state.sampleSeq += 1;
    var sampleKey = 'sample-' + state.sampleSeq + '-' + String(generatedAt || Date.now());

    for (var i = 0; i < list.length; i++) {
      var device = list[i] || {};
      var deviceId = device.id || '';
      if (!deviceId) {
        continue;
      }

      keep[deviceId] = true;

      if (!state.histories[deviceId]) {
        state.histories[deviceId] = {
          cpu: [],
          ram: [],
          disk: [],
          network: [],
          lastSampleKey: ''
        };
      }

      var metrics = device.metrics || {};
      var history = state.histories[deviceId];
      if (history.lastSampleKey === sampleKey && history.cpu.length > 0) {
        continue;
      }

      pushHistoryValue(history.cpu, toMetricPercent(metrics.cpu), state.maxHistoryPoints);
      pushHistoryValue(history.ram, toMetricPercent(metrics.ram), state.maxHistoryPoints);
      pushHistoryValue(history.disk, toMetricPercent(metrics.disk), state.maxHistoryPoints);
      pushHistoryValue(history.network, toMetricPercent(metrics.network), state.maxHistoryPoints);
      history.lastSampleKey = sampleKey;
    }

    Object.keys(state.histories).forEach(function (id) {
      if (!keep[id]) {
        delete state.histories[id];
      }
    });
  }

  function toMetricPercent(value) {
    if (typeof value === 'number' && isFinite(value)) {
      return Math.max(0, Math.min(100, value));
    }

    if (typeof value === 'string' && value.trim() !== '') {
      var num = parseFloat(value);
      if (!isNaN(num) && isFinite(num)) {
        return Math.max(0, Math.min(100, num));
      }
    }

    return null;
  }

  function pushHistoryValue(target, value, maxLen) {
    target.push(value);
    while (target.length > maxLen) {
      target.shift();
    }
  }

  function scheduleTaskCharts() {
    if (state.chartFrame !== null) {
      return;
    }

    state.chartFrame = window.requestAnimationFrame(function () {
      state.chartFrame = null;
      drawTaskCharts();
    });
  }

  function drawTaskCharts() {
    if (!refs.deviceGrid) {
      return;
    }

    var canvases = refs.deviceGrid.querySelectorAll('canvas[data-role="task-chart"]');
    for (var i = 0; i < canvases.length; i++) {
      drawTaskChart(canvases[i]);
    }
  }

  function drawTaskChart(canvas) {
    var deviceId = canvas.getAttribute('data-id');
    if (!deviceId) {
      return;
    }

    var history = state.histories[deviceId] || null;
    var cssWidth = Math.max(120, canvas.clientWidth || 0);
    var cssHeight = Math.max(90, canvas.clientHeight || 0);
    var dpr = window.devicePixelRatio || 1;

    var targetWidth = Math.floor(cssWidth * dpr);
    var targetHeight = Math.floor(cssHeight * dpr);
    if (canvas.width !== targetWidth || canvas.height !== targetHeight) {
      canvas.width = targetWidth;
      canvas.height = targetHeight;
    }

    var ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }

    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    drawTaskGrid(ctx, cssWidth, cssHeight);

    if (!history || history.cpu.length < 2) {
      ctx.fillStyle = 'rgba(196, 220, 246, 0.72)';
      ctx.font = '12px "Plus Jakarta Sans", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Esperando metricas...', cssWidth / 2, cssHeight / 2 + 4);
      return;
    }

    drawTaskSeries(ctx, history.disk, cssWidth, cssHeight, {
      stroke: 'rgb(255, 196, 98)',
      fill: 'rgba(255, 196, 98, 0.13)'
    });
    drawTaskSeries(ctx, history.ram, cssWidth, cssHeight, {
      stroke: 'rgb(120, 241, 185)',
      fill: 'rgba(120, 241, 185, 0.14)'
    });
    drawTaskSeries(ctx, history.network, cssWidth, cssHeight, {
      stroke: 'rgb(174, 146, 255)',
      fill: 'rgba(174, 146, 255, 0.12)'
    });
    drawTaskSeries(ctx, history.cpu, cssWidth, cssHeight, {
      stroke: 'rgb(117, 216, 255)',
      fill: 'rgba(117, 216, 255, 0.16)'
    });
  }

  function drawTaskGrid(ctx, width, height) {
    var gradient = ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, 'rgba(15, 43, 73, 0.65)');
    gradient.addColorStop(1, 'rgba(9, 26, 45, 0.65)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);

    ctx.strokeStyle = 'rgba(142, 177, 216, 0.22)';
    ctx.lineWidth = 1;

    var horizontalLines = 4;
    for (var row = 1; row <= horizontalLines; row++) {
      var y = (height / (horizontalLines + 1)) * row + 0.5;
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
      ctx.stroke();
    }

    var verticalLines = 8;
    for (var col = 1; col <= verticalLines; col++) {
      var x = (width / (verticalLines + 1)) * col + 0.5;
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, height);
      ctx.stroke();
    }
  }

  function drawTaskSeries(ctx, values, width, height, palette) {
    if (!Array.isArray(values) || values.length < 2) {
      return;
    }

    var stepX = width / Math.max(1, values.length - 1);
    var firstIndex = -1;
    for (var i = 0; i < values.length; i++) {
      if (values[i] !== null) {
        firstIndex = i;
        break;
      }
    }

    if (firstIndex < 0) {
      return;
    }

    ctx.beginPath();
    var started = false;
    for (var idx = 0; idx < values.length; idx++) {
      var value = values[idx];
      if (value === null || !isFinite(value)) {
        continue;
      }

      var x = idx * stepX;
      var y = height - (Math.max(0, Math.min(100, value)) / 100) * height;

      if (!started) {
        ctx.moveTo(x, y);
        started = true;
      } else {
        ctx.lineTo(x, y);
      }
    }

    if (!started) {
      return;
    }

    ctx.strokeStyle = palette.stroke;
    ctx.lineWidth = 1.8;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();

    ctx.lineTo(width, height);
    ctx.lineTo(0, height);
    ctx.closePath();
    ctx.fillStyle = palette.fill;
    ctx.fill();
  }

  function updateDetailHeader(device) {
    if (!refs.detailTitle || !refs.detailSubtitle) {
      return;
    }

    var name = device && device.name ? device.name : 'Sin equipo seleccionado';
    var host = device && device.host ? device.host : 'N/D';
    var group = device && device.group ? device.group : '';

    refs.detailTitle.textContent = name;
    refs.detailSubtitle.textContent = group ? host + ' | ' + group : host;

    if (refs.detailDownloadEventsBtn && endpoints.ops && device && device.id) {
      refs.detailDownloadEventsBtn.href = buildOpsActionUrl('events_download', device.id, {});
    }
  }

  function openDeviceDetail(device) {
    if (!device || !device.id) {
      return;
    }
    if (!endpoints.ops) {
      showFlash('No se configuro endpoint de operaciones para detalle.', 'error', 4000);
      return;
    }

    state.detailDeviceId = String(device.id);
    state.detailOpen = true;
    state.detailLogContext = null;
    cancelDetailLogSearchDebounce();
    state.detailLogRequestSeq += 1;
    state.detailHistoryRequestSeq += 1;
    state.detailEventsRequestSeq += 1;
    state.detailLinuxLogsRequestSeq += 1;
    state.detailWindowsLogsRequestSeq += 1;
    updateDetailHeader(device);

    if (refs.detailHistoryHint) {
      refs.detailHistoryHint.textContent = 'Cargando historico...';
    }
    if (refs.detailEventsList) {
      refs.detailEventsList.innerHTML = '<p class="hint">Cargando eventos...</p>';
    }
    if (refs.detailLinuxLogsList) {
      refs.detailLinuxLogsList.innerHTML = '<p class="hint">Usa "Listar logs Linux" para consultar archivos remotos.</p>';
    }
    if (refs.detailWindowsLogsList) {
      refs.detailWindowsLogsList.innerHTML = '<p class="hint">Usa "Listar logs IIS" para consultar archivos remotos de Windows.</p>';
    }
    if (refs.detailLogSearchInput) {
      refs.detailLogSearchInput.value = '';
    }
    if (refs.detailLogLineLimit) {
      refs.detailLogLineLimit.value = String(DEFAULT_LOG_VIEW_LINE_LIMIT);
    }
    setLogSearchInputMatchState(-1, '');
    if (refs.detailLogViewerFile) {
      refs.detailLogViewerFile.textContent = 'Selecciona un archivo con el boton Ver.';
    }
    if (refs.detailLogViewerHint) {
      refs.detailLogViewerHint.textContent = 'Sin contenido cargado.';
    }
    if (refs.detailLogViewer) {
      refs.detailLogViewer.textContent = '';
    }

    if (refs.detailModal) {
      refs.detailModal.hidden = false;
      document.body.classList.add('detail-open');
    }

    reloadCurrentDeviceDetail();
  }

  function closeDeviceDetail() {
    cancelDetailLogSearchDebounce();
    state.detailLogRequestSeq += 1;
    state.detailHistoryRequestSeq += 1;
    state.detailEventsRequestSeq += 1;
    state.detailLinuxLogsRequestSeq += 1;
    state.detailWindowsLogsRequestSeq += 1;
    state.detailOpen = false;
    state.detailDeviceId = '';
    state.detailLogContext = null;
    if (refs.detailModal) {
      refs.detailModal.hidden = true;
    }
    document.body.classList.remove('detail-open');
  }

  function reloadCurrentDeviceDetail() {
    if (!state.detailOpen || !state.detailDeviceId) {
      return;
    }

    var device = findDevice(state.detailDeviceId);
    if (!device) {
      showFlash('No se encontro el equipo para detalle.', 'error', 3500);
      closeDeviceDetail();
      return;
    }

    updateDetailHeader(device);
    loadHistoryForDevice(device);
    loadEventsForDevice(device);
    if (isLikelyLinuxDevice(device)) {
      loadLinuxLogsForDevice(device, false);
    } else if (refs.detailLinuxLogsList) {
      refs.detailLinuxLogsList.innerHTML = '<p class="hint">El equipo no parece Linux. Puedes intentar manualmente con "Listar logs Linux".</p>';
    }

    if (isLikelyWindowsDevice(device)) {
      loadWindowsIisLogsForDevice(device, false);
    } else if (refs.detailWindowsLogsList) {
      refs.detailWindowsLogsList.innerHTML = '<p class="hint">El equipo no parece Windows por SSH. Puedes intentar manualmente con "Listar logs IIS".</p>';
    }
  }

  function buildOpsActionUrl(action, deviceId, extraParams) {
    var url = endpoints.ops;
    url = appendQueryParam(url, 'action', action);
    url = appendQueryParam(url, 'device_id', deviceId);

    var extra = extraParams || {};
    Object.keys(extra).forEach(function (key) {
      if (extra[key] === null || extra[key] === undefined || extra[key] === '') {
        return;
      }
      url = appendQueryParam(url, key, extra[key]);
    });

    return url;
  }

  function isActiveDetailDevice(deviceId) {
    var expected = normalizeDeviceId(deviceId);
    if (expected === '') {
      return false;
    }
    return state.detailOpen && normalizeDeviceId(state.detailDeviceId || '') === expected;
  }

  function isLikelyLinuxDevice(device) {
    var mode = normalizeMode(device && device.mode ? device.mode : '');
    if (mode !== 'pull_ssh') {
      return false;
    }

    var configuredOs = normalizeSshOs(device && device.ssh_os ? device.ssh_os : 'auto');
    if (configuredOs === 'linux') {
      return true;
    }
    if (configuredOs === 'windows') {
      return false;
    }

    var metrics = device && device.metrics ? device.metrics : {};
    var source = String(metrics.ssh_os_used || metrics.source || '').toLowerCase();
    if (source.indexOf('linux') >= 0) {
      return true;
    }
    if (source.indexOf('windows') >= 0) {
      return false;
    }
    return false;
  }

  function isLikelyWindowsDevice(device) {
    var mode = normalizeMode(device && device.mode ? device.mode : '');
    if (mode !== 'pull_ssh') {
      return false;
    }

    var configuredOs = normalizeSshOs(device && device.ssh_os ? device.ssh_os : 'auto');
    if (configuredOs === 'windows') {
      return true;
    }
    if (configuredOs === 'linux') {
      return false;
    }

    var metrics = device && device.metrics ? device.metrics : {};
    var source = String(metrics.ssh_os_used || metrics.source || '').toLowerCase();
    if (source.indexOf('windows') >= 0) {
      return true;
    }
    if (source.indexOf('linux') >= 0) {
      return false;
    }
    return false;
  }

  function loadHistoryForDevice(device) {
    if (!refs.detailHistoryCanvas || !refs.detailHistoryHint || !device || !device.id) {
      return;
    }
    var requestedDeviceId = normalizeDeviceId(device.id);
    if (!isActiveDetailDevice(requestedDeviceId)) {
      return;
    }

    state.detailHistoryRequestSeq += 1;
    var requestSeq = state.detailHistoryRequestSeq;

    refs.detailHistoryHint.textContent = 'Cargando historico por hora...';
    var url = buildOpsActionUrl('history', device.id, { hours: state.detailHistoryHours });
    getJSON(withCacheBust(url)).then(function (response) {
      if (requestSeq !== state.detailHistoryRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No se pudo consultar historico');
      }

      var points = Array.isArray(response.points) ? response.points : [];
      drawDetailHistoryChart(points);
      if (points.length === 0) {
        refs.detailHistoryHint.textContent = 'Sin datos historicos por ahora. Se llenan automaticamente conforme llegan metricas.';
        return;
      }

      var firstHour = points[0].hour ? formatDateTime(points[0].hour) : '';
      var lastHour = points[points.length - 1].hour ? formatDateTime(points[points.length - 1].hour) : '';
      refs.detailHistoryHint.textContent = 'Rango: ' + firstHour + ' -> ' + lastHour + ' | puntos: ' + points.length;
    }).catch(function (err) {
      if (requestSeq !== state.detailHistoryRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      refs.detailHistoryHint.textContent = err.message || 'Error consultando historico.';
    });
  }

  function drawDetailHistoryChart(points) {
    if (!refs.detailHistoryCanvas) {
      return;
    }

    var canvas = refs.detailHistoryCanvas;
    var cssWidth = Math.max(260, canvas.clientWidth || 920);
    var cssHeight = Math.max(150, canvas.clientHeight || 240);
    var dpr = window.devicePixelRatio || 1;
    var targetWidth = Math.floor(cssWidth * dpr);
    var targetHeight = Math.floor(cssHeight * dpr);

    if (canvas.width !== targetWidth || canvas.height !== targetHeight) {
      canvas.width = targetWidth;
      canvas.height = targetHeight;
    }

    var ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }

    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    drawTaskGrid(ctx, cssWidth, cssHeight);

    var list = Array.isArray(points) ? points : [];
    if (list.length < 1) {
      ctx.fillStyle = 'rgba(196, 220, 246, 0.78)';
      ctx.font = '12px "Plus Jakarta Sans", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Sin datos historicos', cssWidth / 2, cssHeight / 2 + 4);
      return;
    }

    var stepX = list.length > 1 ? cssWidth / (list.length - 1) : cssWidth;
    var barWidth = Math.max(3, Math.min(16, stepX * 0.7));
    var ramValues = [];
    var outageValues = [];

    for (var i = 0; i < list.length; i++) {
      var point = list[i] || {};
      var samples = parseInt(point.samples, 10);
      if (!isFinite(samples) || samples < 1) {
        samples = 1;
      }
      var ram = typeof point.ram_avg === 'number' ? point.ram_avg : null;
      ramValues.push(ram === null ? null : Math.max(0, Math.min(100, ram)));

      var redSamples = parseInt(point.red_samples, 10);
      if (!isFinite(redSamples) || redSamples < 0) {
        redSamples = 0;
      }
      var offlineSamples = parseInt(point.offline_samples, 10);
      if (!isFinite(offlineSamples) || offlineSamples < 0) {
        offlineSamples = 0;
      }
      var serviceIssueSamples = parseInt(point.service_issue_samples, 10);
      if (!isFinite(serviceIssueSamples) || serviceIssueSamples < 0) {
        serviceIssueSamples = 0;
      }
      var outagePct = ((redSamples + offlineSamples + serviceIssueSamples) / samples) * 100;
      outageValues.push(Math.max(0, Math.min(100, outagePct)));
    }

    ctx.fillStyle = 'rgba(240, 71, 71, 0.24)';
    for (var idx = 0; idx < outageValues.length; idx++) {
      var outVal = outageValues[idx];
      if (!isFinite(outVal) || outVal <= 0.01) {
        continue;
      }
      var xCenter = idx * stepX;
      var barHeight = (outVal / 100) * cssHeight;
      ctx.fillRect(xCenter - (barWidth / 2), cssHeight - barHeight, barWidth, barHeight);
    }

    ctx.beginPath();
    var started = false;
    for (var ramIdx = 0; ramIdx < ramValues.length; ramIdx++) {
      var ramVal = ramValues[ramIdx];
      if (ramVal === null || !isFinite(ramVal)) {
        continue;
      }
      var x = ramIdx * stepX;
      var y = cssHeight - (ramVal / 100) * cssHeight;
      if (!started) {
        ctx.moveTo(x, y);
        started = true;
      } else {
        ctx.lineTo(x, y);
      }
    }
    if (started) {
      ctx.strokeStyle = 'rgb(120, 241, 185)';
      ctx.lineWidth = 2;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      ctx.stroke();
    }

    ctx.fillStyle = 'rgba(160, 236, 197, 0.92)';
    ctx.font = '12px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('RAM promedio por hora', 8, 16);
    ctx.fillStyle = 'rgba(255, 167, 167, 0.9)';
    ctx.fillText('Caidas/errores por hora', 8, 32);
  }

  function loadEventsForDevice(device) {
    if (!refs.detailEventsList || !device || !device.id) {
      return;
    }
    var requestedDeviceId = normalizeDeviceId(device.id);
    if (!isActiveDetailDevice(requestedDeviceId)) {
      return;
    }

    state.detailEventsRequestSeq += 1;
    var requestSeq = state.detailEventsRequestSeq;

    refs.detailEventsList.innerHTML = '<p class="hint">Cargando eventos...</p>';
    var url = buildOpsActionUrl('events', device.id, { limit: 300 });
    getJSON(withCacheBust(url)).then(function (response) {
      if (requestSeq !== state.detailEventsRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No se pudo consultar eventos');
      }
      var events = Array.isArray(response.events) ? response.events : [];
      renderDetailEvents(events);
    }).catch(function (err) {
      if (requestSeq !== state.detailEventsRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      refs.detailEventsList.innerHTML = '<p class="hint">' + escapeHtml(err.message || 'Error consultando eventos.') + '</p>';
    });
  }

  function renderDetailEvents(events) {
    if (!refs.detailEventsList) {
      return;
    }

    var list = Array.isArray(events) ? events : [];
    if (list.length === 0) {
      refs.detailEventsList.innerHTML = '<p class="hint">Sin eventos registrados aun.</p>';
      return;
    }

    var html = '';
    for (var i = 0; i < list.length; i++) {
      var event = list[i] || {};
      var level = String(event.level || 'yellow').toLowerCase();
      if (level !== 'green' && level !== 'yellow' && level !== 'red') {
        level = 'yellow';
      }
      var ts = event.ts ? formatDateTime(event.ts) : '--';
      var type = event.type ? String(event.type) : 'event';
      var message = event.message ? String(event.message) : '';
      html += '<article class="event-item level-' + escapeAttr(level) + '">';
      html += '  <p class="event-meta"><strong>' + escapeHtml(ts) + '</strong> <span>' + escapeHtml(type) + '</span></p>';
      html += '  <p class="event-message">' + escapeHtml(message) + '</p>';
      html += '</article>';
    }

    refs.detailEventsList.innerHTML = html;
  }

  function renderRemoteLogList(listNode, device, os, directory, files) {
    if (!listNode || !device || !device.id) {
      return;
    }

    var list = Array.isArray(files) ? files : [];
    if (list.length === 0) {
      listNode.innerHTML = '<p class="hint">No hay archivos para mostrar en ese directorio.</p>';
      return;
    }

    var downloadAction = os === 'windows' ? 'windows_iis_log_download' : 'linux_log_download';
    var html = '';
    for (var i = 0; i < list.length; i++) {
      var item = list[i] || {};
      var name = item.name ? String(item.name) : '';
      if (!name) {
        continue;
      }
      var size = typeof item.size === 'number' ? item.size : parseInt(item.size, 10);
      if (!isFinite(size) || size < 0) {
        size = 0;
      }
      var modified = item.modified ? String(item.modified) : '--';
      var downloadUrl = buildOpsActionUrl(downloadAction, device.id, {
        directory: directory,
        file: name
      });

      html += '<article class="remote-log-item">';
      html += '  <div class="remote-log-meta">';
      html += '    <strong>' + escapeHtml(name) + '</strong>';
      html += '    <p>' + escapeHtml(modified) + ' | ' + escapeHtml(formatBytes(size)) + '</p>';
      html += '  </div>';
      html += '  <div class="remote-log-actions">';
      html += '    <button type="button" class="btn btn-ghost btn-mini" data-action="view-remote-log" data-device-id="' + escapeAttr(device.id) + '" data-os="' + escapeAttr(os) + '" data-directory="' + escapeAttr(directory) + '" data-file="' + escapeAttr(name) + '">Ver</button>';
      html += '    <a class="btn btn-secondary btn-mini" href="' + escapeAttr(downloadUrl) + '" download>Descargar</a>';
      html += '  </div>';
      html += '</article>';
    }

    listNode.innerHTML = html || '<p class="hint">No hay archivos descargables.</p>';
  }

  function loadLinuxLogsForDevice(device, force) {
    if (!refs.detailLinuxLogsList || !refs.detailLinuxLogsDir || !device || !device.id) {
      return;
    }
    var requestedDeviceId = normalizeDeviceId(device.id);
    if (!isActiveDetailDevice(requestedDeviceId)) {
      return;
    }

    state.detailLinuxLogsRequestSeq += 1;
    var requestSeq = state.detailLinuxLogsRequestSeq;

    if (!force && !isLikelyLinuxDevice(device)) {
      refs.detailLinuxLogsList.innerHTML = '<p class="hint">Este equipo no parece Linux por SSH.</p>';
      return;
    }

    refs.detailLinuxLogsList.innerHTML = '<p class="hint">Consultando archivos Linux remotos...</p>';
    var directory = '/opt/spring/bancamovil/logs';
    refs.detailLinuxLogsDir.textContent = 'Directorio: ' + directory;

    var url = buildOpsActionUrl('linux_logs_list', device.id, { directory: directory });
    getJSON(withCacheBust(url)).then(function (response) {
      if (requestSeq !== state.detailLinuxLogsRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No se pudo listar logs Linux');
      }

      var dir = response.directory ? String(response.directory) : directory;
      refs.detailLinuxLogsDir.textContent = 'Directorio: ' + dir;
      if (response.missing_dir) {
        refs.detailLinuxLogsList.innerHTML = '<p class="hint">El directorio remoto no existe.</p>';
        return;
      }

      renderRemoteLogList(refs.detailLinuxLogsList, device, 'linux', dir, response.files);
    }).catch(function (err) {
      if (requestSeq !== state.detailLinuxLogsRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      refs.detailLinuxLogsList.innerHTML = '<p class="hint">' + escapeHtml(err.message || 'Error consultando logs Linux.') + '</p>';
    });
  }

  function loadWindowsIisLogsForDevice(device, force) {
    if (!refs.detailWindowsLogsList || !refs.detailWindowsLogsDir || !device || !device.id) {
      return;
    }
    var requestedDeviceId = normalizeDeviceId(device.id);
    if (!isActiveDetailDevice(requestedDeviceId)) {
      return;
    }

    state.detailWindowsLogsRequestSeq += 1;
    var requestSeq = state.detailWindowsLogsRequestSeq;

    if (!force && !isLikelyWindowsDevice(device)) {
      refs.detailWindowsLogsList.innerHTML = '<p class="hint">Este equipo no parece Windows por SSH.</p>';
      return;
    }

    refs.detailWindowsLogsList.innerHTML = '<p class="hint">Consultando archivos IIS remotos...</p>';
    var directory = 'C:\\inetpub\\logs\\LogFiles';
    refs.detailWindowsLogsDir.textContent = 'Directorio: ' + directory;

    var url = buildOpsActionUrl('windows_iis_logs_list', device.id, { directory: directory });
    getJSON(withCacheBust(url)).then(function (response) {
      if (requestSeq !== state.detailWindowsLogsRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No se pudo listar logs IIS');
      }

      var dir = response.directory ? String(response.directory) : directory;
      refs.detailWindowsLogsDir.textContent = 'Directorio: ' + dir;
      if (response.missing_dir) {
        refs.detailWindowsLogsList.innerHTML = '<p class="hint">El directorio IIS remoto no existe.</p>';
        return;
      }

      renderRemoteLogList(refs.detailWindowsLogsList, device, 'windows', dir, response.files);
    }).catch(function (err) {
      if (requestSeq !== state.detailWindowsLogsRequestSeq || !isActiveDetailDevice(requestedDeviceId)) {
        return;
      }
      refs.detailWindowsLogsList.innerHTML = '<p class="hint">' + escapeHtml(err.message || 'Error consultando logs IIS.') + '</p>';
    });
  }

  function openLogPreviewFromButton(button) {
    if (!button) {
      return;
    }

    var os = String(button.getAttribute('data-os') || '').toLowerCase();
    if (os !== 'linux' && os !== 'windows') {
      return;
    }

    var fileName = String(button.getAttribute('data-file') || '').trim();
    var directory = String(button.getAttribute('data-directory') || '').trim();
    if (!fileName || !directory) {
      return;
    }

    state.detailLogContext = {
      os: os,
      file: fileName,
      directory: directory
    };
    cancelDetailLogSearchDebounce();

    if (refs.detailLogSearchInput) {
      refs.detailLogSearchInput.value = '';
    }
    setLogSearchInputMatchState(-1, '');
    loadCurrentLogPreview('');
  }

  function onDetailLogSearchInput() {
    cancelDetailLogSearchDebounce();
    if (!state.detailLogContext) {
      return;
    }

    var query = refs.detailLogSearchInput ? String(refs.detailLogSearchInput.value || '').trim() : '';
    state.detailLogSearchDebounceTimer = setTimeout(function () {
      state.detailLogSearchDebounceTimer = null;
      loadCurrentLogPreview(query);
    }, LOG_SEARCH_DEBOUNCE_MS);
  }

  function cancelDetailLogSearchDebounce() {
    if (state.detailLogSearchDebounceTimer) {
      clearTimeout(state.detailLogSearchDebounceTimer);
      state.detailLogSearchDebounceTimer = null;
    }
  }

  function getDetailLogLineLimit() {
    var value = refs.detailLogLineLimit ? parseInt(refs.detailLogLineLimit.value, 10) : DEFAULT_LOG_VIEW_LINE_LIMIT;
    if (!isFinite(value)) {
      return DEFAULT_LOG_VIEW_LINE_LIMIT;
    }
    if (value <= 0) {
      return 0;
    }
    if (value > MAX_LOG_VIEW_LINE_LIMIT) {
      return MAX_LOG_VIEW_LINE_LIMIT;
    }
    if (value < 20) {
      return 20;
    }
    return value;
  }

  function setLogSearchInputMatchState(matches, query) {
    if (!refs.detailLogSearchInput) {
      return;
    }

    refs.detailLogSearchInput.classList.remove('has-matches', 'no-matches');
    var normalizedQuery = typeof query === 'string' ? query.trim() : '';
    if (!normalizedQuery) {
      return;
    }

    var numericMatches = parseInt(matches, 10);
    if (!isFinite(numericMatches)) {
      return;
    }
    if (numericMatches > 0) {
      refs.detailLogSearchInput.classList.add('has-matches');
    } else if (numericMatches === 0) {
      refs.detailLogSearchInput.classList.add('no-matches');
    }
  }

  function parseSearchTerms(query) {
    var raw = typeof query === 'string' ? query.trim() : '';
    if (!raw) {
      return [];
    }

    var parts = raw.split(/\s+/);
    var unique = {};
    var terms = [];
    for (var i = 0; i < parts.length; i++) {
      var part = String(parts[i] || '').trim();
      if (!part) {
        continue;
      }
      var key = part.toLowerCase();
      if (unique[key]) {
        continue;
      }
      unique[key] = true;
      terms.push(part);
      if (terms.length >= 12) {
        break;
      }
    }
    return terms;
  }

  function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function renderLogPreviewText(text, query) {
    if (!refs.detailLogViewer) {
      return;
    }

    var normalizedText = typeof text === 'string' ? text : '';
    if (!normalizedText) {
      refs.detailLogViewer.textContent = '(Sin lineas para mostrar con el filtro actual).';
      return;
    }

    var terms = parseSearchTerms(query);
    if (terms.length === 0) {
      refs.detailLogViewer.textContent = normalizedText;
      return;
    }

    terms.sort(function (a, b) {
      return b.length - a.length;
    });
    var escapedText = escapeHtml(normalizedText);
    var pattern = terms.map(function (term) {
      return escapeRegExp(escapeHtml(term));
    }).join('|');

    if (!pattern) {
      refs.detailLogViewer.textContent = normalizedText;
      return;
    }

    var regex = new RegExp('(' + pattern + ')', 'gi');
    refs.detailLogViewer.innerHTML = escapedText.replace(regex, '<mark class="log-view-highlight">$1</mark>');
  }

  function searchInCurrentLogPreview() {
    cancelDetailLogSearchDebounce();
    if (!state.detailLogContext) {
      if (refs.detailLogViewerHint) {
        refs.detailLogViewerHint.textContent = 'Selecciona un archivo con el boton Ver antes de buscar.';
      }
      return;
    }

    var query = refs.detailLogSearchInput ? String(refs.detailLogSearchInput.value || '').trim() : '';
    loadCurrentLogPreview(query);
  }

  function reloadCurrentLogPreview() {
    cancelDetailLogSearchDebounce();
    if (!state.detailLogContext) {
      if (refs.detailLogViewerHint) {
        refs.detailLogViewerHint.textContent = 'Selecciona un archivo con el boton Ver antes de recargar.';
      }
      return;
    }

    var query = refs.detailLogSearchInput ? String(refs.detailLogSearchInput.value || '').trim() : '';
    loadCurrentLogPreview(query);
  }

  function loadCurrentLogPreview(queryText) {
    if (!refs.detailLogViewer || !refs.detailLogViewerHint || !state.detailLogContext) {
      return;
    }

    var device = findDevice(state.detailDeviceId || '');
    if (!device || !device.id) {
      refs.detailLogViewerHint.textContent = 'No hay equipo seleccionado para cargar log.';
      return;
    }

    var context = state.detailLogContext;
    var query = typeof queryText === 'string' ? queryText.trim() : '';
    var lineLimit = getDetailLogLineLimit();
    var action = context.os === 'windows' ? 'windows_iis_log_view' : 'linux_log_view';
    var params = {
      directory: context.directory,
      file: context.file,
      lines: lineLimit
    };
    if (query) {
      params.query = query;
    }

    var osLabel = context.os === 'windows' ? 'IIS Windows' : 'Linux';
    if (refs.detailLogViewerFile) {
      refs.detailLogViewerFile.textContent = osLabel + ' | ' + context.file;
    }
    refs.detailLogViewerHint.textContent = 'Cargando contenido del log...';
    refs.detailLogViewer.textContent = '';
    setLogSearchInputMatchState(-1, query);

    state.detailLogRequestSeq += 1;
    var requestSeq = state.detailLogRequestSeq;
    var url = buildOpsActionUrl(action, device.id, params);
    getJSON(withCacheBust(url)).then(function (response) {
      if (requestSeq !== state.detailLogRequestSeq) {
        return;
      }
      if (!response || !response.ok) {
        throw new Error(response && response.error ? response.error : 'No se pudo leer contenido del log');
      }

      var text = response.text ? String(response.text) : '';
      renderLogPreviewText(text, query);

      var searched = !!response.searched;
      var lines = parseInt(response.lines, 10);
      if (!isFinite(lines)) {
        lines = lineLimit;
      }
      var matches = parseInt(response.matches, 10);
      if (!isFinite(matches)) {
        matches = -1;
      }
      var truncated = !!response.truncated;
      var previewLimitBytes = parseInt(response.preview_limit_bytes, 10);
      if (!isFinite(previewLimitBytes) || previewLimitBytes < 1) {
        previewLimitBytes = 0;
      }
      setLogSearchInputMatchState(matches, query);

      if (searched) {
        if (matches >= 0) {
          if (lines === 0) {
            refs.detailLogViewerHint.textContent = 'Busqueda aplicada sobre contenido completo. Coincidencias: ' + matches + '.';
          } else {
            refs.detailLogViewerHint.textContent = 'Busqueda aplicada. Coincidencias: ' + matches + '.';
          }
        } else {
          refs.detailLogViewerHint.textContent = 'Busqueda aplicada.';
        }
      } else {
        if (lines === 0) {
          refs.detailLogViewerHint.textContent = 'Mostrando contenido completo.';
        } else {
          refs.detailLogViewerHint.textContent = 'Mostrando ultimas ' + lines + ' lineas.';
        }
      }
      if (truncated) {
        if (previewLimitBytes > 0) {
          refs.detailLogViewerHint.textContent += ' Vista truncada a ' + formatBytes(previewLimitBytes) + ' para evitar errores. Usa Descargar para archivo completo.';
        } else {
          refs.detailLogViewerHint.textContent += ' Vista truncada para evitar errores. Usa Descargar para archivo completo.';
        }
      }
    }).catch(function (err) {
      if (requestSeq !== state.detailLogRequestSeq) {
        return;
      }
      refs.detailLogViewerHint.textContent = err.message || 'Error leyendo log remoto.';
      refs.detailLogViewer.textContent = '';
      setLogSearchInputMatchState(-1, query);
    });
  }

  function formatBytes(bytes) {
    var size = parseInt(bytes, 10);
    if (!isFinite(size) || size < 1) {
      return '0 B';
    }
    var units = ['B', 'KB', 'MB', 'GB'];
    var index = 0;
    var value = size;
    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }
    var text = value >= 100 ? value.toFixed(0) : value.toFixed(1);
    return text + ' ' + units[index];
  }

  function findDevice(id) {
    var normalizedId = normalizeDeviceId(id);
    if (normalizedId === '') {
      return null;
    }

    for (var i = 0; i < state.devices.length; i++) {
      if (normalizeDeviceId(state.devices[i].id) === normalizedId) {
        return state.devices[i];
      }
    }
    return null;
  }

  function normalizeDeviceId(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value).trim();
  }

  function getEventElementTarget(event) {
    var target = event && event.target ? event.target : null;
    if (!target) {
      return null;
    }
    if (target.nodeType === 3 && target.parentElement) {
      target = target.parentElement;
    }
    if (target.nodeType !== 1 || typeof target.closest !== 'function') {
      return null;
    }
    return target;
  }

  function pruneSelection() {
    var allowed = {};
    for (var i = 0; i < state.devices.length; i++) {
      allowed[state.devices[i].id] = true;
    }

    Object.keys(state.selected).forEach(function (id) {
      if (!allowed[id]) {
        delete state.selected[id];
      }
    });

    updateBulkDeleteState();
  }

  function updateSummary(summary) {
    refs.sumTotal.textContent = String(summary.total || 0);
    refs.sumGreen.textContent = String(summary.green || 0);
    refs.sumYellow.textContent = String(summary.yellow || 0);
    refs.sumRed.textContent = String(summary.red || 0);
  }

  function updateLastSync(isoDate) {
    if (!isoDate) {
      refs.lastSync.textContent = 'Sin sincronizacion aun';
      return;
    }

    refs.lastSync.textContent = 'Ultima sincronizacion: ' + formatDateTime(isoDate);
  }

  function render() {
    if (state.draggingId) {
      return;
    }

    var filter = refs.searchInput.value.trim().toLowerCase();
    var list = orderDevicesForRender(state.devices);

    if (filter !== '') {
      list = list.filter(function (device) {
        var target = [device.name || '', device.host || '', device.group || ''].join(' ').toLowerCase();
        return target.indexOf(filter) >= 0;
      });
    }

    if (list.length === 0) {
      refs.deviceGrid.innerHTML = '<div class="empty-state">No hay equipos para mostrar con el filtro actual.</div>';
      updateBulkDeleteState();
      scheduleTaskCharts();
      return;
    }

    var existingCards = {};
    var cards = refs.deviceGrid.querySelectorAll('.device-card[data-device-id]');
    for (var cardIdx = 0; cardIdx < cards.length; cardIdx++) {
      var cardId = cards[cardIdx].getAttribute('data-device-id') || '';
      if (cardId) {
        existingCards[cardId] = cards[cardIdx];
      }
    }

    var usedIds = {};
    var emptyNode = refs.deviceGrid.querySelector('.empty-state');
    if (emptyNode) {
      emptyNode.remove();
    }

    for (var i = 0; i < list.length; i++) {
      var device = list[i];
      var id = String(device.id || '');
      if (!id) {
        continue;
      }

      var existingCard = existingCards[id] || null;
      var cardNode = existingCard || createDeviceCardElement(device);
      updateDeviceCardElement(cardNode, device);
      refs.deviceGrid.appendChild(cardNode);
      usedIds[id] = true;
      if (existingCards[id]) {
        delete existingCards[id];
      }
    }

    Object.keys(existingCards).forEach(function (leftId) {
      if (usedIds[leftId]) {
        return;
      }
      var stale = existingCards[leftId];
      if (stale && stale.parentNode === refs.deviceGrid) {
        stale.remove();
      }
    });

    updateBulkDeleteState();
    scheduleTaskCharts();
  }

  function orderDevicesForRender(devices) {
    var list = Array.isArray(devices) ? devices.slice() : [];
    if (list.length < 2) {
      return list;
    }

    var orderRank = {};
    for (var i = 0; i < state.cardOrder.length; i++) {
      orderRank[state.cardOrder[i]] = i;
    }

    list.sort(function (a, b) {
      var aId = String(a && a.id ? a.id : '');
      var bId = String(b && b.id ? b.id : '');
      var aHasRank = Object.prototype.hasOwnProperty.call(orderRank, aId);
      var bHasRank = Object.prototype.hasOwnProperty.call(orderRank, bId);

      if (aHasRank && bHasRank && orderRank[aId] !== orderRank[bId]) {
        return orderRank[aId] - orderRank[bId];
      }
      if (aHasRank && !bHasRank) {
        return -1;
      }
      if (!aHasRank && bHasRank) {
        return 1;
      }

      var aName = String(a && a.name ? a.name : '').toLowerCase();
      var bName = String(b && b.name ? b.name : '').toLowerCase();
      if (aName === bName) {
        return aId.localeCompare(bId);
      }
      return aName.localeCompare(bName);
    });

    return list;
  }

  function createDeviceCardElement(device) {
    var template = document.createElement('template');
    template.innerHTML = renderDeviceCard(device);
    return template.content.firstElementChild;
  }

  function updateDeviceCardElement(card, device) {
    if (!card) {
      return;
    }

    var view = buildDeviceCardData(device);
    card.setAttribute('data-device-id', view.id);
    card.setAttribute('draggable', 'true');
    card.classList.remove('status-green');
    card.classList.remove('status-yellow');
    card.classList.remove('status-red');
    card.classList.add('status-' + view.overall);
    if (state.draggingId === view.id) {
      card.classList.add('dragging');
    } else {
      card.classList.remove('dragging');
    }

    var checkbox = card.querySelector('input[data-role="select-device"]');
    if (checkbox) {
      checkbox.checked = view.checked;
      checkbox.setAttribute('data-id', view.id);
    }

    var nameNode = card.querySelector('[data-role="device-name"]');
    if (nameNode) {
      nameNode.textContent = view.name;
    }

    var hostNode = card.querySelector('[data-role="device-host"]');
    if (hostNode) {
      hostNode.textContent = view.host;
    }

    var statusNode = card.querySelector('[data-role="device-status"]');
    if (statusNode) {
      statusNode.classList.remove('green');
      statusNode.classList.remove('yellow');
      statusNode.classList.remove('red');
      statusNode.classList.add(view.overall);
      statusNode.textContent = view.statusLabel;
    }

    var metaNode = card.querySelector('[data-role="device-meta"]');
    if (metaNode) {
      metaNode.innerHTML = renderMetaChips(view);
    }

    updateMetricNode(card, 'cpu', view.metrics.cpu);
    updateMetricNode(card, 'ram', view.metrics.ram);
    updateMetricNode(card, 'disk', view.metrics.disk);
    updateMetricNode(card, 'network', view.metrics.network);

    var cpuLegend = card.querySelector('[data-role="legend-cpu"]');
    if (cpuLegend) {
      cpuLegend.textContent = 'CPU ' + view.cpuText;
    }
    var ramLegend = card.querySelector('[data-role="legend-ram"]');
    if (ramLegend) {
      ramLegend.textContent = 'RAM ' + view.ramText;
    }
    var diskLegend = card.querySelector('[data-role="legend-disk"]');
    if (diskLegend) {
      diskLegend.textContent = 'DISK ' + view.diskText;
    }
    var networkLegend = card.querySelector('[data-role="legend-network"]');
    if (networkLegend) {
      networkLegend.textContent = 'RED ' + view.networkText;
    }

    var canvas = card.querySelector('canvas[data-role="task-chart"]');
    if (canvas) {
      canvas.setAttribute('data-id', view.id);
    }

    var ageNode = card.querySelector('[data-role="age-text"]');
    if (ageNode) {
      ageNode.textContent = view.ageText;
    }

    var footerInfo = card.querySelector('.device-footer-info');
    var errorNode = card.querySelector('[data-role="error-text"]');
    if (view.errorText) {
      if (!errorNode && footerInfo) {
        errorNode = document.createElement('p');
        errorNode.className = 'small small-error';
        errorNode.setAttribute('data-role', 'error-text');
        footerInfo.appendChild(errorNode);
      }
      if (errorNode) {
        errorNode.textContent = view.errorText;
        errorNode.title = view.lastError;
      }
    } else if (errorNode) {
      errorNode.remove();
    }

    var actionsNode = card.querySelector('[data-role="device-actions"]');
    if (actionsNode) {
      actionsNode.innerHTML = renderCardActions(view);
    }
  }

  function updateMetricNode(card, metricType, metricView) {
    var valueNode = card.querySelector('[data-role="metric-value"][data-metric="' + metricType + '"]');
    if (valueNode) {
      valueNode.classList.remove('green');
      valueNode.classList.remove('yellow');
      valueNode.classList.remove('red');
      valueNode.classList.add(metricView.status);
      valueNode.textContent = metricView.text;
    }

    var fillNode = card.querySelector('[data-role="metric-fill"][data-metric="' + metricType + '"]');
    if (fillNode) {
      fillNode.style.width = metricView.width.toFixed(1) + '%';
    }

    var extraNode = card.querySelector('[data-role="metric-extra"][data-metric="' + metricType + '"]');
    if (extraNode) {
      var detailText = metricView && typeof metricView.detailText === 'string' ? metricView.detailText : '';
      extraNode.textContent = detailText;
      extraNode.hidden = !detailText;
    }
  }

  function buildDeviceCardData(device) {
    var metrics = device.metrics || {};
    var status = device.status || {};
    var overall = status.overall || 'red';
    if (overall !== 'green' && overall !== 'yellow' && overall !== 'red') {
      overall = 'red';
    }
    var thresholds = normalizeMetricThresholds(device.thresholds || {});
    var cpuStatus = metricStatusFromPercent(metrics.cpu, thresholds.cpu.warning, thresholds.cpu.critical, 'red');
    var ramStatus = metricStatusFromPercent(metrics.ram, thresholds.ram.warning, thresholds.ram.critical, 'red');
    var diskStatus = metricStatusFromPercent(metrics.disk, thresholds.disk.warning, thresholds.disk.critical, 'red');
    var networkStatus = metricStatusFromPercent(metrics.network, thresholds.network.warning, thresholds.network.critical, 'green');

    var checked = !!state.selected[device.id];
    var devicePoll = parseInt(device.poll_interval_seconds, 10);
    var pollChipText = (!isFinite(devicePoll) || devicePoll <= 0)
      ? 'Sync ' + formatMsLabel(state.uiPollMs) + ' (dashboard)'
      : 'Sync ' + normalizeSeconds(devicePoll, defaultDevicePollSeconds, 2, 600) + ' s (equipo)';

    var lastError = typeof metrics.last_error === 'string' ? metrics.last_error.trim() : '';
    var ageText = metrics.age_seconds === null || metrics.age_seconds === undefined
      ? 'Sin datos'
      : 'Hace ' + formatAge(metrics.age_seconds);
    var ramUsageText = metricUsageText(metrics.ram_used_bytes, metrics.ram_total_bytes);
    var diskUsageText = metricUsageText(metrics.disk_used_bytes, metrics.disk_total_bytes);

    return {
      id: String(device.id || ''),
      checked: checked,
      name: device.name || 'Sin nombre',
      host: device.host || 'N/D',
      overall: overall,
      statusLabel: status.label || statusLabelFor(overall),
      modeText: device.mode || 'push',
      sourceText: metrics.source || '',
      groupText: device.group || '',
      pollChipText: pollChipText,
      processChipsHtml: renderProcessChips(device, metrics),
      ageText: ageText,
      errorText: lastError,
      lastError: lastError,
      cpuText: formatMetricShort(metrics.cpu),
      ramText: formatMetricShort(metrics.ram),
      diskText: formatMetricShort(metrics.disk),
      networkText: formatMetricShort(metrics.network),
      tokenVisible: (device.mode || '') === 'push',
      metrics: {
        cpu: buildMetricView(metrics.cpu, cpuStatus, ''),
        ram: buildMetricView(metrics.ram, ramStatus, ramUsageText),
        disk: buildMetricView(metrics.disk, diskStatus, diskUsageText),
        network: buildMetricView(metrics.network, networkStatus, '')
      }
    };
  }

  function metricStatusFromPercent(value, warning, critical, fallbackStatus) {
    var fallback = fallbackStatus === 'yellow' || fallbackStatus === 'red' ? fallbackStatus : 'green';
    if (typeof value !== 'number' || !isFinite(value)) {
      return fallback;
    }

    if (value >= critical) {
      return 'red';
    }
    if (value >= warning) {
      return 'yellow';
    }
    return 'green';
  }

  function normalizeMetricThresholds(input) {
    var source = input && typeof input === 'object' ? input : {};
    return {
      cpu: normalizeMetricThresholdPair(source.cpu, 70, 90),
      ram: normalizeMetricThresholdPair(source.ram, 75, 90),
      disk: normalizeMetricThresholdPair(source.disk, 80, 95),
      network: normalizeMetricThresholdPair(source.network, 70, 90)
    };
  }

  function normalizeMetricThresholdPair(value, defaultWarning, defaultCritical) {
    var source = value && typeof value === 'object' ? value : {};
    var warning = parseFloat(source.warning);
    var critical = parseFloat(source.critical);

    if (!isFinite(warning) || warning < 1 || warning > 100) {
      warning = defaultWarning;
    }
    if (!isFinite(critical) || critical < 1 || critical > 100) {
      critical = defaultCritical;
    }
    if (critical <= warning) {
      critical = Math.min(100, warning + 1);
    }

    return {
      warning: warning,
      critical: critical
    };
  }

  function toFiniteNumber(value) {
    if (typeof value === 'number') {
      return isFinite(value) ? value : null;
    }
    if (typeof value === 'string') {
      var trimmed = value.trim();
      if (trimmed === '') {
        return null;
      }
      var parsed = parseFloat(trimmed);
      return isFinite(parsed) ? parsed : null;
    }
    return null;
  }

  function formatMetricBytes(bytes) {
    var size = toFiniteNumber(bytes);
    if (size === null || size < 0) {
      return '--';
    }

    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var unitIdx = 0;
    var value = size;
    while (value >= 1024 && unitIdx < units.length - 1) {
      value /= 1024;
      unitIdx += 1;
    }

    var decimals = value >= 100 ? 0 : 1;
    if (unitIdx === 0) {
      decimals = 0;
    }
    return value.toFixed(decimals) + ' ' + units[unitIdx];
  }

  function metricUsageText(usedBytes, totalBytes) {
    var used = toFiniteNumber(usedBytes);
    var total = toFiniteNumber(totalBytes);
    if (used === null || total === null || total <= 0) {
      return '';
    }

    if (used < 0) {
      used = 0;
    }
    if (used > total) {
      used = total;
    }

    return formatMetricBytes(used) + ' / ' + formatMetricBytes(total);
  }

  function buildMetricView(value, status, detailText) {
    var numeric = typeof value === 'number' ? value : null;
    var width = numeric === null ? 0 : Math.max(0, Math.min(100, numeric));
    var normalizedStatus = status === 'green' || status === 'yellow' || status === 'red' ? status : 'red';
    var detail = typeof detailText === 'string' ? detailText : '';

    return {
      text: numeric === null ? '--' : numeric.toFixed(1) + '%',
      width: width,
      status: normalizedStatus,
      detailText: detail
    };
  }

  function renderMetaChips(view) {
    var html = '';
    html += '<span class="meta-chip">' + escapeHtml(view.modeText) + '</span>';
    html += '<span class="meta-chip chip-poll">' + escapeHtml(view.pollChipText) + '</span>';
    html += view.processChipsHtml;
    if (view.sourceText) {
      html += '<span class="meta-chip">' + escapeHtml(view.sourceText) + '</span>';
    }
    if (view.groupText) {
      html += '<span class="meta-chip">' + escapeHtml(view.groupText) + '</span>';
    }
    return html;
  }

  function renderCardActions(view) {
    var html = '';
    html += '<button type="button" class="btn btn-ghost" data-action="details" data-id="' + escapeAttr(view.id) + '">Detalle</button>';
    if (view.tokenVisible) {
      html += '<button type="button" class="btn btn-ghost" data-action="copy_token" data-id="' + escapeAttr(view.id) + '">Token</button>';
    }
    html += '<button type="button" class="btn btn-secondary" data-action="edit" data-id="' + escapeAttr(view.id) + '">Editar</button>';
    html += '<button type="button" class="btn btn-danger" data-action="delete" data-id="' + escapeAttr(view.id) + '">Eliminar</button>';
    return html;
  }

  function renderDeviceCard(device) {
    var view = buildDeviceCardData(device);
    var checked = view.checked ? 'checked' : '';

    return [
      '<article class="device-card status-' + escapeAttr(view.overall) + '" data-device-id="' + escapeAttr(view.id) + '" draggable="true">',
      '  <header class="device-head">',
      '    <input type="checkbox" data-role="select-device" data-id="' + escapeAttr(view.id) + '" ' + checked + '/>',
      '    <div>',
      '      <h3 class="device-title" data-role="device-name">' + escapeHtml(view.name) + '</h3>',
      '      <p class="device-host" data-role="device-host">' + escapeHtml(view.host) + '</p>',
      '    </div>',
      '    <span class="status-pill ' + escapeAttr(view.overall) + '" data-role="device-status">' + escapeHtml(view.statusLabel) + '</span>',
      '  </header>',
      '  <div class="device-meta" data-role="device-meta">',
      renderMetaChips(view),
      '  </div>',
      '  <section class="metrics">',
      metricRow('CPU', view.metrics.cpu, 'cpu'),
      metricRow('RAM', view.metrics.ram, 'ram'),
      metricRow('DISK', view.metrics.disk, 'disk'),
      metricRow('RED', view.metrics.network, 'network'),
      '  </section>',
      '  <section class="task-graph-wrap">',
      '    <div class="task-graph-head">',
      '      <p>Vista tipo Task Manager</p>',
      '      <div class="task-legend">',
      '        <span class="task-legend-item cpu" data-role="legend-cpu">CPU ' + escapeHtml(view.cpuText) + '</span>',
      '        <span class="task-legend-item ram" data-role="legend-ram">RAM ' + escapeHtml(view.ramText) + '</span>',
      '        <span class="task-legend-item disk" data-role="legend-disk">DISK ' + escapeHtml(view.diskText) + '</span>',
      '        <span class="task-legend-item network" data-role="legend-network">RED ' + escapeHtml(view.networkText) + '</span>',
      '      </div>',
      '    </div>',
      '    <canvas class="task-canvas" data-role="task-chart" data-id="' + escapeAttr(view.id) + '" width="350" height="122"></canvas>',
      '  </section>',
      '  <footer class="device-footer">',
      '    <div class="device-footer-info">',
      '      <p class="small" data-role="age-text">' + escapeHtml(view.ageText) + '</p>',
      view.errorText ? '      <p class="small small-error" data-role="error-text" title="' + escapeAttr(view.lastError) + '">' + escapeHtml(view.errorText) + '</p>' : '',
      '    </div>',
      '    <div class="device-actions" data-role="device-actions">',
      renderCardActions(view),
      '    </div>',
      '  </footer>',
      '</article>'
    ].join('');
  }

  function metricRow(label, metricView, metricType) {
    var type = metricType === 'ram' || metricType === 'disk' || metricType === 'network'
      ? metricType
      : 'cpu';
    var detailText = metricView && typeof metricView.detailText === 'string' ? metricView.detailText : '';
    var hiddenAttr = detailText ? '' : ' hidden';

    return [
      '<div class="metric-row">',
      '  <div class="metric-label"><span>' + escapeHtml(label) + '</span><strong class="metric-value ' + escapeAttr(metricView.status) + '" data-role="metric-value" data-metric="' + escapeAttr(type) + '">' + escapeHtml(metricView.text) + '</strong></div>',
      '  <div class="metric-track"><div class="metric-fill metric-' + escapeAttr(type) + '" data-role="metric-fill" data-metric="' + escapeAttr(type) + '" style="width:' + metricView.width.toFixed(1) + '%"></div></div>',
      '  <p class="metric-extra" data-role="metric-extra" data-metric="' + escapeAttr(type) + '"' + hiddenAttr + '>' + escapeHtml(detailText) + '</p>',
      '</div>'
    ].join('');
  }

  function normalizeBinary(value) {
    if (value === true) {
      return 1;
    }
    if (value === false) {
      return 0;
    }
    if (typeof value === 'number' && isFinite(value)) {
      return value > 0 ? 1 : 0;
    }
    if (typeof value === 'string') {
      var text = value.trim().toLowerCase();
      if (text === '1' || text === 'true' || text === 'yes' || text === 'si' || text === 'on' || text === 'running' || text === 'up' || text === 'ok') {
        return 1;
      }
      if (text === '0' || text === 'false' || text === 'no' || text === 'off' || text === 'stopped' || text === 'down') {
        return 0;
      }
    }
    return null;
  }

  function normalizeProcessPort(value) {
    var port = parseInt(value, 10);
    if (!isFinite(port) || port < 1 || port > 65535) {
      return 0;
    }
    return port;
  }

  function normalizePortStatusMap(value) {
    var map = {};
    if (!value || typeof value !== 'object') {
      return map;
    }

    Object.keys(value).forEach(function (key) {
      var port = normalizeProcessPort(key);
      var item = value[key];
      if (port === 0 && item && typeof item === 'object') {
        port = normalizeProcessPort(item.port);
      }
      if (port === 0) {
        return;
      }

      var statusRaw = item;
      if (item && typeof item === 'object' && Object.prototype.hasOwnProperty.call(item, 'ok')) {
        statusRaw = item.ok;
      }
      map[String(port)] = normalizeBinary(statusRaw);
    });

    return map;
  }

  function portListFromStatusMap(map) {
    if (!map || typeof map !== 'object') {
      return [];
    }
    var keys = Object.keys(map);
    var ports = [];
    for (var i = 0; i < keys.length; i++) {
      var port = normalizeProcessPort(keys[i]);
      if (port > 0) {
        ports.push(port);
      }
    }
    ports.sort(function (a, b) {
      return a - b;
    });
    return ports;
  }

  function renderProcessChips(device, metrics) {
    var chips = [];
    var mode = normalizeMode(device.mode || '');
    if (mode !== 'pull_ssh') {
      return '';
    }

    var resolvedOs = normalizeSshOs(metrics.ssh_os_used || device.ssh_os || 'auto');

    var watchIis = isTruthy(device.expect_iis);
    var iisUp = normalizeBinary(metrics.proc_iis_up);
    var iisStatusByPort = normalizePortStatusMap(metrics.proc_iis_ports);
    var javaStatusByPort = normalizePortStatusMap(metrics.proc_java_ports);
    var measuredJavaPort = normalizeProcessPort(metrics.proc_java_port);
    if (measuredJavaPort > 0 && !Object.prototype.hasOwnProperty.call(javaStatusByPort, String(measuredJavaPort))) {
      javaStatusByPort[String(measuredJavaPort)] = normalizeBinary(metrics.proc_java_port_ok);
    }

    var customServiceChecks = normalizeServiceChecks(device.service_checks);
    var customServiceStatusByPort = normalizePortStatusMap(metrics.proc_service_ports);
    for (var customIdx = 0; customIdx < customServiceChecks.length; customIdx++) {
      var customCheck = customServiceChecks[customIdx] || {};
      var customPort = normalizeProcessPort(customCheck.port);
      if (customPort === 0) {
        continue;
      }
      var customLabelText = String(customCheck.label || '').trim();
      if (!customLabelText) {
        customLabelText = 'Servicio';
      }

      var customKey = String(customPort);
      var customStatus = Object.prototype.hasOwnProperty.call(customServiceStatusByPort, customKey)
        ? customServiceStatusByPort[customKey]
        : null;
      if (customStatus === null && Object.prototype.hasOwnProperty.call(iisStatusByPort, customKey)) {
        customStatus = iisStatusByPort[customKey];
      }
      if (customStatus === null && Object.prototype.hasOwnProperty.call(javaStatusByPort, customKey)) {
        customStatus = javaStatusByPort[customKey];
      }

      var customLabel = customLabelText + ':' + customPort;
      if (customStatus === 1) {
        chips.push('<span class="meta-chip chip-proc-ok">' + escapeHtml(customLabel + ' OK') + '</span>');
      } else if (customStatus === 0) {
        chips.push('<span class="meta-chip chip-proc-bad">' + escapeHtml(customLabel + ' DOWN') + '</span>');
      } else {
        chips.push('<span class="meta-chip chip-proc-unk">' + escapeHtml(customLabel + ' --') + '</span>');
      }
    }

    var configuredIisPorts = normalizePortListFromValue(device.expect_iis_ports);
    var iisPorts = configuredIisPorts.slice();
    if (iisPorts.length === 0 && resolvedOs === 'windows') {
      iisPorts = [80, 443];
    } else if (iisPorts.length === 0 && watchIis) {
      iisPorts = [80, 443];
    }
    iisPorts = mergePortLists(iisPorts, portListFromStatusMap(iisStatusByPort));

    if (iisPorts.length > 0 && resolvedOs !== 'linux') {
      for (var iisIdx = 0; iisIdx < iisPorts.length; iisIdx++) {
        var iisPort = iisPorts[iisIdx];
        var iisStatus = Object.prototype.hasOwnProperty.call(iisStatusByPort, String(iisPort))
          ? iisStatusByPort[String(iisPort)]
          : null;
        var iisLabel = 'IIS:' + iisPort;
        if (iisStatus === 1) {
          chips.push('<span class="meta-chip chip-proc-ok">' + escapeHtml(iisLabel + ' OK') + '</span>');
        } else if (iisStatus === 0) {
          chips.push('<span class="meta-chip chip-proc-bad">' + escapeHtml(iisLabel + ' DOWN') + '</span>');
        } else {
          chips.push('<span class="meta-chip chip-proc-unk">' + escapeHtml(iisLabel + ' --') + '</span>');
        }
      }
    } else {
      var showIis = watchIis || iisUp !== null || resolvedOs === 'windows';
      if (showIis && resolvedOs !== 'linux') {
        if (iisUp === 1) {
          chips.push('<span class="meta-chip chip-proc-ok">IIS OK</span>');
        } else if (iisUp === 0) {
          chips.push('<span class="meta-chip chip-proc-bad">IIS DOWN</span>');
        } else {
          chips.push('<span class="meta-chip chip-proc-unk">IIS --</span>');
        }
      }
    }

    var configuredJavaPorts = normalizePortListFromValue(device.expect_java_ports);
    if (configuredJavaPorts.length === 0) {
      var legacyConfiguredPort = normalizeProcessPort(device.expect_java_port);
      if (legacyConfiguredPort > 0) {
        configuredJavaPorts.push(legacyConfiguredPort);
      }
    }

    var javaPorts = mergePortLists(configuredJavaPorts, portListFromStatusMap(javaStatusByPort));
    if (javaPorts.length === 0 && resolvedOs === 'linux') {
      javaPorts = [8080];
    }

    for (var javaIdx = 0; javaIdx < javaPorts.length; javaIdx++) {
      var javaPort = javaPorts[javaIdx];
      var javaUp = Object.prototype.hasOwnProperty.call(javaStatusByPort, String(javaPort))
        ? javaStatusByPort[String(javaPort)]
        : null;
      var label = 'JAVA:' + javaPort;
      if (javaUp === 1) {
        chips.push('<span class="meta-chip chip-proc-ok">' + escapeHtml(label + ' OK') + '</span>');
      } else if (javaUp === 0) {
        chips.push('<span class="meta-chip chip-proc-bad">' + escapeHtml(label + ' DOWN') + '</span>');
      } else {
        chips.push('<span class="meta-chip chip-proc-unk">' + escapeHtml(label + ' --') + '</span>');
      }
    }

    var maxChips = 8;
    if (chips.length > maxChips) {
      var hiddenCount = chips.length - maxChips;
      chips = chips.slice(0, maxChips);
      chips.push('<span class="meta-chip chip-proc-unk">+' + hiddenCount + ' mas</span>');
    }

    return chips.join('');
  }

  function formatMetricShort(value) {
    if (typeof value !== 'number' || !isFinite(value)) {
      return '--';
    }
    return value.toFixed(1) + '%';
  }

  function statusLabelFor(status) {
    if (status === 'green') {
      return 'Verde';
    }
    if (status === 'yellow') {
      return 'Amarillo';
    }
    return 'Rojo';
  }

  function formatAge(ageSeconds) {
    var secs = parseInt(ageSeconds, 10);
    if (isNaN(secs) || secs < 1) {
      return '0 s';
    }
    if (secs < 60) {
      return secs + ' s';
    }

    var mins = Math.floor(secs / 60);
    if (mins < 60) {
      return mins + ' min';
    }

    var hours = Math.floor(mins / 60);
    return hours + ' h';
  }

  function compactText(value, maxLen) {
    var text = String(value || '').replace(/\s+/g, ' ').trim();
    if (!text) {
      return '';
    }

    if (text.length <= maxLen) {
      return text;
    }

    return text.slice(0, Math.max(0, maxLen - 3)) + '...';
  }

  function formatDateTime(isoDate) {
    try {
      var d = new Date(isoDate);
      if (isNaN(d.getTime())) {
        return isoDate;
      }
      return d.toLocaleString('es-GT');
    } catch (error) {
      return isoDate;
    }
  }

  function showFlash(message, type, timeoutMs) {
    if (!refs.flash) {
      return;
    }

    refs.flash.textContent = message || '';
    refs.flash.classList.remove('success', 'error');
    refs.flash.classList.add(type === 'error' ? 'error' : 'success');
    refs.flash.hidden = false;

    if (showFlash._timer) {
      clearTimeout(showFlash._timer);
    }

    var ttl = typeof timeoutMs === 'number' ? timeoutMs : 3000;
    showFlash._timer = setTimeout(function () {
      refs.flash.hidden = true;
    }, ttl);
  }

  function updateBulkDeleteState() {
    var count = Object.keys(state.selected).length;
    refs.bulkDeleteBtn.disabled = count === 0;
    if (count === 0) {
      refs.bulkDeleteBtn.textContent = 'Eliminar seleccionados';
    } else {
      refs.bulkDeleteBtn.textContent = 'Eliminar seleccionados (' + count + ')';
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '');
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(String(value));
    }
    return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function getJSON(url) {
    return fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      }
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (payload) {
        if (!response.ok) {
          var err = payload && payload.error ? payload.error : 'HTTP ' + response.status;
          throw new Error(err);
        }
        return payload;
      });
    });
  }

  function postJSON(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(data || {})
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (payload) {
        if (!response.ok) {
          var err = payload && payload.error ? payload.error : 'HTTP ' + response.status;
          throw new Error(err);
        }
        return payload;
      });
    });
  }
})();
