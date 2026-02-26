<?php
require __DIR__ . '/bootstrap.php';

mgk_init_storage();

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method !== 'POST') {
    mgk_error('Metodo no permitido.', 405, array());
}

$payload = mgk_read_json_body();
$devices = mgk_get_devices();
if (count($devices) === 0) {
    mgk_error('No hay equipos registrados para recibir metricas.', 404, array());
}

$providedToken = mgk_clean_text(
    mgk_get_nested($payload, array('token', 'api_token'), mgk_get_header_value('X-Monitor-Token')),
    120
);
$providedDeviceId = mgk_clean_text(mgk_get_nested($payload, array('device_id', 'id'), ''), 80);

$targetDevice = null;

for ($i = 0; $i < count($devices); $i++) {
    $candidate = $devices[$i];
    $candidateId = isset($candidate['id']) ? (string) $candidate['id'] : '';
    $candidateToken = isset($candidate['token']) ? (string) $candidate['token'] : '';

    if ($providedDeviceId !== '' && $candidateId === $providedDeviceId) {
        $targetDevice = $candidate;
        break;
    }

    if ($providedDeviceId === '' && $providedToken !== '' && $candidateToken === $providedToken) {
        $targetDevice = $candidate;
        break;
    }
}

if ($targetDevice === null) {
    mgk_error('Equipo no encontrado para el token/device_id recibido.', 404, array());
}

$expectedToken = isset($targetDevice['token']) ? (string) $targetDevice['token'] : '';
if ($expectedToken !== '' && $providedToken !== '' && $providedToken !== $expectedToken) {
    mgk_error('Token invalido para este equipo.', 401, array());
}
if ($expectedToken !== '' && $providedToken === '') {
    mgk_error('Debes enviar token para autenticar la ingesta.', 401, array());
}

$metricsPayload = isset($payload['metrics']) && is_array($payload['metrics'])
    ? $payload['metrics']
    : $payload;
$normalizedMetrics = mgk_normalize_metrics($metricsPayload);

if (count($normalizedMetrics) === 0) {
    mgk_error('No se recibieron metricas validas (cpu, ram o disk).', 422, array());
}

$metricsStore = mgk_get_metrics_store();
$deviceId = isset($targetDevice['id']) ? $targetDevice['id'] : '';
$currentPacket = isset($metricsStore[$deviceId]) && is_array($metricsStore[$deviceId])
    ? $metricsStore[$deviceId]
    : array();
$previousPacket = $currentPacket;
$hadPreviousSample = isset($previousPacket['updated_at']) && trim((string) $previousPacket['updated_at']) !== '';
$settings = mgk_get_settings();
$globalPollSeconds = isset($settings['device_poll_interval_seconds'])
    ? mgk_normalize_poll_interval($settings['device_poll_interval_seconds'], 8)
    : 8;
$effectivePollSeconds = mgk_resolve_device_poll_interval($targetDevice, $globalPollSeconds);
$offlineAfterSeconds = mgk_offline_after_seconds_for_poll($effectivePollSeconds, 45);
$previousStatus = $hadPreviousSample
    ? mgk_build_device_status($targetDevice, $previousPacket, $offlineAfterSeconds)
    : array();

foreach ($normalizedMetrics as $metricKey => $metricValue) {
    $currentPacket[$metricKey] = $metricValue;
}

$currentPacket['updated_at'] = mgk_now_iso();
$currentPacket['source'] = 'push';
$currentPacket['last_error'] = '';
if (isset($currentPacket['retry_after_ts'])) {
    unset($currentPacket['retry_after_ts']);
}
if (isset($currentPacket['consecutive_failures'])) {
    $currentPacket['consecutive_failures'] = 0;
}

if (isset($payload['host']) && trim((string) $payload['host']) !== '') {
    $currentPacket['reported_host'] = mgk_clean_text($payload['host'], 120);
}

$nextStatus = mgk_build_device_status($targetDevice, $currentPacket, $offlineAfterSeconds);
mgk_record_hourly_history($targetDevice, $currentPacket, $nextStatus);
if ($hadPreviousSample) {
    mgk_record_status_events($targetDevice, $previousStatus, $nextStatus, 'push');
}

$metricsStore[$deviceId] = $currentPacket;
if (!mgk_save_metrics_store($metricsStore)) {
    mgk_error('No se pudo guardar la metrica recibida.', 500, array());
}

mgk_ok(array(
    'device_id' => $deviceId,
    'updated_at' => $currentPacket['updated_at'],
    'accepted_metrics' => array_keys($normalizedMetrics)
));
