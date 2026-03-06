<?php
require __DIR__ . '/api/bootstrap.php';
mgk_init_storage();
$settings = mgk_get_settings();
$defaultPollMs = 500;
$defaultTheme = isset($settings['theme_default']) ? mgk_normalize_theme($settings['theme_default']) : 'dark';
$appVersion = mgk_get_app_version();
$cssVersion = @filemtime(__DIR__ . '/assets/css/app.css');
$jsVersion = @filemtime(__DIR__ . '/assets/js/app.js');
if (!$cssVersion) {
    $cssVersion = time();
}
if (!$jsVersion) {
    $jsVersion = time();
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>MonitorGEKO <?php echo htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8'); ?> | Monitoreo en tiempo real</title>
  <link rel="stylesheet" href="assets/css/app.css?v=<?php echo urlencode((string) $cssVersion); ?>"/>
</head>
<body>
  <div class="bg-glow bg-glow-a"></div>
  <div class="bg-glow bg-glow-b"></div>

  <main class="app-shell">
    <header class="hero panel">
      <div>
        <p class="eyebrow">NOC Dashboard</p>
        <h1>MonitorGEKO</h1>
        <p class="hint">Version <?php echo htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="hero-copy">Monitorea CPU, RAM, Disco y Red en tiempo real. Administra equipos de forma individual y masiva con umbrales por color.</p>
      </div>
      <div class="hero-actions">
        <div class="quick-controls">
          <label class="compact-field">
            Tema
            <select class="input input-compact" id="themeMode">
              <option value="dark">Oscuro</option>
              <option value="glass">Glass Vista</option>
              <option value="emerald">Emerald Neon</option>
              <option value="sunset">Sunset Amber</option>
              <option value="graphite">Graphite Mono</option>
              <option value="arctic">Arctic Cyan</option>
            </select>
          </label>
          <label class="compact-field">
            Modo cards (Amplio/Compacto)
            <select class="input input-compact" id="cardDensityMode">
              <option value="amplio">Amplio</option>
              <option value="compacto">Compacto</option>
            </select>
          </label>
          <label class="compact-field">
            Refresco visual dashboard (ms)
            <div class="inline-row">
              <input class="input input-compact" id="uiPollMs" type="number" min="100" max="30000" step="50" value="<?php echo htmlspecialchars((string) $defaultPollMs, ENT_QUOTES, 'UTF-8'); ?>"/>
              <button type="button" class="btn btn-ghost btn-compact" id="saveUiPollBtn">Aplicar</button>
            </div>
          </label>
        </div>
        <p class="compact-note" id="pollInfo">Captura y refresco visual sincronizados.</p>
        <div class="hero-status-row">
          <div class="live-pill" id="livePill">
            <span class="dot"></span>
            <span>En vivo</span>
          </div>
          <button type="button" class="btn btn-secondary" id="refreshBtn">Actualizar ahora</button>
          <p class="sync-text" id="lastSync">Sin sincronizacion aun</p>
        </div>
      </div>
    </header>

    <section class="layout">
      <aside class="panel controls-panel">
        <h2>Gestion de equipos</h2>
        <div class="panel-size-row">
          <label class="panel-size-label" for="controlsPanelWidth">Ancho panel</label>
          <div class="panel-size-controls">
            <input id="controlsPanelWidth" type="range" min="300" max="620" step="10" value="390"/>
            <button type="button" class="btn btn-ghost btn-compact" id="controlsPanelWidthReset">Reset</button>
          </div>
          <p class="hint" id="controlsPanelWidthHint">390 px</p>
        </div>
        <div id="flash" class="flash" hidden></div>

        <form id="deviceForm" class="device-form" autocomplete="off">
          <input type="hidden" id="deviceId"/>

          <label>
            Nombre del equipo
            <input class="input" id="deviceName" type="text" placeholder="Srv-App-01" required/>
          </label>

          <label>
            Host / IP
            <input class="input" id="deviceHost" type="text" placeholder="10.10.20.15" required/>
          </label>

          <label>
            Grupo
            <input class="input" id="deviceGroup" type="text" placeholder="Produccion"/>
          </label>

          <label>
            Intervalo individual (segundos)
            <input class="input" id="devicePollSeconds" type="number" min="2" max="600" step="1" placeholder="Vacio = usar refresco dashboard"/>
          </label>

          <label>
            Modo de monitoreo
            <select class="input" id="deviceMode">
              <option value="push">push (recomendado, agente envia)</option>
              <option value="pull_http">pull_http (servidor consulta endpoint)</option>
              <option value="pull_ssh_auto">pull_ssh auto (linux/windows)</option>
              <option value="pull_ssh_windows">pull_ssh windows</option>
              <option value="pull_ssh_linux">pull_ssh linux</option>
            </select>
          </label>

          <div id="pushFields" class="mode-block">
            <label>
              Token del equipo
              <div class="token-row">
                <input class="input" id="deviceToken" type="text" placeholder="Token automatico"/>
                <button type="button" class="btn btn-ghost" id="regenTokenBtn">Nuevo</button>
              </div>
            </label>
            <p class="hint">El agente del equipo usa este token para enviar metricas al endpoint de ingesta.</p>
          </div>

          <div id="pullFields" class="mode-block" hidden>
            <div id="pullUrlRow">
              <label>
                URL endpoint remoto (JSON)
                <input class="input" id="devicePullUrl" type="url" placeholder="http://10.0.0.23:9100/metrics"/>
              </label>
            </div>
            <label>
              Usuario
              <input class="input" id="deviceUsername" type="text" placeholder="monitor-user"/>
            </label>
            <label>
              Password
              <input class="input" id="devicePassword" type="password" placeholder="Solo si aplica"/>
            </label>
            <label class="inline-check">
              <input id="clearPassword" type="checkbox"/>
              Limpiar password guardada
            </label>

            <div id="sshFields" hidden>
              <label>
                Puerto SSH
                <input class="input" id="deviceSshPort" type="number" min="1" max="65535" value="22"/>
              </label>
              <label>
                Sistema remoto SSH
                <select class="input" id="deviceSshOs">
                  <option value="auto">auto (intenta linux y luego windows)</option>
                  <option value="linux">linux</option>
                  <option value="windows">windows</option>
                </select>
              </label>
              <label>
                Ruta llave privada en servidor (opcional)
                <input class="input" id="deviceSshKeyPath" type="text" placeholder="C:\\keys\\monitor_id_rsa"/>
              </label>
              <div class="process-watch-grid">
                <label class="inline-check">
                  <input id="expectIis" type="checkbox"/>
                  Vigilar IIS (Windows)
                </label>
                <label>
                  Puertos IIS esperados (Windows)
                  <input class="input" id="expectIisPorts" type="text" placeholder="80,443"/>
                </label>
                <label>
                  Puertos Java esperados (Linux)
                  <input class="input" id="expectJavaPorts" type="text" placeholder="8080,8443"/>
                </label>
                <label>
                  Servicios personalizados (descripcion:puerto, una linea por servicio)
                  <textarea id="serviceChecks" rows="3" placeholder="IIS HTTP:80&#10;IIS HTTPS:443&#10;API Java:8080"></textarea>
                </label>
              </div>
              <p class="hint">Si no usas llave, se intentara autenticacion por password con ssh2/plink.</p>
              <p class="hint">Puedes indicar puertos separados por coma. Ejemplo: IIS 80,443 y Java 8080.</p>
              <p class="hint">En servicios personalizados usa formato <code>Descripcion:Puerto</code> y separa multiples con nueva linea, <code>;</code> o <code>|</code>.</p>
            </div>
          </div>

          <fieldset>
            <legend>Umbrales de alerta (%)</legend>
            <div class="threshold-grid">
              <label>CPU Warn<input class="input" id="cpuWarning" type="number" min="1" max="100" value="70"/></label>
              <label>CPU Crit<input class="input" id="cpuCritical" type="number" min="1" max="100" value="90"/></label>
              <label>RAM Warn<input class="input" id="ramWarning" type="number" min="1" max="100" value="75"/></label>
              <label>RAM Crit<input class="input" id="ramCritical" type="number" min="1" max="100" value="90"/></label>
              <label>DISK Warn<input class="input" id="diskWarning" type="number" min="1" max="100" value="80"/></label>
              <label>DISK Crit<input class="input" id="diskCritical" type="number" min="1" max="100" value="95"/></label>
            </div>
          </fieldset>

          <label class="inline-check">
            <input id="deviceEnabled" type="checkbox" checked/>
            Equipo habilitado
          </label>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="saveDeviceBtn">Guardar equipo</button>
            <button type="button" class="btn btn-ghost" id="cancelEditBtn">Limpiar</button>
          </div>
        </form>
        <div class="controls-resizer" id="controlsResizer" title="Arrastra para ajustar el ancho del panel"></div>

      </aside>

      <section class="panel monitor-panel">
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="input" id="searchInput" type="text" placeholder="Buscar por nombre, host o grupo"/>
          </div>
          <div class="toolbar-right">
            <button type="button" class="btn btn-danger" id="bulkDeleteBtn" disabled>Eliminar seleccionados</button>
          </div>
        </div>

        <div class="summary-grid" id="summaryGrid">
          <article class="summary-card neutral">
            <p>Total</p>
            <strong id="sumTotal">0</strong>
          </article>
          <article class="summary-card green">
            <p>Verde</p>
            <strong id="sumGreen">0</strong>
          </article>
          <article class="summary-card yellow">
            <p>Amarillo</p>
            <strong id="sumYellow">0</strong>
          </article>
          <article class="summary-card red">
            <p>Rojo</p>
            <strong id="sumRed">0</strong>
          </article>
        </div>

        <div id="deviceGrid" class="device-grid"></div>
      </section>
    </section>

    <section class="panel bulk-panel">
      <div class="bulk-panel-inner">
        <h3>Carga masiva</h3>
        <p class="hint">Formato por linea: <code>nombre,host,grupo,modo,pull_url,usuario,password,cpu_warn,cpu_crit,ram_warn,ram_crit,disk_warn,disk_crit,token,id,ssh_port,ssh_os,ssh_key_path,poll_seconds,expect_iis,expect_java_port,expect_iis_ports,expect_java_ports,service_checks</code></p>
        <p class="hint">`service_checks` ejemplo: <code>IIS HTTP:80|IIS HTTPS:443|App Java:8080</code></p>
        <textarea id="bulkInput" rows="6" placeholder="Srv-01,10.0.0.1,Produccion,push,,,,70,90,75,90,80,95"></textarea>
        <button type="button" class="btn btn-secondary" id="bulkAddBtn">Agregar/actualizar masivo</button>
      </div>
    </section>
  </main>

  <section class="detail-modal" id="deviceDetailModal" hidden>
    <div class="detail-backdrop" data-action="close-detail"></div>
    <article class="detail-dialog panel" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
      <header class="detail-head">
        <div>
          <p class="eyebrow">Detalle del equipo</p>
          <h3 id="detailTitle">Sin equipo seleccionado</h3>
          <p class="hint" id="detailSubtitle">Selecciona una card y usa el boton Detalle.</p>
        </div>
        <button type="button" class="btn btn-ghost" id="detailCloseBtn">Cerrar</button>
      </header>

      <div class="detail-actions">
        <button type="button" class="btn btn-secondary" id="detailRefreshBtn">Recargar detalle</button>
        <a class="btn btn-ghost" id="detailDownloadEventsBtn" href="#" download>Descargar eventos CSV</a>
        <button type="button" class="btn btn-ghost" id="detailLoadLinuxLogsBtn">Listar logs Linux</button>
        <button type="button" class="btn btn-ghost" id="detailLoadWindowsLogsBtn">Listar logs IIS</button>
      </div>

      <section class="detail-section">
        <h4>Historico por hora (RAM y caidas)</h4>
        <canvas class="detail-history-canvas" id="detailHistoryCanvas" width="920" height="240"></canvas>
        <p class="hint" id="detailHistoryHint">Cargando historico...</p>
      </section>

      <section class="detail-section">
        <h4>Eventos de servicios y estado</h4>
        <div class="event-list" id="detailEventsList"></div>
      </section>

      <section class="detail-section">
        <h4>Logs Linux remotos</h4>
        <p class="hint" id="detailLinuxLogsDir">Directorio: /opt/spring/bancamovil/logs</p>
        <div class="remote-log-list" id="detailLinuxLogsList"></div>
      </section>

      <section class="detail-section">
        <h4>Logs IIS remotos (Windows)</h4>
        <p class="hint" id="detailWindowsLogsDir">Directorio: C:\inetpub\logs\LogFiles</p>
        <div class="remote-log-list" id="detailWindowsLogsList"></div>
      </section>

      <section class="detail-section">
        <h4>Visor de logs y busqueda</h4>
        <p class="hint" id="detailLogViewerFile">Selecciona un archivo con el boton Ver.</p>
        <div class="log-view-controls">
          <select class="input input-compact log-view-lines" id="detailLogLineLimit" title="Cantidad de lineas a cargar">
            <option value="1000" selected>1000 lineas</option>
            <option value="2000">2000 lineas</option>
            <option value="5000">5000 lineas</option>
            <option value="0">Completo</option>
          </select>
          <input class="input input-compact log-view-input" id="detailLogSearchInput" type="text" placeholder="Buscar texto dentro del log"/>
          <button type="button" class="btn btn-ghost btn-mini" id="detailLogSearchBtn">Buscar</button>
          <button type="button" class="btn btn-secondary btn-mini" id="detailLogReloadBtn">Recargar</button>
        </div>
        <p class="hint" id="detailLogViewerHint">Sin contenido cargado.</p>
        <pre class="log-viewer" id="detailLogViewer"></pre>
      </section>
    </article>
  </section>

  <script>
    window.MONITOR_APP_CONFIG = {
      endpoints: {
        state: 'api/state.php',
        devices: 'api/devices.php',
        ingest: 'api/ingest.php',
        settings: 'api/settings.php',
        ops: 'api/ops.php'
      },
      pollIntervalMs: <?php echo json_encode($defaultPollMs); ?>,
      themeDefault: <?php echo json_encode($defaultTheme); ?>,
      appVersion: <?php echo json_encode($appVersion); ?>
    };
  </script>
  <script src="assets/js/app.js?v=<?php echo urlencode((string) $jsVersion); ?>" defer></script>
</body>
</html>
