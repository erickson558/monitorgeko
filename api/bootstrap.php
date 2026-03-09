<?php
/**
 * Funciones compartidas para MonitorApp.
 */

date_default_timezone_set('America/Guatemala');

define('MONITORAPP_ROOT', dirname(__DIR__));
define('MONITORAPP_DATA', MONITORAPP_ROOT . DIRECTORY_SEPARATOR . 'data');
define('MONITORAPP_DEVICES_FILE', MONITORAPP_DATA . DIRECTORY_SEPARATOR . 'devices.json');
define('MONITORAPP_METRICS_FILE', MONITORAPP_DATA . DIRECTORY_SEPARATOR . 'metrics.json');
define('MONITORAPP_EVENTS_FILE', MONITORAPP_DATA . DIRECTORY_SEPARATOR . 'events.json');
define('MONITORAPP_HISTORY_FILE', MONITORAPP_DATA . DIRECTORY_SEPARATOR . 'history.json');
define('MONITORAPP_SETTINGS_FILE', MONITORAPP_DATA . DIRECTORY_SEPARATOR . 'settings.json');
define('MONITORAPP_SECRET_FILE', MONITORAPP_DATA . DIRECTORY_SEPARATOR . '.secret');
define('MONITORAPP_VERSION_FILE', MONITORAPP_ROOT . DIRECTORY_SEPARATOR . 'VERSION');

function mgk_get_app_version() {
    static $cachedVersion = null;

    if ($cachedVersion !== null) {
        return $cachedVersion;
    }

    $defaultVersion = 'v0.1.0';
    if (!file_exists(MONITORAPP_VERSION_FILE)) {
        $cachedVersion = $defaultVersion;
        return $cachedVersion;
    }

    $content = @file_get_contents(MONITORAPP_VERSION_FILE);
    if ($content === false) {
        $cachedVersion = $defaultVersion;
        return $cachedVersion;
    }

    $version = trim((string) $content);
    if (!preg_match('/^v[0-9]+\.[0-9]+\.[0-9]+$/', $version)) {
        $cachedVersion = $defaultVersion;
        return $cachedVersion;
    }

    $cachedVersion = $version;
    return $cachedVersion;
}

function mgk_init_storage() {
    if (!is_dir(MONITORAPP_DATA)) {
        mkdir(MONITORAPP_DATA, 0775, true);
    }

    if (!file_exists(MONITORAPP_DEVICES_FILE)) {
        file_put_contents(MONITORAPP_DEVICES_FILE, "[]\n", LOCK_EX);
    }

    if (!file_exists(MONITORAPP_METRICS_FILE)) {
        file_put_contents(MONITORAPP_METRICS_FILE, "{}\n", LOCK_EX);
    }

    if (!file_exists(MONITORAPP_EVENTS_FILE)) {
        mgk_write_json_file(MONITORAPP_EVENTS_FILE, array('by_device' => array()));
    }

    if (!file_exists(MONITORAPP_HISTORY_FILE)) {
        mgk_write_json_file(MONITORAPP_HISTORY_FILE, array('by_device' => array()));
    }

    if (!file_exists(MONITORAPP_SETTINGS_FILE)) {
        mgk_write_json_file(MONITORAPP_SETTINGS_FILE, mgk_default_settings());
    }
}

function mgk_response($payload, $statusCode) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function mgk_error($message, $statusCode, $extra) {
    $payload = array('ok' => false, 'error' => $message);
    if (is_array($extra)) {
        foreach ($extra as $k => $v) {
            $payload[$k] = $v;
        }
    }
    mgk_response($payload, $statusCode);
}

function mgk_ok($payload) {
    if (!is_array($payload)) {
        $payload = array();
    }
    $payload['ok'] = true;
    mgk_response($payload, 200);
}

function mgk_read_json_file($path, $defaultValue) {
    if (!file_exists($path)) {
        return $defaultValue;
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return $defaultValue;
    }

    $parsed = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $defaultValue;
    }

    return $parsed;
}

function mgk_write_json_file($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $result = file_put_contents($path, $json . "\n", LOCK_EX);
    return $result !== false;
}

function mgk_read_json_body() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return array();
    }

    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        mgk_error('JSON invalido en el cuerpo de la peticion.', 400, array());
    }

    return $parsed;
}

function mgk_get_devices() {
    $devices = mgk_read_json_file(MONITORAPP_DEVICES_FILE, array());
    if (!is_array($devices)) {
        return array();
    }

    $safe = array();
    foreach ($devices as $device) {
        if (is_array($device) && !empty($device['id'])) {
            $safe[] = $device;
        }
    }

    return $safe;
}

function mgk_save_devices($devices) {
    return mgk_write_json_file(MONITORAPP_DEVICES_FILE, $devices);
}

function mgk_get_metrics_store() {
    $metrics = mgk_read_json_file(MONITORAPP_METRICS_FILE, array());
    if (!is_array($metrics)) {
        return array();
    }
    return $metrics;
}

function mgk_save_metrics_store($metrics) {
    return mgk_write_json_file(MONITORAPP_METRICS_FILE, $metrics);
}

function mgk_get_events_store() {
    $store = mgk_read_json_file(MONITORAPP_EVENTS_FILE, array('by_device' => array()));
    if (!is_array($store)) {
        $store = array('by_device' => array());
    }
    if (!isset($store['by_device']) || !is_array($store['by_device'])) {
        $store['by_device'] = array();
    }
    return $store;
}

function mgk_save_events_store($store) {
    if (!is_array($store)) {
        $store = array();
    }
    if (!isset($store['by_device']) || !is_array($store['by_device'])) {
        $store['by_device'] = array();
    }
    return mgk_write_json_file(MONITORAPP_EVENTS_FILE, $store);
}

function mgk_get_history_store() {
    $store = mgk_read_json_file(MONITORAPP_HISTORY_FILE, array('by_device' => array()));
    if (!is_array($store)) {
        $store = array('by_device' => array());
    }
    if (!isset($store['by_device']) || !is_array($store['by_device'])) {
        $store['by_device'] = array();
    }
    return $store;
}

function mgk_save_history_store($store) {
    if (!is_array($store)) {
        $store = array();
    }
    if (!isset($store['by_device']) || !is_array($store['by_device'])) {
        $store['by_device'] = array();
    }
    return mgk_write_json_file(MONITORAPP_HISTORY_FILE, $store);
}

function mgk_normalize_status_text($value, $fallback) {
    $status = strtolower(trim((string) $value));
    if ($status === 'green' || $status === 'yellow' || $status === 'red') {
        return $status;
    }
    return $fallback;
}

function mgk_service_label_from_key($serviceKey) {
    $serviceKey = trim((string) $serviceKey);
    if ($serviceKey === '') {
        return 'Servicio';
    }

    if ($serviceKey === 'iis') {
        return 'IIS';
    }

    if (preg_match('/^iis_port_([0-9]{1,5})$/', $serviceKey, $matchIis)) {
        $port = (int) $matchIis[1];
        if ($port === 80) {
            return 'IIS HTTP:80';
        }
        if ($port === 443) {
            return 'IIS HTTPS:443';
        }
        return 'IIS:' . $port;
    }

    if (preg_match('/^java_([0-9]{1,5})$/', $serviceKey, $matchJava)) {
        return 'JAVA:' . (int) $matchJava[1];
    }

    if (preg_match('/^svc_([0-9]{1,5})/', $serviceKey, $matchSvc)) {
        return 'Servicio:' . (int) $matchSvc[1];
    }

    return str_replace('_', ' ', $serviceKey);
}

function mgk_append_device_event($device, $type, $level, $message, $meta) {
    if (!is_array($device)) {
        return false;
    }

    $deviceId = isset($device['id']) ? trim((string) $device['id']) : '';
    if ($deviceId === '') {
        return false;
    }

    $level = mgk_normalize_status_text($level, 'yellow');
    $type = mgk_clean_text($type, 64);
    if ($type === '') {
        $type = 'event';
    }

    $event = array(
        'id' => mgk_random_token(12),
        'ts' => mgk_now_iso(),
        'device_id' => $deviceId,
        'device_name' => isset($device['name']) ? mgk_clean_text($device['name'], 120) : '',
        'type' => $type,
        'level' => $level,
        'message' => mgk_clean_text($message, 280),
        'meta' => is_array($meta) ? $meta : array()
    );

    $store = mgk_get_events_store();
    if (!isset($store['by_device'][$deviceId]) || !is_array($store['by_device'][$deviceId])) {
        $store['by_device'][$deviceId] = array();
    }

    $lastEvent = null;
    if (count($store['by_device'][$deviceId]) > 0) {
        $lastEvent = $store['by_device'][$deviceId][count($store['by_device'][$deviceId]) - 1];
    }
    if (is_array($lastEvent)) {
        $sameType = isset($lastEvent['type']) && (string) $lastEvent['type'] === $event['type'];
        $sameLevel = isset($lastEvent['level']) && (string) $lastEvent['level'] === $event['level'];
        $sameMessage = isset($lastEvent['message']) && (string) $lastEvent['message'] === $event['message'];
        $lastTs = isset($lastEvent['ts']) ? strtotime((string) $lastEvent['ts']) : false;
        $nowTs = strtotime($event['ts']);
        if ($sameType && $sameLevel && $sameMessage && $lastTs !== false && $nowTs !== false) {
            if (($nowTs - $lastTs) <= 20) {
                return true;
            }
        }
    }

    $store['by_device'][$deviceId][] = $event;
    $maxEvents = 1000;
    if (count($store['by_device'][$deviceId]) > $maxEvents) {
        $store['by_device'][$deviceId] = array_slice($store['by_device'][$deviceId], -$maxEvents);
    }

    return mgk_save_events_store($store);
}

function mgk_get_device_events($deviceId, $limit) {
    $deviceId = trim((string) $deviceId);
    if ($deviceId === '') {
        return array();
    }

    $limit = (int) $limit;
    if ($limit < 1) {
        $limit = 100;
    }
    if ($limit > 2000) {
        $limit = 2000;
    }

    $store = mgk_get_events_store();
    $events = isset($store['by_device'][$deviceId]) && is_array($store['by_device'][$deviceId])
        ? $store['by_device'][$deviceId]
        : array();
    if (count($events) === 0) {
        return array();
    }

    $events = array_slice($events, -$limit);
    $events = array_reverse($events);
    return $events;
}

function mgk_events_to_csv($events) {
    if (!is_array($events)) {
        $events = array();
    }

    $fp = fopen('php://temp', 'w+');
    if ($fp === false) {
        return '';
    }

    fputcsv($fp, array('timestamp', 'device_id', 'device_name', 'type', 'level', 'message', 'meta'));
    for ($i = 0; $i < count($events); $i++) {
        $event = is_array($events[$i]) ? $events[$i] : array();
        $meta = isset($event['meta']) && is_array($event['meta'])
            ? json_encode($event['meta'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';
        fputcsv($fp, array(
            isset($event['ts']) ? $event['ts'] : '',
            isset($event['device_id']) ? $event['device_id'] : '',
            isset($event['device_name']) ? $event['device_name'] : '',
            isset($event['type']) ? $event['type'] : '',
            isset($event['level']) ? $event['level'] : '',
            isset($event['message']) ? $event['message'] : '',
            $meta
        ));
    }

    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return is_string($csv) ? $csv : '';
}

function mgk_record_status_events($device, $previousStatus, $nextStatus, $source) {
    if (!is_array($device) || !is_array($previousStatus) || !is_array($nextStatus)) {
        return;
    }

    $sourceText = mgk_clean_text($source, 40);

    $prevOverall = mgk_normalize_status_text(mgk_get_nested($previousStatus, array('overall'), ''), '');
    $nextOverall = mgk_normalize_status_text(mgk_get_nested($nextStatus, array('overall'), ''), '');
    if ($prevOverall !== '' && $nextOverall !== '' && $prevOverall !== $nextOverall) {
        mgk_append_device_event(
            $device,
            'overall_status',
            $nextOverall,
            'Estado general cambio de ' . strtoupper($prevOverall) . ' a ' . strtoupper($nextOverall) . '.',
            array('source' => $sourceText)
        );
    }

    $prevOffline = array_key_exists('offline', $previousStatus) ? mgk_to_bool($previousStatus['offline'], false) : null;
    $nextOffline = array_key_exists('offline', $nextStatus) ? mgk_to_bool($nextStatus['offline'], false) : null;
    if ($prevOffline !== null && $nextOffline !== null && $prevOffline !== $nextOffline) {
        if ($nextOffline) {
            mgk_append_device_event(
                $device,
                'offline',
                'red',
                'Equipo sin reporte reciente (offline).',
                array('source' => $sourceText)
            );
        } else {
            mgk_append_device_event(
                $device,
                'online',
                'green',
                'Equipo recupero comunicacion (online).',
                array('source' => $sourceText)
            );
        }
    }

    $prevServices = isset($previousStatus['services']) && is_array($previousStatus['services'])
        ? $previousStatus['services']
        : array();
    $nextServices = isset($nextStatus['services']) && is_array($nextStatus['services'])
        ? $nextStatus['services']
        : array();
    if (count($prevServices) === 0 || count($nextServices) === 0) {
        return;
    }

    $allKeys = array();
    foreach ($prevServices as $serviceKey => $unusedValue) {
        $allKeys[(string) $serviceKey] = true;
    }
    foreach ($nextServices as $serviceKey => $unusedValue) {
        $allKeys[(string) $serviceKey] = true;
    }

    foreach ($allKeys as $serviceKey => $unusedFlag) {
        $prevServiceStatus = array_key_exists($serviceKey, $prevServices)
            ? mgk_normalize_status_text($prevServices[$serviceKey], '')
            : '';
        $nextServiceStatus = array_key_exists($serviceKey, $nextServices)
            ? mgk_normalize_status_text($nextServices[$serviceKey], '')
            : '';

        if ($prevServiceStatus === '' || $nextServiceStatus === '' || $prevServiceStatus === $nextServiceStatus) {
            continue;
        }

        $label = mgk_service_label_from_key($serviceKey);
        if ($nextServiceStatus === 'red') {
            mgk_append_device_event(
                $device,
                'service_down',
                'red',
                $label . ' cayo.',
                array('service' => $serviceKey, 'source' => $sourceText)
            );
            continue;
        }

        if ($prevServiceStatus === 'red' && $nextServiceStatus === 'green') {
            mgk_append_device_event(
                $device,
                'service_up',
                'green',
                $label . ' se recupero.',
                array('service' => $serviceKey, 'source' => $sourceText)
            );
            continue;
        }

        mgk_append_device_event(
            $device,
            'service_state',
            $nextServiceStatus,
            $label . ' cambio de ' . strtoupper($prevServiceStatus) . ' a ' . strtoupper($nextServiceStatus) . '.',
            array('service' => $serviceKey, 'source' => $sourceText)
        );
    }
}

function mgk_history_bucket_key($isoDatetime) {
    $ts = strtotime((string) $isoDatetime);
    if ($ts === false) {
        $ts = time();
    }
    return gmdate('Y-m-d\TH:00:00\Z', $ts);
}

function mgk_bucket_avg($bucket, $metricName) {
    $sumKey = $metricName . '_sum';
    $countKey = $metricName . '_count';
    $sum = isset($bucket[$sumKey]) ? (float) $bucket[$sumKey] : 0.0;
    $count = isset($bucket[$countKey]) ? (int) $bucket[$countKey] : 0;
    if ($count < 1) {
        return null;
    }
    return round($sum / $count, 3);
}

function mgk_record_hourly_history($device, $metricPacket, $status) {
    if (!is_array($device) || !is_array($metricPacket)) {
        return false;
    }

    $deviceId = isset($device['id']) ? trim((string) $device['id']) : '';
    if ($deviceId === '') {
        return false;
    }

    $hourKey = mgk_history_bucket_key(isset($metricPacket['updated_at']) ? $metricPacket['updated_at'] : '');
    $store = mgk_get_history_store();
    if (!isset($store['by_device'][$deviceId]) || !is_array($store['by_device'][$deviceId])) {
        $store['by_device'][$deviceId] = array();
    }

    if (!isset($store['by_device'][$deviceId][$hourKey]) || !is_array($store['by_device'][$deviceId][$hourKey])) {
        $store['by_device'][$deviceId][$hourKey] = array(
            'samples' => 0,
            'offline_samples' => 0,
            'service_issue_samples' => 0,
            'green_samples' => 0,
            'yellow_samples' => 0,
            'red_samples' => 0,
            'cpu_sum' => 0.0,
            'cpu_count' => 0,
            'ram_sum' => 0.0,
            'ram_count' => 0,
            'disk_sum' => 0.0,
            'disk_count' => 0,
            'network_sum' => 0.0,
            'network_count' => 0
        );
    }

    $bucket = $store['by_device'][$deviceId][$hourKey];
    $bucket['samples'] = isset($bucket['samples']) ? ((int) $bucket['samples'] + 1) : 1;

    foreach (array('cpu', 'ram', 'disk', 'network') as $metricName) {
        $value = isset($metricPacket[$metricName]) ? $metricPacket[$metricName] : null;
        if (!is_numeric($value)) {
            continue;
        }
        $sumKey = $metricName . '_sum';
        $countKey = $metricName . '_count';
        $bucket[$sumKey] = isset($bucket[$sumKey]) ? ((float) $bucket[$sumKey] + (float) $value) : (float) $value;
        $bucket[$countKey] = isset($bucket[$countKey]) ? ((int) $bucket[$countKey] + 1) : 1;
    }

    if (is_array($status)) {
        $overall = mgk_normalize_status_text(mgk_get_nested($status, array('overall'), ''), '');
        if ($overall === 'green' || $overall === 'yellow' || $overall === 'red') {
            $countKey = $overall . '_samples';
            $bucket[$countKey] = isset($bucket[$countKey]) ? ((int) $bucket[$countKey] + 1) : 1;
        }

        if (array_key_exists('offline', $status) && mgk_to_bool($status['offline'], false)) {
            $bucket['offline_samples'] = isset($bucket['offline_samples']) ? ((int) $bucket['offline_samples'] + 1) : 1;
        }

        $hasServiceIssue = false;
        $services = isset($status['services']) && is_array($status['services']) ? $status['services'] : array();
        foreach ($services as $serviceState) {
            if (mgk_normalize_status_text($serviceState, '') === 'red') {
                $hasServiceIssue = true;
                break;
            }
        }
        if ($hasServiceIssue) {
            $bucket['service_issue_samples'] = isset($bucket['service_issue_samples'])
                ? ((int) $bucket['service_issue_samples'] + 1)
                : 1;
        }
    }

    $store['by_device'][$deviceId][$hourKey] = $bucket;

    $maxBuckets = 24 * 60;
    if (count($store['by_device'][$deviceId]) > $maxBuckets) {
        $keys = array_keys($store['by_device'][$deviceId]);
        sort($keys, SORT_STRING);
        $removeCount = count($keys) - $maxBuckets;
        for ($idx = 0; $idx < $removeCount; $idx++) {
            unset($store['by_device'][$deviceId][$keys[$idx]]);
        }
    }

    return mgk_save_history_store($store);
}

function mgk_get_device_hourly_history($deviceId, $hours) {
    $deviceId = trim((string) $deviceId);
    if ($deviceId === '') {
        return array();
    }

    $hours = (int) $hours;
    if ($hours < 24) {
        $hours = 24;
    }
    if ($hours > (24 * 60)) {
        $hours = 24 * 60;
    }

    $store = mgk_get_history_store();
    $rows = isset($store['by_device'][$deviceId]) && is_array($store['by_device'][$deviceId])
        ? $store['by_device'][$deviceId]
        : array();
    if (count($rows) === 0) {
        return array();
    }

    $fromTs = time() - ($hours * 3600);
    $keys = array_keys($rows);
    sort($keys, SORT_STRING);

    $points = array();
    for ($i = 0; $i < count($keys); $i++) {
        $hourKey = $keys[$i];
        $hourTs = strtotime($hourKey);
        if ($hourTs === false || $hourTs < $fromTs) {
            continue;
        }

        $bucket = is_array($rows[$hourKey]) ? $rows[$hourKey] : array();
        $points[] = array(
            'hour' => $hourKey,
            'samples' => isset($bucket['samples']) ? (int) $bucket['samples'] : 0,
            'cpu_avg' => mgk_bucket_avg($bucket, 'cpu'),
            'ram_avg' => mgk_bucket_avg($bucket, 'ram'),
            'disk_avg' => mgk_bucket_avg($bucket, 'disk'),
            'network_avg' => mgk_bucket_avg($bucket, 'network'),
            'offline_samples' => isset($bucket['offline_samples']) ? (int) $bucket['offline_samples'] : 0,
            'service_issue_samples' => isset($bucket['service_issue_samples']) ? (int) $bucket['service_issue_samples'] : 0,
            'green_samples' => isset($bucket['green_samples']) ? (int) $bucket['green_samples'] : 0,
            'yellow_samples' => isset($bucket['yellow_samples']) ? (int) $bucket['yellow_samples'] : 0,
            'red_samples' => isset($bucket['red_samples']) ? (int) $bucket['red_samples'] : 0
        );
    }

    return $points;
}

function mgk_sanitize_remote_log_filename($fileName) {
    $fileName = trim((string) $fileName);
    if ($fileName === '') {
        return '';
    }
    if (strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
        return '';
    }
    if (strpos($fileName, '..') !== false) {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
        return '';
    }
    return $fileName;
}

function mgk_sanitize_linux_log_directory($directory) {
    $directory = trim((string) $directory);
    if ($directory === '') {
        return '/opt/spring/bancamovil/logs';
    }

    $directory = str_replace('\\', '/', $directory);
    $directory = preg_replace('#/+#', '/', $directory);
    if (!is_string($directory) || $directory === '') {
        return '/opt/spring/bancamovil/logs';
    }
    if ($directory[0] !== '/') {
        return '/opt/spring/bancamovil/logs';
    }
    if (strpos($directory, '..') !== false || strpos($directory, "\0") !== false) {
        return '/opt/spring/bancamovil/logs';
    }
    if (!preg_match('/^\/[A-Za-z0-9._\/-]+$/', $directory)) {
        return '/opt/spring/bancamovil/logs';
    }

    $trimmed = rtrim($directory, '/');
    if ($trimmed === '') {
        return '/';
    }
    return $trimmed;
}

function mgk_is_linux_ssh_device($device, $metricPacket) {
    if (!is_array($device)) {
        return false;
    }

    $mode = isset($device['mode']) ? mgk_normalize_mode($device['mode']) : 'push';
    if ($mode !== 'pull_ssh') {
        return false;
    }

    $deviceOs = isset($device['ssh_os']) ? mgk_normalize_ssh_os($device['ssh_os']) : 'auto';
    if ($deviceOs === 'linux') {
        return true;
    }
    if ($deviceOs === 'windows') {
        return false;
    }

    if (is_array($metricPacket) && isset($metricPacket['ssh_os_used'])) {
        $lastUsed = mgk_normalize_ssh_os($metricPacket['ssh_os_used']);
        if ($lastUsed === 'linux') {
            return true;
        }
        if ($lastUsed === 'windows') {
            return false;
        }
    }

    return true;
}

function mgk_is_windows_ssh_device($device, $metricPacket) {
    if (!is_array($device)) {
        return false;
    }

    $mode = isset($device['mode']) ? mgk_normalize_mode($device['mode']) : 'push';
    if ($mode !== 'pull_ssh') {
        return false;
    }

    $deviceOs = isset($device['ssh_os']) ? mgk_normalize_ssh_os($device['ssh_os']) : 'auto';
    if ($deviceOs === 'windows') {
        return true;
    }
    if ($deviceOs === 'linux') {
        return false;
    }

    if (is_array($metricPacket) && isset($metricPacket['ssh_os_used'])) {
        $lastUsed = mgk_normalize_ssh_os($metricPacket['ssh_os_used']);
        if ($lastUsed === 'windows') {
            return true;
        }
        if ($lastUsed === 'linux') {
            return false;
        }
    }

    return false;
}

function mgk_sanitize_remote_log_relative_path($relativePath) {
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $relativePath);
    if ($normalized === '') {
        return '';
    }
    if (strpos($normalized, '..') !== false) {
        return '';
    }
    if (strpos($normalized, ':') !== false) {
        return '';
    }
    if (strpos($normalized, "\0") !== false) {
        return '';
    }
    if ($normalized[0] === '/') {
        return '';
    }

    $parts = explode('/', $normalized);
    $safeParts = array();
    for ($idx = 0; $idx < count($parts); $idx++) {
        $part = trim((string) $parts[$idx]);
        if ($part === '' || $part === '.' || $part === '..') {
            return '';
        }
        if (preg_match('/[<>:"|?*]/', $part)) {
            return '';
        }
        $safeParts[] = $part;
    }

    if (count($safeParts) < 1) {
        return '';
    }

    return implode('/', $safeParts);
}

function mgk_windows_iis_default_log_directory() {
    return 'C:\\inetpub\\logs\\LogFiles';
}

function mgk_ps_single_quote($value) {
    return "'" . str_replace("'", "''", (string) $value) . "'";
}

function mgk_build_windows_powershell_remote_command($script) {
    $script = "\$ProgressPreference='SilentlyContinue';\$InformationPreference='SilentlyContinue';" . (string) $script;
    $encoded = mgk_utf16le_base64($script);
    if ($encoded !== '') {
        return 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ' . $encoded;
    }

    $escapedScript = str_replace('"', '\"', $script);
    return 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "' . $escapedScript . '"';
}

function mgk_strip_windows_powershell_noise($text) {
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $clean = preg_replace('/#<\s*CLIXML/i', '', $text);
    if (!is_string($clean)) {
        $clean = $text;
    }
    $clean = preg_replace('/<Objs[^>]*>.*?<\/Objs>/is', '', $clean);
    if (!is_string($clean)) {
        $clean = $text;
    }

    return trim($clean);
}

function mgk_list_windows_iis_log_files($device, $directory) {
    $directory = trim((string) $directory);
    if ($directory === '') {
        $directory = mgk_windows_iis_default_log_directory();
    }
    $directory = str_replace('/', '\\', $directory);

    $script = "\$dir = " . mgk_ps_single_quote($directory) . ";"
        . "if (-not (Test-Path -LiteralPath \$dir)) { Write-Output '__MGK_NODIR__'; exit 0 };"
        . "try { \$files = Get-ChildItem -LiteralPath \$dir -File -Recurse -ErrorAction Stop | Sort-Object LastWriteTime -Descending | Select-Object -First 500 } catch { Write-Output '__MGK_NOPERM_DIR__'; exit 0 };"
        . "foreach (\$f in \$files) { \$rel = \$f.FullName.Substring(\$dir.Length).TrimStart([char]92, [char]47); \$mod = \$f.LastWriteTime.ToString('yyyy-MM-dd HH:mm:ss'); Write-Output (\$rel + '|' + [string]\$f.Length + '|' + \$mod + '|1') }";

    $execResult = mgk_ssh_exec_command($device, mgk_build_windows_powershell_remote_command($script));
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible consultar logs IIS remotos.'
        );
    }

    $output = isset($execResult['output']) ? (string) $execResult['output'] : '';
    $lines = preg_split('/\r\n|\n|\r/', $output);
    $files = array();
    $missingDir = false;
    $noPermDir = false;
    $seenByName = array();

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim((string) $lines[$i]);
        if ($line === '') {
            continue;
        }
        if ($line === '__MGK_NODIR__') {
            $missingDir = true;
            continue;
        }
        if ($line === '__MGK_NOPERM_DIR__') {
            $noPermDir = true;
            continue;
        }

        $parts = explode('|', $line);
        if (count($parts) < 3) {
            continue;
        }

        $name = mgk_sanitize_remote_log_relative_path($parts[0]);
        if ($name === '') {
            continue;
        }
        if (isset($seenByName[$name])) {
            continue;
        }
        $seenByName[$name] = true;

        $size = is_numeric($parts[1]) ? max(0, (int) $parts[1]) : 0;
        $modified = trim((string) $parts[2]);
        if (strlen($modified) > 19) {
            $modified = substr($modified, 0, 19);
        }
        $readable = 1;
        if (isset($parts[3])) {
            $readable = mgk_boolish_to_int($parts[3], 1) === 1 ? 1 : 0;
        }

        $files[] = array(
            'name' => $name,
            'size' => $size,
            'modified' => $modified,
            'readable' => $readable
        );
    }

    if ($noPermDir) {
        return array(
            'ok' => false,
            'error' => 'Sin permisos para listar el directorio remoto IIS: ' . $directory
        );
    }

    if (count($files) > 1) {
        usort($files, function ($a, $b) {
            $aMod = isset($a['modified']) ? (string) $a['modified'] : '';
            $bMod = isset($b['modified']) ? (string) $b['modified'] : '';
            if ($aMod === $bMod) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }
            return strcmp($bMod, $aMod);
        });
    }

    return array(
        'ok' => true,
        'files' => $files,
        'missing_dir' => $missingDir,
        'directory' => $directory
    );
}

function mgk_read_windows_log_file_base64($device, $directory, $relativePath) {
    $directory = trim((string) $directory);
    if ($directory === '') {
        $directory = mgk_windows_iis_default_log_directory();
    }
    $directory = str_replace('/', '\\', $directory);

    $safeRelative = mgk_sanitize_remote_log_relative_path($relativePath);
    if ($safeRelative === '') {
        return array('ok' => false, 'error' => 'Ruta de archivo invalida.');
    }

    $script = "\$dir = " . mgk_ps_single_quote($directory) . ";"
        . "\$rel = " . mgk_ps_single_quote($safeRelative) . ";"
        . "\$rel = \$rel -replace '/', [string][char]92;"
        . "if (\$rel.Contains('..')) { Write-Output '__MGK_BADFILE__'; exit 0 };"
        . "\$path = Join-Path -Path \$dir -ChildPath \$rel;"
        . "if (-not (Test-Path -LiteralPath \$path -PathType Leaf)) { Write-Output '__MGK_NOFILE__'; exit 0 };"
        . "try { \$fs = New-Object System.IO.FileStream(\$path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite); \$ms = New-Object System.IO.MemoryStream; \$fs.CopyTo(\$ms); \$bytes = \$ms.ToArray(); \$ms.Close(); \$fs.Close(); } catch { Write-Output '__MGK_NOREAD__'; exit 0 };"
        . "if (\$bytes -eq \$null) { Write-Output '__MGK_EMPTY__'; exit 0 };"
        . "[Console]::Out.Write('__MGK_B64_BEGIN__' + [Environment]::NewLine);"
        . "[Console]::Out.Write([Convert]::ToBase64String(\$bytes));"
        . "[Console]::Out.Write([Environment]::NewLine + '__MGK_B64_END__');";

    $execResult = mgk_ssh_exec_command($device, mgk_build_windows_powershell_remote_command($script));
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible descargar archivo IIS remoto.'
        );
    }

    $raw = mgk_strip_windows_powershell_noise(isset($execResult['output']) ? $execResult['output'] : '');
    if (strpos($raw, '__MGK_BADFILE__') !== false) {
        return array('ok' => false, 'error' => 'Ruta de archivo remoto invalida.');
    }
    if (strpos($raw, '__MGK_NOFILE__') !== false) {
        return array('ok' => false, 'error' => 'Archivo remoto no encontrado.');
    }
    if (strpos($raw, '__MGK_NOREAD__') !== false) {
        return array('ok' => false, 'error' => 'No hay permisos de lectura sobre el archivo remoto.');
    }
    if (strpos($raw, '__MGK_EMPTY__') !== false) {
        return array('ok' => false, 'error' => 'El archivo remoto se recibio vacio.');
    }
    if ($raw === '') {
        return array('ok' => false, 'error' => 'No se recibio contenido del archivo remoto.');
    }

    $beginMarker = '__MGK_B64_BEGIN__';
    $endMarker = '__MGK_B64_END__';
    $payload = $raw;
    $beginPos = strpos($raw, $beginMarker);
    if ($beginPos !== false) {
        $startPos = $beginPos + strlen($beginMarker);
        $endPos = strpos($raw, $endMarker, $startPos);
        if ($endPos === false) {
            $payload = substr($raw, $startPos);
        } else {
            $payload = substr($raw, $startPos, $endPos - $startPos);
        }
    }

    $payload = preg_replace('/[^A-Za-z0-9+\/=]/', '', (string) $payload);
    if (!is_string($payload) || $payload === '') {
        return array('ok' => false, 'error' => 'No se pudo procesar el archivo remoto.');
    }

    return array(
        'ok' => true,
        'file' => $safeRelative,
        'directory' => $directory,
        'base64' => $payload
    );
}

function mgk_view_linux_log_text_base64($device, $directory, $fileName, $lineLimit, $query) {
    $directory = mgk_sanitize_linux_log_directory($directory);
    $safeName = mgk_sanitize_remote_log_filename($fileName);
    if ($safeName === '') {
        return array('ok' => false, 'error' => 'Nombre de archivo invalido.');
    }

    $lineLimit = (int) $lineLimit;
    $fullContent = $lineLimit <= 0;
    if ($fullContent) {
        $lineLimit = 0;
    } else {
        if ($lineLimit < 20) {
            $lineLimit = 20;
        }
        if ($lineLimit > 5000) {
            $lineLimit = 5000;
        }
    }

    $query = trim((string) $query);
    $maxPreviewBytes = 2 * 1024 * 1024;
    $readPreviewBytes = $maxPreviewBytes + 1;
    $remoteCommand = 'dir=' . $directory
        . '; file=' . $safeName
        . '; path="$dir/$file"'
        . '; lines=' . $lineLimit
        . '; full=' . ($fullContent ? '1' : '0')
        . '; max_bytes=' . $maxPreviewBytes
        . '; read_bytes=' . $readPreviewBytes
        . '; truncated=0'
        . '; if [ ! -f "$path" ]; then echo __MGK_NOFILE__; exit 0; fi'
        . '; if [ ! -r "$path" ]; then echo __MGK_NOREAD__; exit 0; fi'
        . '; is_gz=0'
        . '; if [ "${file%.gz}" != "$file" ] || [ "${file%.GZ}" != "$file" ]; then is_gz=1; fi'
        . '; text=""'
        . '; if [ "$is_gz" = "1" ]; then'
        . '  if command -v gzip >/dev/null 2>&1; then if [ "$full" = "1" ]; then text=$(gzip -dc -- "$path" 2>/dev/null | head -c "$read_bytes"); else text=$(gzip -dc -- "$path" 2>/dev/null | tail -n "$lines" | head -c "$read_bytes"); fi'
        . '  elif command -v zcat >/dev/null 2>&1; then if [ "$full" = "1" ]; then text=$(zcat -- "$path" 2>/dev/null | head -c "$read_bytes"); else text=$(zcat -- "$path" 2>/dev/null | tail -n "$lines" | head -c "$read_bytes"); fi'
        . '  else echo __MGK_NOGZIP__; exit 0; fi'
        . '; else'
        . '  if [ "$full" = "1" ]; then text=$(cat "$path" 2>/dev/null | head -c "$read_bytes"); else text=$(tail -n "$lines" "$path" 2>/dev/null | head -c "$read_bytes"); fi'
        . '; fi'
        . '; text_len=$(printf "%s" "$text" | wc -c | tr -d "[:space:]")'
        . '; if [ x"$text_len" = x ]; then text_len=0; fi'
        . '; if [ "$text_len" -gt "$max_bytes" ]; then truncated=1; text=$(printf "%s" "$text" | head -c "$max_bytes"); fi'
        . '; if [ "$truncated" = "1" ]; then echo __MGK_TRUNCATED__; fi'
        . '; if command -v base64 >/dev/null 2>&1; then printf "%s" "$text" | base64 | tr -d "\r\n"; elif command -v openssl >/dev/null 2>&1; then printf "%s" "$text" | openssl base64 -A; else echo __MGK_NOBASE64__; exit 0; fi';

    $execResult = mgk_ssh_exec_command($device, $remoteCommand);
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible leer log remoto.'
        );
    }

    $output = isset($execResult['output']) ? (string) $execResult['output'] : '';
    $lines = preg_split('/\r\n|\n|\r/', $output);
    $base64Raw = '';
    $truncated = false;

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim((string) $lines[$i]);
        if ($line === '') {
            continue;
        }
        if ($line === '__MGK_NOFILE__') {
            return array('ok' => false, 'error' => 'Archivo remoto no encontrado.');
        }
        if ($line === '__MGK_NOREAD__') {
            return array('ok' => false, 'error' => 'No hay permisos de lectura sobre el archivo remoto.');
        }
        if ($line === '__MGK_NOBASE64__') {
            return array('ok' => false, 'error' => 'El servidor Linux no tiene utilidades para codificar base64.');
        }
        if ($line === '__MGK_NOGZIP__') {
            return array('ok' => false, 'error' => 'El servidor Linux no tiene utilidades para descomprimir archivos .gz.');
        }
        if ($line === '__MGK_TRUNCATED__') {
            $truncated = true;
            continue;
        }
        $base64Raw .= $line;
    }

    $base64Raw = preg_replace('/\s+/', '', (string) $base64Raw);
    if (!is_string($base64Raw) || $base64Raw === '') {
        $base64Raw = '';
    }
    $decodedText = base64_decode($base64Raw, true);
    if ($decodedText === false) {
        return array('ok' => false, 'error' => 'No se pudo decodificar contenido remoto Linux.');
    }

    $matches = -1;
    if ($query !== '') {
        $rows = preg_split('/\r\n|\n|\r/', (string) $decodedText);
        $filtered = array();
        $matches = 0;
        for ($rowIdx = 0; $rowIdx < count($rows); $rowIdx++) {
            $row = (string) $rows[$rowIdx];
            if (stripos($row, $query) === false) {
                continue;
            }
            $matches++;
            $filtered[] = (string) ($rowIdx + 1) . ': ' . $row;
        }
        $decodedText = implode("\n", $filtered);
    }
    $base64Raw = base64_encode((string) $decodedText);

    return array(
        'ok' => true,
        'file' => $safeName,
        'directory' => $directory,
        'base64' => $base64Raw,
        'matches' => $matches,
        'lines' => $lineLimit,
        'searched' => $query !== '',
        'truncated' => $truncated,
        'preview_limit_bytes' => $maxPreviewBytes
    );
}

function mgk_view_windows_log_text_base64($device, $directory, $relativePath, $lineLimit, $query) {
    $directory = trim((string) $directory);
    if ($directory === '') {
        $directory = mgk_windows_iis_default_log_directory();
    }
    $directory = str_replace('/', '\\', $directory);

    $safeRelative = mgk_sanitize_remote_log_relative_path($relativePath);
    if ($safeRelative === '') {
        return array('ok' => false, 'error' => 'Ruta de archivo invalida.');
    }

    $lineLimit = (int) $lineLimit;
    $fullContent = $lineLimit <= 0;
    if ($fullContent) {
        $lineLimit = 0;
    } else {
        if ($lineLimit < 20) {
            $lineLimit = 20;
        }
        if ($lineLimit > 5000) {
            $lineLimit = 5000;
        }
    }
    $query = trim((string) $query);

    $script = "\$dir = " . mgk_ps_single_quote($directory) . ";"
        . "\$rel = " . mgk_ps_single_quote($safeRelative) . ";"
        . "\$rel = \$rel -replace '/', [string][char]92;"
        . "if (\$rel.Contains('..')) { Write-Output '__MGK_BADFILE__'; exit 0 };"
        . "\$path = Join-Path -Path \$dir -ChildPath \$rel;"
        . "if (-not (Test-Path -LiteralPath \$path -PathType Leaf)) { Write-Output '__MGK_NOFILE__'; exit 0 };"
        . "if (-not (Get-Item -LiteralPath \$path -ErrorAction SilentlyContinue)) { Write-Output '__MGK_NOREAD__'; exit 0 };"
        . "\$lineLimit = " . $lineLimit . ";"
        . "\$fullContent = \$lineLimit -le 0;"
        . "if (-not \$fullContent -and \$lineLimit -lt 20) { \$lineLimit = 20 };"
        . "\$query = " . mgk_ps_single_quote($query) . ";"
        . "\$matches = -1;"
        . "\$rows = @();"
        . "if (\$query -ne '') {"
        . "  \$hits = Select-String -LiteralPath \$path -Pattern \$query -SimpleMatch -ErrorAction SilentlyContinue;"
        . "  \$matches = @(\$hits).Count;"
        . "  if (\$matches -gt 0) { if (\$fullContent) { \$rows = \$hits | ForEach-Object { ([string]\$_.LineNumber + ': ' + \$_.Line) } } else { \$rows = \$hits | Select-Object -Last \$lineLimit | ForEach-Object { ([string]\$_.LineNumber + ': ' + \$_.Line) } } }"
        . "} else {"
        . "  try { if (\$fullContent) { \$rows = Get-Content -LiteralPath \$path -ErrorAction Stop } else { \$rows = Get-Content -LiteralPath \$path -Tail \$lineLimit -ErrorAction Stop } } catch { \$rows = @() }"
        . "};"
        . "\$rows = @(\$rows);"
        . "\$text = [string]::Join([Environment]::NewLine, \$rows);"
        . "\$bytes = [System.Text.Encoding]::UTF8.GetBytes(\$text);"
        . "\$b64 = [Convert]::ToBase64String(\$bytes);"
        . "[Console]::Out.Write('__MGK_MATCHES__|' + [string]\$matches + [Environment]::NewLine + \$b64);";

    $execResult = mgk_ssh_exec_command($device, mgk_build_windows_powershell_remote_command($script));
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible leer log IIS remoto.'
        );
    }

    $output = mgk_strip_windows_powershell_noise(isset($execResult['output']) ? $execResult['output'] : '');
    $lines = preg_split('/\r\n|\n|\r/', $output);
    $matches = -1;
    $base64Raw = '';

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim((string) $lines[$i]);
        if ($line === '') {
            continue;
        }
        if ($line === '__MGK_BADFILE__') {
            return array('ok' => false, 'error' => 'Ruta de archivo remoto invalida.');
        }
        if ($line === '__MGK_NOFILE__') {
            return array('ok' => false, 'error' => 'Archivo remoto no encontrado.');
        }
        if ($line === '__MGK_NOREAD__') {
            return array('ok' => false, 'error' => 'No hay permisos de lectura sobre el archivo remoto.');
        }
        if (strpos($line, '__MGK_MATCHES__|') === 0) {
            $matchValue = substr($line, strlen('__MGK_MATCHES__|'));
            $matches = is_numeric($matchValue) ? (int) $matchValue : -1;
            continue;
        }
        $base64Raw .= $line;
    }

    $base64Raw = preg_replace('/\s+/', '', (string) $base64Raw);
    if (!is_string($base64Raw)) {
        $base64Raw = '';
    }

    return array(
        'ok' => true,
        'file' => $safeRelative,
        'directory' => $directory,
        'base64' => $base64Raw,
        'matches' => $matches,
        'lines' => $lineLimit,
        'searched' => $query !== ''
    );
}

function mgk_list_linux_log_files($device, $directory) {
    $directory = mgk_sanitize_linux_log_directory($directory);

    $remoteCommand = 'dir=' . $directory
        . '; if [ ! -d "$dir" ]; then echo __MGK_NODIR__; exit 0; fi'
        . '; if [ ! -x "$dir" ]; then echo __MGK_NOPERM_DIR__; exit 0; fi'
        . '; if command -v find >/dev/null 2>&1; then find "$dir" -maxdepth 1 -type f 2>/dev/null; else ls -1A "$dir" 2>/dev/null | while IFS= read -r n; do p="$dir/$n"; [ -f "$p" ] && echo "$p"; done; fi'
        . ' | while IFS= read -r f; do [ -f "$f" ] || continue; b=$(basename "$f"); s=$(stat -c %s "$f" 2>/dev/null); m=$(stat -c %y "$f" 2>/dev/null | cut -d"." -f1); if [ x"$m" = x ]; then m=$(date -r "$f" "+%Y-%m-%d %H:%M:%S" 2>/dev/null); fi; r=1; [ -r "$f" ] || r=0; echo "$b|${s:-0}|${m:-}|$r"; done'
        . '; if [ -f "$dir/app.log" ]; then s=$(stat -c %s "$dir/app.log" 2>/dev/null); m=$(stat -c %y "$dir/app.log" 2>/dev/null | cut -d"." -f1); if [ x"$m" = x ]; then m=$(date -r "$dir/app.log" "+%Y-%m-%d %H:%M:%S" 2>/dev/null); fi; r=1; [ -r "$dir/app.log" ] || r=0; echo "__MGK_APPLOG__|app.log|${s:-0}|${m:-}|$r"; fi';
    $execResult = mgk_ssh_exec_command($device, $remoteCommand);
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible consultar logs remotos.'
        );
    }

    $output = isset($execResult['output']) ? (string) $execResult['output'] : '';
    $lines = preg_split('/\r\n|\n|\r/', $output);
    $files = array();
    $missingDir = false;
    $noPermDir = false;
    $seenByName = array();

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim((string) $lines[$i]);
        if ($line === '') {
            continue;
        }
        if ($line === '__MGK_NODIR__') {
            $missingDir = true;
            continue;
        }
        if ($line === '__MGK_NOPERM_DIR__') {
            $noPermDir = true;
            continue;
        }
        if (strpos($line, '__MGK_APPLOG__|') === 0) {
            $line = substr($line, strlen('__MGK_APPLOG__|'));
        }

        $parts = explode('|', $line);
        if (count($parts) < 3) {
            continue;
        }

        $name = mgk_sanitize_remote_log_filename($parts[0]);
        if ($name === '') {
            continue;
        }
        if (isset($seenByName[$name])) {
            continue;
        }
        $seenByName[$name] = true;
        $size = is_numeric($parts[1]) ? max(0, (int) $parts[1]) : 0;
        $modified = trim((string) $parts[2]);
        if (strlen($modified) > 19) {
            $modified = substr($modified, 0, 19);
        }
        $readable = 1;
        if (isset($parts[3])) {
            $readable = mgk_boolish_to_int($parts[3], 1) === 1 ? 1 : 0;
        }

        $files[] = array(
            'name' => $name,
            'size' => $size,
            'modified' => $modified,
            'readable' => $readable
        );
    }

    if ($noPermDir) {
        return array(
            'ok' => false,
            'error' => 'Sin permisos para listar el directorio remoto: ' . $directory
        );
    }

    if (count($files) > 1) {
        usort($files, function ($a, $b) {
            $aMod = isset($a['modified']) ? (string) $a['modified'] : '';
            $bMod = isset($b['modified']) ? (string) $b['modified'] : '';
            if ($aMod === $bMod) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }
            return strcmp($bMod, $aMod);
        });
    }

    return array(
        'ok' => true,
        'files' => $files,
        'missing_dir' => $missingDir,
        'directory' => $directory
    );
}

function mgk_read_linux_log_file_base64($device, $directory, $fileName) {
    $directory = mgk_sanitize_linux_log_directory($directory);
    $safeName = mgk_sanitize_remote_log_filename($fileName);
    if ($safeName === '') {
        return array('ok' => false, 'error' => 'Nombre de archivo invalido.');
    }

    $remoteCommand = 'dir=' . $directory
        . '; file=' . $safeName
        . '; path="$dir/$file"'
        . '; if [ ! -f "$path" ]; then echo __MGK_NOFILE__; exit 0; fi'
        . '; if [ ! -r "$path" ]; then echo __MGK_NOREAD__; exit 0; fi'
        . '; echo __MGK_B64_BEGIN__'
        . '; if command -v base64 >/dev/null 2>&1; then base64 "$path"'
        . '; elif command -v openssl >/dev/null 2>&1; then openssl base64 -A -in "$path"'
        . '; else echo __MGK_NOBASE64__; exit 0; fi'
        . '; echo __MGK_B64_END__';
    $execResult = mgk_ssh_exec_command($device, $remoteCommand);
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible descargar archivo remoto.'
        );
    }

    $output = isset($execResult['output']) ? (string) $execResult['output'] : '';
    $lines = preg_split('/\r\n|\n|\r/', $output);
    $base64Raw = '';
    $insideBase64 = false;

    for ($i = 0; $i < count($lines); $i++) {
        $lineRaw = (string) $lines[$i];
        $line = trim($lineRaw);
        if ($line === '') {
            continue;
        }
        if ($line === '__MGK_NOFILE__') {
            return array('ok' => false, 'error' => 'Archivo remoto no encontrado.');
        }
        if ($line === '__MGK_NOREAD__') {
            return array('ok' => false, 'error' => 'No hay permisos de lectura sobre el archivo remoto.');
        }
        if ($line === '__MGK_NOBASE64__') {
            return array('ok' => false, 'error' => 'El servidor Linux no tiene utilidades para codificar base64.');
        }
        if ($line === '__MGK_B64_BEGIN__') {
            $insideBase64 = true;
            continue;
        }
        if ($line === '__MGK_B64_END__') {
            $insideBase64 = false;
            continue;
        }
        if ($insideBase64) {
            $base64Raw .= trim($lineRaw);
        }
    }

    $base64Raw = preg_replace('/[^A-Za-z0-9+\/=]/', '', (string) $base64Raw);
    if (!is_string($base64Raw) || $base64Raw === '') {
        return array('ok' => false, 'error' => 'No se pudo procesar el archivo remoto.');
    }

    return array(
        'ok' => true,
        'file' => $safeName,
        'directory' => $directory,
        'base64' => $base64Raw
    );
}

function mgk_read_linux_log_tail_base64($device, $directory, $fileName, $tailBytes) {
    $directory = mgk_sanitize_linux_log_directory($directory);
    $safeName = mgk_sanitize_remote_log_filename($fileName);
    if ($safeName === '') {
        return array('ok' => false, 'error' => 'Nombre de archivo invalido.');
    }

    $tailBytes = (int) $tailBytes;
    if ($tailBytes < 1024) {
        $tailBytes = 1024;
    }
    if ($tailBytes > (10 * 1024 * 1024)) {
        $tailBytes = 10 * 1024 * 1024;
    }

    $remoteCommand = 'dir=' . $directory
        . '; file=' . $safeName
        . '; path="$dir/$file"'
        . '; if [ ! -f "$path" ]; then echo __MGK_NOFILE__; exit 0; fi'
        . '; if [ ! -r "$path" ]; then echo __MGK_NOREAD__; exit 0; fi'
        . '; bytes=' . $tailBytes
        . '; if [ "$bytes" -lt 1024 ]; then bytes=1024; fi'
        . '; echo __MGK_B64_BEGIN__'
        . '; if command -v base64 >/dev/null 2>&1; then'
        . ' if command -v tail >/dev/null 2>&1; then tail -c "$bytes" "$path" | base64; else cat "$path" | base64; fi'
        . '; elif command -v openssl >/dev/null 2>&1; then'
        . ' if command -v tail >/dev/null 2>&1; then tail -c "$bytes" "$path" | openssl base64 -A; else cat "$path" | openssl base64 -A; fi'
        . '; else echo __MGK_NOBASE64__; exit 0; fi'
        . '; echo __MGK_B64_END__';

    $execResult = mgk_ssh_exec_command($device, $remoteCommand);
    if (!isset($execResult['ok']) || !$execResult['ok']) {
        return array(
            'ok' => false,
            'error' => isset($execResult['error']) ? (string) $execResult['error'] : 'No fue posible leer tail remoto.'
        );
    }

    $output = isset($execResult['output']) ? (string) $execResult['output'] : '';
    $lines = preg_split('/\r\n|\n|\r/', $output);
    $base64Raw = '';
    $insideBase64 = false;

    for ($i = 0; $i < count($lines); $i++) {
        $lineRaw = (string) $lines[$i];
        $line = trim($lineRaw);
        if ($line === '') {
            continue;
        }
        if ($line === '__MGK_NOFILE__') {
            return array('ok' => false, 'error' => 'Archivo remoto no encontrado.');
        }
        if ($line === '__MGK_NOREAD__') {
            return array('ok' => false, 'error' => 'No hay permisos de lectura sobre el archivo remoto.');
        }
        if ($line === '__MGK_NOBASE64__') {
            return array('ok' => false, 'error' => 'El servidor Linux no tiene utilidades para codificar base64.');
        }
        if ($line === '__MGK_B64_BEGIN__') {
            $insideBase64 = true;
            continue;
        }
        if ($line === '__MGK_B64_END__') {
            $insideBase64 = false;
            continue;
        }
        if ($insideBase64) {
            $base64Raw .= trim($lineRaw);
        }
    }

    $base64Raw = preg_replace('/[^A-Za-z0-9+\/=]/', '', (string) $base64Raw);
    if (!is_string($base64Raw) || $base64Raw === '') {
        return array('ok' => false, 'error' => 'No se pudo procesar el tail remoto.');
    }

    return array(
        'ok' => true,
        'file' => $safeName,
        'directory' => $directory,
        'base64' => $base64Raw,
        'tail_bytes' => $tailBytes
    );
}

function mgk_default_settings() {
    return array(
        'device_poll_interval_seconds' => 8,
        'theme_default' => 'dark'
    );
}

function mgk_normalize_theme($value) {
    $theme = strtolower(trim((string) $value));
    $allowed = array('dark', 'glass', 'emerald', 'sunset', 'graphite', 'arctic');
    if (in_array($theme, $allowed, true)) {
        return $theme;
    }
    return 'dark';
}

function mgk_normalize_poll_interval($value, $fallback) {
    $fallback = (int) $fallback;
    if ($fallback < 2 || $fallback > 600) {
        $fallback = 8;
    }

    if ($value === null) {
        return $fallback;
    }

    if (is_string($value) && trim($value) === '') {
        return 0;
    }

    if (!is_numeric($value)) {
        return $fallback;
    }

    $seconds = (int) $value;
    if ($seconds < 0) {
        $seconds = 0;
    }

    if ($seconds === 0) {
        return 0;
    }

    if ($seconds < 2) {
        $seconds = 2;
    }
    if ($seconds > 600) {
        $seconds = 600;
    }

    return $seconds;
}

function mgk_merge_settings($input, $existing) {
    $base = mgk_default_settings();
    if (is_array($existing)) {
        if (isset($existing['device_poll_interval_seconds'])) {
            $base['device_poll_interval_seconds'] = mgk_normalize_poll_interval(
                $existing['device_poll_interval_seconds'],
                $base['device_poll_interval_seconds']
            );
            if ($base['device_poll_interval_seconds'] < 2) {
                $base['device_poll_interval_seconds'] = 8;
            }
        }
        if (isset($existing['theme_default'])) {
            $base['theme_default'] = mgk_normalize_theme($existing['theme_default']);
        }
    }

    if (!is_array($input)) {
        return $base;
    }

    $pollInput = null;
    $pollProvided = false;
    if (array_key_exists('device_poll_interval_seconds', $input)) {
        $pollInput = $input['device_poll_interval_seconds'];
        $pollProvided = true;
    } elseif (array_key_exists('poll_interval_seconds', $input)) {
        $pollInput = $input['poll_interval_seconds'];
        $pollProvided = true;
    }

    if ($pollProvided) {
        $incoming = mgk_normalize_poll_interval($pollInput, $base['device_poll_interval_seconds']);
        if ($incoming < 2) {
            $incoming = 2;
        }
        $base['device_poll_interval_seconds'] = $incoming;
    }

    if (array_key_exists('theme_default', $input)) {
        $base['theme_default'] = mgk_normalize_theme($input['theme_default']);
    }

    return $base;
}

function mgk_get_settings() {
    $stored = mgk_read_json_file(MONITORAPP_SETTINGS_FILE, array());
    return mgk_merge_settings(array(), $stored);
}

function mgk_save_settings($settings) {
    $normalized = mgk_merge_settings($settings, mgk_get_settings());
    return mgk_write_json_file(MONITORAPP_SETTINGS_FILE, $normalized);
}

function mgk_resolve_device_poll_interval($device, $globalSeconds) {
    $globalSeconds = mgk_normalize_poll_interval($globalSeconds, 8);
    if ($globalSeconds < 2) {
        $globalSeconds = 8;
    }

    if (!is_array($device) || !isset($device['poll_interval_seconds'])) {
        return $globalSeconds;
    }

    $deviceSeconds = mgk_normalize_poll_interval($device['poll_interval_seconds'], $globalSeconds);
    if ($deviceSeconds < 2) {
        return $globalSeconds;
    }

    return $deviceSeconds;
}

function mgk_offline_after_seconds_for_poll($pollSeconds, $minimumSeconds) {
    $pollSeconds = mgk_normalize_poll_interval($pollSeconds, 8);
    if ($pollSeconds < 2) {
        $pollSeconds = 8;
    }

    $minimumSeconds = (int) $minimumSeconds;
    if ($minimumSeconds < 15) {
        $minimumSeconds = 45;
    }

    $computed = $pollSeconds * 3;
    if ($computed < $minimumSeconds) {
        $computed = $minimumSeconds;
    }

    return $computed;
}

function mgk_now_iso() {
    return gmdate('c');
}

function mgk_get_secret_key() {
    $envSecret = getenv('MONITORAPP_SECRET');
    if (is_string($envSecret) && trim($envSecret) !== '') {
        return hash('sha256', $envSecret, true);
    }

    if (!file_exists(MONITORAPP_SECRET_FILE)) {
        $seed = mgk_random_token(64);
        file_put_contents(MONITORAPP_SECRET_FILE, $seed . "\n", LOCK_EX);
    }

    $fileSecret = trim((string) file_get_contents(MONITORAPP_SECRET_FILE));
    if ($fileSecret === '') {
        $fileSecret = mgk_random_token(64);
        file_put_contents(MONITORAPP_SECRET_FILE, $fileSecret . "\n", LOCK_EX);
    }

    return hash('sha256', $fileSecret, true);
}

function mgk_encrypt($plainText) {
    $plainText = (string) $plainText;
    if ($plainText === '') {
        return '';
    }

    if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt')) {
        return 'plain:' . base64_encode($plainText);
    }

    $method = 'AES-256-CBC';
    $ivLen = openssl_cipher_iv_length($method);
    if ($ivLen === false || $ivLen < 8) {
        return '';
    }

    $iv = mgk_random_bytes($ivLen);
    $cipher = openssl_encrypt($plainText, $method, mgk_get_secret_key(), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return '';
    }

    return base64_encode($iv . $cipher);
}

function mgk_decrypt($encodedCipherText) {
    $encodedCipherText = (string) $encodedCipherText;
    if ($encodedCipherText === '') {
        return '';
    }

    if (strpos($encodedCipherText, 'plain:') === 0) {
        $plainRaw = base64_decode(substr($encodedCipherText, 6), true);
        return $plainRaw === false ? '' : $plainRaw;
    }

    if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_decrypt')) {
        return '';
    }

    $decoded = base64_decode($encodedCipherText, true);
    if ($decoded === false) {
        return '';
    }

    $method = 'AES-256-CBC';
    $ivLen = openssl_cipher_iv_length($method);
    if ($ivLen === false || strlen($decoded) <= $ivLen) {
        return '';
    }

    $iv = substr($decoded, 0, $ivLen);
    $cipher = substr($decoded, $ivLen);
    $plain = openssl_decrypt($cipher, $method, mgk_get_secret_key(), OPENSSL_RAW_DATA, $iv);

    if ($plain === false) {
        return '';
    }

    return $plain;
}

function mgk_random_bytes($length) {
    $length = (int) $length;
    if ($length < 1) {
        return '';
    }

    if (function_exists('random_bytes')) {
        return random_bytes($length);
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        return openssl_random_pseudo_bytes($length);
    }

    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }

    return $bytes;
}

function mgk_random_token($length) {
    $length = (int) $length;
    if ($length < 8) {
        $length = 8;
    }

    $hex = bin2hex(mgk_random_bytes((int) ceil($length / 2)));
    return substr($hex, 0, $length);
}

function mgk_clean_text($value, $maxLen) {
    $text = trim((string) $value);
    $text = strip_tags($text);
    if ($maxLen > 0 && strlen($text) > $maxLen) {
        $text = substr($text, 0, $maxLen);
    }
    return $text;
}

function mgk_to_bool($value, $defaultValue) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if ($normalized === '1' || $normalized === 'true' || $normalized === 'si' || $normalized === 'yes' || $normalized === 'on') {
            return true;
        }
        if ($normalized === '0' || $normalized === 'false' || $normalized === 'no' || $normalized === 'off') {
            return false;
        }
    }

    if (is_numeric($value)) {
        return ((float) $value) > 0;
    }

    return (bool) $defaultValue;
}

function mgk_normalize_mode($value) {
    $mode = strtolower(trim((string) $value));
    if ($mode === 'pull' || $mode === 'pull_http') {
        return 'pull_http';
    }
    if ($mode === 'ssh' || $mode === 'pull_ssh') {
        return 'pull_ssh';
    }
    return 'push';
}

function mgk_normalize_ssh_os($value) {
    $os = strtolower(trim((string) $value));
    if ($os === 'win' || $os === 'windows' || $os === 'powershell') {
        return 'windows';
    }
    if ($os === 'linux' || $os === 'unix') {
        return 'linux';
    }
    return 'auto';
}

function mgk_normalize_port($value, $fallback) {
    $fallback = (int) $fallback;
    if ($fallback < 1 || $fallback > 65535) {
        $fallback = 22;
    }

    if (!is_numeric($value)) {
        return $fallback;
    }

    $port = (int) $value;
    if ($port < 1 || $port > 65535) {
        return $fallback;
    }

    return $port;
}

function mgk_default_thresholds() {
    return array(
        'cpu' => array('warning' => 70, 'critical' => 90),
        'ram' => array('warning' => 75, 'critical' => 90),
        'disk' => array('warning' => 80, 'critical' => 95),
        'network' => array('warning' => 70, 'critical' => 90)
    );
}

function mgk_percent_value($value, $fallback) {
    if ($value === null || $value === '') {
        return (float) $fallback;
    }

    if (!is_numeric($value)) {
        return (float) $fallback;
    }

    $num = (float) $value;
    if ($num < 1) {
        $num = 1;
    }
    if ($num > 100) {
        $num = 100;
    }

    return round($num, 2);
}

function mgk_get_nested($array, $keys, $fallback) {
    if (!is_array($array)) {
        return $fallback;
    }

    foreach ($keys as $key) {
        if (array_key_exists($key, $array) && $array[$key] !== '') {
            return $array[$key];
        }
    }

    return $fallback;
}

function mgk_merge_thresholds($payload, $existingThresholds) {
    $base = mgk_default_thresholds();

    if (is_array($existingThresholds)) {
        foreach ($base as $metric => $values) {
            if (isset($existingThresholds[$metric]) && is_array($existingThresholds[$metric])) {
                $base[$metric]['warning'] = mgk_percent_value(
                    mgk_get_nested($existingThresholds[$metric], array('warning', 'warn'), $base[$metric]['warning']),
                    $base[$metric]['warning']
                );
                $base[$metric]['critical'] = mgk_percent_value(
                    mgk_get_nested($existingThresholds[$metric], array('critical', 'crit'), $base[$metric]['critical']),
                    $base[$metric]['critical']
                );
            }
        }
    }

    $thresholdsPayload = array();
    if (isset($payload['thresholds']) && is_array($payload['thresholds'])) {
        $thresholdsPayload = $payload['thresholds'];
    }

    foreach ($base as $metric => $values) {
        $warnFallback = $base[$metric]['warning'];
        $critFallback = $base[$metric]['critical'];

        $warn = mgk_get_nested($payload, array($metric . '_warning', $metric . 'Warning', $metric . '_warn'), $warnFallback);
        $crit = mgk_get_nested($payload, array($metric . '_critical', $metric . 'Critical', $metric . '_crit'), $critFallback);

        if (isset($thresholdsPayload[$metric]) && is_array($thresholdsPayload[$metric])) {
            $warn = mgk_get_nested($thresholdsPayload[$metric], array('warning', 'warn'), $warn);
            $crit = mgk_get_nested($thresholdsPayload[$metric], array('critical', 'crit'), $crit);
        }

        $warnValue = mgk_percent_value($warn, $warnFallback);
        $critValue = mgk_percent_value($crit, $critFallback);

        if ($critValue < $warnValue) {
            $critValue = $warnValue;
        }

        $base[$metric]['warning'] = $warnValue;
        $base[$metric]['critical'] = $critValue;
    }

    return $base;
}

function mgk_find_device_index($devices, $id) {
    if (!is_array($devices)) {
        return -1;
    }

    for ($i = 0; $i < count($devices); $i++) {
        if (isset($devices[$i]['id']) && (string) $devices[$i]['id'] === (string) $id) {
            return $i;
        }
    }

    return -1;
}

function mgk_find_device_index_by_host($devices, $host) {
    if (!is_array($devices)) {
        return -1;
    }

    $host = strtolower(trim((string) $host));
    if ($host === '') {
        return -1;
    }

    for ($i = 0; $i < count($devices); $i++) {
        $candidateHost = strtolower(trim((string) (isset($devices[$i]['host']) ? $devices[$i]['host'] : '')));
        if ($candidateHost !== '' && $candidateHost === $host) {
            return $i;
        }
    }

    return -1;
}

function mgk_sort_devices(&$devices) {
    usort($devices, function ($a, $b) {
        $aName = strtolower(isset($a['name']) ? (string) $a['name'] : '');
        $bName = strtolower(isset($b['name']) ? (string) $b['name'] : '');

        if ($aName === $bName) {
            return strcmp((string) $a['id'], (string) $b['id']);
        }

        return strcmp($aName, $bName);
    });
}

function mgk_prepare_device($input, $existing) {
    if (!is_array($input)) {
        return array(false, 'No se recibio un objeto de equipo.');
    }

    $isNew = !is_array($existing);
    $device = $isNew ? array() : $existing;

    $deviceId = mgk_clean_text(
        mgk_get_nested($input, array('id'), isset($device['id']) ? $device['id'] : ''),
        80
    );
    if ($deviceId === '') {
        $deviceId = mgk_random_token(16);
    }

    $name = mgk_clean_text(mgk_get_nested($input, array('name', 'nombre'), isset($device['name']) ? $device['name'] : ''), 90);
    $host = mgk_clean_text(mgk_get_nested($input, array('host', 'ip', 'hostname'), isset($device['host']) ? $device['host'] : ''), 120);

    if ($name === '') {
        return array(false, 'El nombre del equipo es obligatorio.');
    }

    if ($host === '') {
        return array(false, 'El host/IP del equipo es obligatorio.');
    }

    $mode = mgk_normalize_mode(mgk_get_nested($input, array('mode', 'collector_mode'), isset($device['mode']) ? $device['mode'] : 'push'));
    $pullUrl = mgk_clean_text(mgk_get_nested($input, array('pull_url', 'pullUrl', 'url'), isset($device['pull_url']) ? $device['pull_url'] : ''), 260);
    $sshPort = mgk_normalize_port(
        mgk_get_nested($input, array('ssh_port', 'sshPort'), isset($device['ssh_port']) ? $device['ssh_port'] : 22),
        22
    );
    $sshOs = mgk_normalize_ssh_os(mgk_get_nested($input, array('ssh_os', 'sshOs', 'os'), isset($device['ssh_os']) ? $device['ssh_os'] : 'auto'));
    $sshKeyPath = mgk_clean_text(mgk_get_nested($input, array('ssh_key_path', 'sshKeyPath', 'key_path'), isset($device['ssh_key_path']) ? $device['ssh_key_path'] : ''), 260);
    $sshHostKey = mgk_clean_text(
        mgk_get_nested($input, array('ssh_hostkey', 'sshHostKey', 'hostkey'), isset($device['ssh_hostkey']) ? $device['ssh_hostkey'] : ''),
        220
    );

    if ($mode === 'pull_http' && $pullUrl === '') {
        return array(false, 'Para modo pull_http debes indicar la URL del agente remoto.');
    }

    $token = mgk_clean_text(mgk_get_nested($input, array('token'), isset($device['token']) ? $device['token'] : ''), 120);
    if ($token === '') {
        $token = mgk_random_token(32);
    }

    $username = mgk_clean_text(mgk_get_nested($input, array('username', 'user'), isset($device['username']) ? $device['username'] : ''), 120);

    $passwordInput = '';
    if (array_key_exists('password', $input)) {
        $passwordInput = (string) $input['password'];
    }

    $clearPassword = mgk_to_bool(mgk_get_nested($input, array('clear_password', 'clearPassword'), false), false);
    $passwordEnc = isset($device['password_enc']) ? $device['password_enc'] : '';

    if ($passwordInput !== '') {
        $passwordEnc = mgk_encrypt($passwordInput);
    } elseif ($isNew) {
        $passwordEnc = '';
    }

    if ($clearPassword) {
        $passwordEnc = '';
    }

    if ($mode === 'pull_ssh' && $username === '') {
        return array(false, 'Para modo pull_ssh debes indicar el usuario SSH.');
    }

    $expectIisDefault = isset($device['expect_iis']) ? $device['expect_iis'] : false;
    $expectIis = mgk_to_bool(
        mgk_get_nested($input, array('expect_iis', 'watch_iis', 'monitor_iis'), $expectIisDefault),
        $expectIisDefault
    );

    $expectIisPortsDefault = mgk_get_expected_iis_ports($device);
    $expectIisPortsProvided = false;
    $expectIisPortsRaw = null;
    foreach (array('expect_iis_ports', 'watch_iis_ports', 'monitor_iis_ports', 'iis_ports') as $iisPortsKey) {
        if (array_key_exists($iisPortsKey, $input)) {
            $expectIisPortsProvided = true;
            $expectIisPortsRaw = $input[$iisPortsKey];
            break;
        }
    }
    $expectIisPorts = $expectIisPortsProvided
        ? mgk_parse_port_list($expectIisPortsRaw, array())
        : $expectIisPortsDefault;
    if ($expectIis && count($expectIisPorts) === 0) {
        $expectIisPorts = array(80, 443);
    }

    $expectJavaPortsDefault = mgk_get_expected_java_ports($device);
    $expectJavaPortsProvided = false;
    $expectJavaPortsRaw = null;
    foreach (array('expect_java_ports', 'watch_java_ports', 'monitor_java_ports', 'java_ports', 'expect_java_port', 'java_port') as $javaPortsKey) {
        if (array_key_exists($javaPortsKey, $input)) {
            $expectJavaPortsProvided = true;
            $expectJavaPortsRaw = $input[$javaPortsKey];
            break;
        }
    }
    $expectJavaPorts = $expectJavaPortsProvided
        ? mgk_parse_port_list($expectJavaPortsRaw, array())
        : $expectJavaPortsDefault;
    $expectJavaPort = 0;
    if (count($expectJavaPorts) > 0) {
        $expectJavaPort = (int) $expectJavaPorts[0];
    }

    $serviceChecksDefault = isset($device['service_checks']) ? $device['service_checks'] : array();
    $serviceChecksRaw = mgk_get_nested(
        $input,
        array('service_checks', 'services', 'service_checks_text', 'services_text'),
        $serviceChecksDefault
    );
    $serviceChecks = mgk_normalize_service_checks($serviceChecksRaw, $serviceChecksDefault);

    $enabledDefault = isset($device['enabled']) ? $device['enabled'] : true;
    $enabled = mgk_to_bool(mgk_get_nested($input, array('enabled'), $enabledDefault), $enabledDefault);

    $pollIntervalSeconds = isset($device['poll_interval_seconds'])
        ? mgk_normalize_poll_interval($device['poll_interval_seconds'], 8)
        : 0;
    $pollIntervalProvided = false;
    $pollIntervalRaw = null;
    foreach (array('poll_interval_seconds', 'poll_seconds', 'interval_seconds') as $pollKey) {
        if (array_key_exists($pollKey, $input)) {
            $pollIntervalProvided = true;
            $pollIntervalRaw = $input[$pollKey];
            break;
        }
    }
    if ($pollIntervalProvided) {
        $pollIntervalSeconds = mgk_normalize_poll_interval($pollIntervalRaw, 8);
    }
    if ($pollIntervalSeconds < 0) {
        $pollIntervalSeconds = 0;
    }

    $device['id'] = $deviceId;
    $device['name'] = $name;
    $device['host'] = $host;
    $device['group'] = mgk_clean_text(mgk_get_nested($input, array('group', 'grupo'), isset($device['group']) ? $device['group'] : ''), 90);
    $device['mode'] = $mode;
    $device['token'] = $token;
    $device['pull_url'] = $pullUrl;
    $device['username'] = $username;
    $device['password_enc'] = $passwordEnc;
    $device['ssh_port'] = $sshPort;
    $device['ssh_os'] = $sshOs;
    $device['ssh_key_path'] = $sshKeyPath;
    $device['ssh_hostkey'] = $sshHostKey;
    $device['expect_iis'] = $expectIis;
    $device['expect_iis_ports'] = $expectIisPorts;
    $device['expect_java_ports'] = $expectJavaPorts;
    $device['expect_java_port'] = $expectJavaPort;
    $device['service_checks'] = $serviceChecks;
    $device['enabled'] = $enabled;
    $device['poll_interval_seconds'] = $pollIntervalSeconds;
    $device['thresholds'] = mgk_merge_thresholds($input, isset($device['thresholds']) ? $device['thresholds'] : array());

    $nowIso = mgk_now_iso();
    if (!isset($device['created_at']) || trim((string) $device['created_at']) === '') {
        $device['created_at'] = $nowIso;
    }
    $device['updated_at'] = $nowIso;

    return array(true, $device);
}

function mgk_public_device($device) {
    $copy = $device;
    $copy['password_configured'] = !empty($device['password_enc']);
    unset($copy['password_enc']);
    return $copy;
}

function mgk_age_seconds($isoDatetime) {
    $isoDatetime = trim((string) $isoDatetime);
    if ($isoDatetime === '') {
        return null;
    }

    $ts = strtotime($isoDatetime);
    if ($ts === false) {
        return null;
    }

    return max(0, time() - $ts);
}

function mgk_status_rank($status) {
    $status = strtolower((string) $status);
    if ($status === 'yellow') {
        return 2;
    }
    if ($status === 'red') {
        return 3;
    }
    return 1;
}

function mgk_rank_status($rank) {
    if ((int) $rank >= 3) {
        return 'red';
    }
    if ((int) $rank >= 2) {
        return 'yellow';
    }
    return 'green';
}

function mgk_metric_status($value, $warning, $critical) {
    if (!is_numeric($value)) {
        return 'red';
    }

    $value = (float) $value;
    if ($value >= (float) $critical) {
        return 'red';
    }
    if ($value >= (float) $warning) {
        return 'yellow';
    }

    return 'green';
}

function mgk_number_or_null($value, $decimals) {
    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, (int) $decimals);
}

function mgk_bytes_from_metric_candidates($payload, $bytesKeys, $kbKeys, $mbKeys, $gbKeys) {
    if (!is_array($payload)) {
        return null;
    }

    $candidates = array(
        array('keys' => is_array($bytesKeys) ? $bytesKeys : array(), 'factor' => 1.0),
        array('keys' => is_array($kbKeys) ? $kbKeys : array(), 'factor' => 1024.0),
        array('keys' => is_array($mbKeys) ? $mbKeys : array(), 'factor' => 1024.0 * 1024.0),
        array('keys' => is_array($gbKeys) ? $gbKeys : array(), 'factor' => 1024.0 * 1024.0 * 1024.0)
    );

    for ($i = 0; $i < count($candidates); $i++) {
        $keys = $candidates[$i]['keys'];
        if (count($keys) === 0) {
            continue;
        }
        $raw = mgk_get_nested($payload, $keys, null);
        if (!is_numeric($raw)) {
            continue;
        }

        $num = (float) $raw;
        if ($num < 0) {
            $num = 0;
        }

        $bytes = $num * (float) $candidates[$i]['factor'];
        if (is_finite($bytes) && $bytes >= 0) {
            return $bytes;
        }
    }

    return null;
}

function mgk_boolish_to_int($value, $defaultValue) {
    if ($value === null || $value === '') {
        return $defaultValue;
    }

    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_numeric($value)) {
        return ((float) $value) > 0 ? 1 : 0;
    }

    $text = strtolower(trim((string) $value));
    if (in_array($text, array('1', 'true', 'yes', 'si', 'on', 'running', 'up', 'ok', 'alive'), true)) {
        return 1;
    }
    if (in_array($text, array('0', 'false', 'no', 'off', 'stopped', 'down', 'dead'), true)) {
        return 0;
    }

    return $defaultValue;
}

function mgk_parse_port_list($value, $fallbackList) {
    $tokens = array();
    if (is_array($value)) {
        $tokens = $value;
    } else {
        $text = trim((string) $value);
        if ($text !== '') {
            $tokens = preg_split('/[\s,;|]+/', $text);
        }
    }

    if (count($tokens) === 0) {
        if (is_array($fallbackList)) {
            $tokens = $fallbackList;
        } else {
            $fallbackText = trim((string) $fallbackList);
            if ($fallbackText !== '') {
                $tokens = preg_split('/[\s,;|]+/', $fallbackText);
            }
        }
    }

    $ports = array();
    $seen = array();
    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        if (is_array($token)) {
            continue;
        }

        if (!is_numeric($token)) {
            continue;
        }

        $port = (int) $token;
        if ($port < 1 || $port > 65535) {
            continue;
        }

        $portKey = (string) $port;
        if (isset($seen[$portKey])) {
            continue;
        }
        $seen[$portKey] = true;
        $ports[] = $port;
    }

    return $ports;
}

function mgk_normalize_port_status_map($value) {
    $map = array();
    if (!is_array($value)) {
        return $map;
    }

    foreach ($value as $key => $item) {
        $port = 0;
        if (is_numeric($key)) {
            $port = (int) $key;
        }
        if (($port < 1 || $port > 65535) && is_array($item) && isset($item['port']) && is_numeric($item['port'])) {
            $port = (int) $item['port'];
        }
        if ($port < 1 || $port > 65535) {
            continue;
        }

        $rawStatus = $item;
        if (is_array($item) && array_key_exists('ok', $item)) {
            $rawStatus = $item['ok'];
        }

        $map[(string) $port] = mgk_boolish_to_int($rawStatus, null);
    }

    if (count($map) > 1) {
        ksort($map, SORT_NUMERIC);
    }

    return $map;
}

function mgk_get_expected_iis_ports($device) {
    if (!is_array($device)) {
        return array();
    }

    $ports = array();
    if (array_key_exists('expect_iis_ports', $device)) {
        $ports = mgk_parse_port_list($device['expect_iis_ports'], array());
    }

    if (count($ports) === 0) {
        $watchIis = isset($device['expect_iis']) ? mgk_to_bool($device['expect_iis'], false) : false;
        if ($watchIis) {
            $ports = array(80, 443);
        }
    }

    return $ports;
}

function mgk_get_expected_java_ports($device) {
    if (!is_array($device)) {
        return array();
    }

    $ports = array();
    if (array_key_exists('expect_java_ports', $device)) {
        $ports = mgk_parse_port_list($device['expect_java_ports'], array());
    }

    if (count($ports) === 0 && isset($device['expect_java_port']) && is_numeric($device['expect_java_port'])) {
        $legacyPort = (int) $device['expect_java_port'];
        if ($legacyPort >= 1 && $legacyPort <= 65535) {
            $ports[] = $legacyPort;
        }
    }

    return $ports;
}

function mgk_normalize_service_checks($value, $fallbackList) {
    $rawItems = array();
    if (is_array($value)) {
        $rawItems = $value;
    } else {
        $text = trim((string) $value);
        if ($text === '') {
            if (is_array($fallbackList)) {
                $rawItems = $fallbackList;
            } else {
                $fallbackText = trim((string) $fallbackList);
                if ($fallbackText !== '') {
                    $rawItems = preg_split('/\r\n|\n|\r/', $fallbackText);
                }
            }
        } else {
            $lines = preg_split('/\r\n|\n|\r/', $text);
            for ($lineIdx = 0; $lineIdx < count($lines); $lineIdx++) {
                $line = trim((string) $lines[$lineIdx]);
                if ($line === '') {
                    continue;
                }
                $parts = preg_split('/\s*[;|]+\s*/', $line);
                for ($partIdx = 0; $partIdx < count($parts); $partIdx++) {
                    $part = trim((string) $parts[$partIdx]);
                    if ($part !== '') {
                        $rawItems[] = $part;
                    }
                }
            }
        }
    }

    $normalized = array();
    $seen = array();
    for ($i = 0; $i < count($rawItems); $i++) {
        $entry = $rawItems[$i];
        $label = '';
        $port = 0;

        if (is_array($entry)) {
            $portRaw = mgk_get_nested($entry, array('port', 'puerto'), null);
            $labelRaw = mgk_get_nested($entry, array('label', 'name', 'descripcion', 'description', 'servicio'), '');
            if (is_numeric($portRaw)) {
                $port = (int) $portRaw;
            }
            $label = mgk_clean_text($labelRaw, 90);
        } else {
            $line = trim((string) $entry);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('/^(.*?)[\s]*[:=,][\s]*([0-9]{1,5})$/', $line, $match)) {
                $label = mgk_clean_text($match[1], 90);
                $port = (int) $match[2];
            } elseif (preg_match('/^([0-9]{1,5})$/', $line, $matchOnlyPort)) {
                $port = (int) $matchOnlyPort[1];
            } else {
                continue;
            }
        }

        if ($port < 1 || $port > 65535) {
            continue;
        }
        if ($label === '') {
            $label = 'Servicio ' . $port;
        }

        $dedupeKey = strtolower($label) . '|' . $port;
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;

        $normalized[] = array(
            'label' => $label,
            'port' => $port
        );
        if (count($normalized) >= 40) {
            break;
        }
    }

    return $normalized;
}

function mgk_get_custom_service_checks($device) {
    if (!is_array($device)) {
        return array();
    }

    if (array_key_exists('service_checks', $device)) {
        return mgk_normalize_service_checks($device['service_checks'], array());
    }

    return array();
}

function mgk_extract_process_checks($payload) {
    $checks = array(
        'iis' => null,
        'iis_ports' => array(),
        'java_port_ok' => null,
        'java_port' => null,
        'java_ports' => array(),
        'service_ports' => array()
    );

    if (!is_array($payload)) {
        return $checks;
    }

    $iisRaw = mgk_get_nested($payload, array('iis', 'iis_up', 'w3svc'), null);
    $checks['iis'] = mgk_boolish_to_int($iisRaw, null);
    $checks['iis_ports'] = mgk_normalize_port_status_map(
        mgk_get_nested($payload, array('iis_ports', 'iis_port_status', 'ports_iis'), array())
    );

    $javaOkRaw = mgk_get_nested($payload, array('java_port_ok', 'java_ok', 'java8080', 'java_8080'), null);
    $checks['java_port_ok'] = mgk_boolish_to_int($javaOkRaw, null);

    $javaPortRaw = mgk_get_nested($payload, array('java_port', 'java_port_number', 'java_expected_port'), null);
    if (is_numeric($javaPortRaw)) {
        $javaPort = (int) $javaPortRaw;
        if ($javaPort >= 1 && $javaPort <= 65535) {
            $checks['java_port'] = $javaPort;
        }
    }
    $checks['java_ports'] = mgk_normalize_port_status_map(
        mgk_get_nested($payload, array('java_ports', 'java_port_status', 'ports_java'), array())
    );

    if ($checks['java_port'] !== null && !array_key_exists((string) $checks['java_port'], $checks['java_ports'])) {
        $checks['java_ports'][(string) $checks['java_port']] = $checks['java_port_ok'];
    }
    $checks['service_ports'] = mgk_normalize_port_status_map(
        mgk_get_nested($payload, array('service_ports', 'svc_ports', 'custom_service_ports'), array())
    );

    return $checks;
}

function mgk_normalize_metrics($metricsPayload) {
    if (!is_array($metricsPayload)) {
        return array();
    }

    $metrics = array();

    $cpu = mgk_get_nested($metricsPayload, array('cpu', 'cpu_pct', 'cpuPercent'), null);
    $ram = mgk_get_nested($metricsPayload, array('ram', 'ram_pct', 'memory', 'memory_pct'), null);
    $disk = mgk_get_nested($metricsPayload, array('disk', 'disk_pct', 'storage', 'storage_pct'), null);
    $network = mgk_get_nested($metricsPayload, array('network', 'network_pct', 'network_percent', 'net', 'net_pct', 'net_percent'), null);
    $networkMbps = mgk_get_nested($metricsPayload, array('network_mbps', 'net_mbps', 'bandwidth_mbps', 'throughput_mbps'), null);
    $temp = mgk_get_nested($metricsPayload, array('temp', 'temp_c', 'temperature'), null);
    $latency = mgk_get_nested($metricsPayload, array('latency', 'latency_ms', 'ping_ms'), null);
    $ramUsedBytes = mgk_bytes_from_metric_candidates(
        $metricsPayload,
        array('ram_used_bytes', 'memory_used_bytes', 'mem_used_bytes'),
        array('ram_used_kb', 'memory_used_kb', 'mem_used_kb'),
        array('ram_used_mb', 'memory_used_mb', 'mem_used_mb'),
        array('ram_used_gb', 'memory_used_gb', 'mem_used_gb')
    );
    $ramTotalBytes = mgk_bytes_from_metric_candidates(
        $metricsPayload,
        array('ram_total_bytes', 'memory_total_bytes', 'mem_total_bytes'),
        array('ram_total_kb', 'memory_total_kb', 'mem_total_kb'),
        array('ram_total_mb', 'memory_total_mb', 'mem_total_mb'),
        array('ram_total_gb', 'memory_total_gb', 'mem_total_gb')
    );
    $diskUsedBytes = mgk_bytes_from_metric_candidates(
        $metricsPayload,
        array('disk_used_bytes', 'storage_used_bytes', 'fs_used_bytes'),
        array('disk_used_kb', 'storage_used_kb', 'fs_used_kb'),
        array('disk_used_mb', 'storage_used_mb', 'fs_used_mb'),
        array('disk_used_gb', 'storage_used_gb', 'fs_used_gb')
    );
    $diskTotalBytes = mgk_bytes_from_metric_candidates(
        $metricsPayload,
        array('disk_total_bytes', 'storage_total_bytes', 'fs_total_bytes'),
        array('disk_total_kb', 'storage_total_kb', 'fs_total_kb'),
        array('disk_total_mb', 'storage_total_mb', 'fs_total_mb'),
        array('disk_total_gb', 'storage_total_gb', 'fs_total_gb')
    );

    if (!is_numeric($ram) && $ramTotalBytes !== null && $ramTotalBytes > 0 && $ramUsedBytes !== null) {
        $ram = ((float) $ramUsedBytes * 100.0) / (float) $ramTotalBytes;
    }
    if (!is_numeric($disk) && $diskTotalBytes !== null && $diskTotalBytes > 0 && $diskUsedBytes !== null) {
        $disk = ((float) $diskUsedBytes * 100.0) / (float) $diskTotalBytes;
    }

    if (is_numeric($cpu)) {
        $metrics['cpu'] = max(0, min(100, (float) $cpu));
    }
    if (is_numeric($ram)) {
        $metrics['ram'] = max(0, min(100, (float) $ram));
    }
    if (is_numeric($disk)) {
        $metrics['disk'] = max(0, min(100, (float) $disk));
    }
    if (!is_numeric($network) && is_numeric($networkMbps)) {
        $network = (float) $networkMbps;
    }
    if (is_numeric($network)) {
        $metrics['network'] = max(0, min(100, (float) $network));
    }
    if (is_numeric($temp)) {
        $metrics['temp'] = (float) $temp;
    }
    if (is_numeric($latency)) {
        $metrics['latency'] = max(0, (float) $latency);
    }
    if ($ramUsedBytes !== null) {
        $metrics['ram_used_bytes'] = max(0, round((float) $ramUsedBytes, 2));
    }
    if ($ramTotalBytes !== null) {
        $metrics['ram_total_bytes'] = max(0, round((float) $ramTotalBytes, 2));
    }
    if ($diskUsedBytes !== null) {
        $metrics['disk_used_bytes'] = max(0, round((float) $diskUsedBytes, 2));
    }
    if ($diskTotalBytes !== null) {
        $metrics['disk_total_bytes'] = max(0, round((float) $diskTotalBytes, 2));
    }

    return $metrics;
}

function mgk_build_device_status($device, $metricPacket, $offlineAfterSeconds) {
    $thresholds = isset($device['thresholds']) && is_array($device['thresholds'])
        ? $device['thresholds']
        : mgk_default_thresholds();
    $defaultThresholds = mgk_default_thresholds();

    $metricsStatus = array(
        'cpu' => 'red',
        'ram' => 'red',
        'disk' => 'red',
        'network' => 'green'
    );
    $servicesStatus = array();

    $status = array(
        'overall' => 'red',
        'offline' => false,
        'reason' => 'Sin datos reportados.',
        'metrics' => $metricsStatus,
        'services' => $servicesStatus,
        'label' => 'Critico'
    );

    if (!is_array($metricPacket) || empty($metricPacket)) {
        return $status;
    }

    $age = mgk_age_seconds(isset($metricPacket['updated_at']) ? $metricPacket['updated_at'] : '');
    if ($age === null || $age > (int) $offlineAfterSeconds) {
        $status['offline'] = true;
        $status['reason'] = 'Sin reporte reciente del equipo.';
        return $status;
    }

    $worstRank = 1;
    $missing = false;
    $serviceIssues = array();
    foreach (array('cpu', 'ram', 'disk') as $metricName) {
        $value = isset($metricPacket[$metricName]) ? $metricPacket[$metricName] : null;
        if (!is_numeric($value)) {
            $metricsStatus[$metricName] = 'red';
            $missing = true;
            $worstRank = max($worstRank, 3);
            continue;
        }

        $warning = isset($thresholds[$metricName]['warning']) ? $thresholds[$metricName]['warning'] : $defaultThresholds[$metricName]['warning'];
        $critical = isset($thresholds[$metricName]['critical']) ? $thresholds[$metricName]['critical'] : $defaultThresholds[$metricName]['critical'];
        $metricStatus = mgk_metric_status($value, $warning, $critical);

        $metricsStatus[$metricName] = $metricStatus;
        $worstRank = max($worstRank, mgk_status_rank($metricStatus));
    }

    $networkValue = isset($metricPacket['network']) ? $metricPacket['network'] : null;
    if (is_numeric($networkValue)) {
        $networkWarning = isset($thresholds['network']['warning']) ? $thresholds['network']['warning'] : $defaultThresholds['network']['warning'];
        $networkCritical = isset($thresholds['network']['critical']) ? $thresholds['network']['critical'] : $defaultThresholds['network']['critical'];
        $networkStatus = mgk_metric_status($networkValue, $networkWarning, $networkCritical);
        $metricsStatus['network'] = $networkStatus;
        $worstRank = max($worstRank, mgk_status_rank($networkStatus));
    }

    $expectIis = isset($device['expect_iis']) ? mgk_to_bool($device['expect_iis'], false) : false;
    $expectedIisPorts = mgk_get_expected_iis_ports($device);
    $packetIisPorts = isset($metricPacket['proc_iis_ports'])
        ? mgk_normalize_port_status_map($metricPacket['proc_iis_ports'])
        : array();
    if (count($expectedIisPorts) === 0 && count($packetIisPorts) > 0) {
        $expectedIisPorts = mgk_parse_port_list(array_keys($packetIisPorts), array());
    }
    if (count($expectedIisPorts) === 0) {
        $osUsed = isset($metricPacket['ssh_os_used']) ? mgk_normalize_ssh_os($metricPacket['ssh_os_used']) : 'auto';
        $configuredOs = isset($device['ssh_os']) ? mgk_normalize_ssh_os($device['ssh_os']) : 'auto';
        if ($osUsed === 'windows' || $configuredOs === 'windows') {
            $expectedIisPorts = array(80, 443);
        }
    }
    if (count($expectedIisPorts) > 0) {
        for ($iisIdx = 0; $iisIdx < count($expectedIisPorts); $iisIdx++) {
            $iisPort = (int) $expectedIisPorts[$iisIdx];
            $iisPortKey = (string) $iisPort;
            $iisPortStatus = array_key_exists($iisPortKey, $packetIisPorts)
                ? $packetIisPorts[$iisPortKey]
                : null;
            $serviceKey = 'iis_port_' . $iisPort;
            if ($iisPortStatus === 1) {
                $servicesStatus[$serviceKey] = 'green';
            } else {
                $servicesStatus[$serviceKey] = 'red';
                $worstRank = max($worstRank, 3);
                $serviceIssues[] = $iisPortStatus === null
                    ? 'Sin dato de IIS en puerto ' . $iisPort . '.'
                    : 'IIS no escucha en puerto ' . $iisPort . '.';
            }
        }
    }

    if ($expectIis) {
        $iisUp = isset($metricPacket['proc_iis_up']) ? mgk_boolish_to_int($metricPacket['proc_iis_up'], null) : null;
        if ($iisUp === 1) {
            $servicesStatus['iis'] = 'green';
        } else {
            $servicesStatus['iis'] = 'red';
            $worstRank = max($worstRank, 3);
            $serviceIssues[] = $iisUp === null
                ? 'Sin dato de IIS en el ultimo sondeo.'
                : 'IIS no esta corriendo.';
        }
    }

    $expectedJavaPorts = mgk_get_expected_java_ports($device);
    $packetJavaPorts = isset($metricPacket['proc_java_ports'])
        ? mgk_normalize_port_status_map($metricPacket['proc_java_ports'])
        : array();
    if (count($packetJavaPorts) === 0 && isset($metricPacket['proc_java_port']) && is_numeric($metricPacket['proc_java_port'])) {
        $legacyJavaPort = (int) $metricPacket['proc_java_port'];
        if ($legacyJavaPort >= 1 && $legacyJavaPort <= 65535) {
            $packetJavaPorts[(string) $legacyJavaPort] = isset($metricPacket['proc_java_port_ok'])
                ? mgk_boolish_to_int($metricPacket['proc_java_port_ok'], null)
                : null;
        }
    }

    if (count($expectedJavaPorts) > 0) {
        for ($javaIdx = 0; $javaIdx < count($expectedJavaPorts); $javaIdx++) {
            $javaPort = (int) $expectedJavaPorts[$javaIdx];
            $javaPortKey = (string) $javaPort;
            $javaPortOk = array_key_exists($javaPortKey, $packetJavaPorts)
                ? $packetJavaPorts[$javaPortKey]
                : null;
            $serviceKey = 'java_' . $javaPort;
            if ($javaPortOk === 1) {
                $servicesStatus[$serviceKey] = 'green';
            } else {
                $servicesStatus[$serviceKey] = 'red';
                $worstRank = max($worstRank, 3);
                $serviceIssues[] = $javaPortOk === null
                    ? 'Sin dato del proceso Java en puerto ' . $javaPort . '.'
                    : 'Java no aparece escuchando en puerto ' . $javaPort . '.';
            }
        }
    }

    $customServiceChecks = mgk_get_custom_service_checks($device);
    if (count($customServiceChecks) > 0) {
        $packetServicePorts = isset($metricPacket['proc_service_ports'])
            ? mgk_normalize_port_status_map($metricPacket['proc_service_ports'])
            : array();

        for ($svcIdx = 0; $svcIdx < count($customServiceChecks); $svcIdx++) {
            $svc = $customServiceChecks[$svcIdx];
            $svcLabel = isset($svc['label']) ? trim((string) $svc['label']) : '';
            $svcPort = isset($svc['port']) ? (int) $svc['port'] : 0;
            if ($svcPort < 1 || $svcPort > 65535) {
                continue;
            }
            if ($svcLabel === '') {
                $svcLabel = 'Servicio ' . $svcPort;
            }

            $svcPortKey = (string) $svcPort;
            $svcOk = array_key_exists($svcPortKey, $packetServicePorts)
                ? $packetServicePorts[$svcPortKey]
                : null;
            if ($svcOk === null && array_key_exists($svcPortKey, $packetIisPorts)) {
                $svcOk = $packetIisPorts[$svcPortKey];
            }
            if ($svcOk === null && array_key_exists($svcPortKey, $packetJavaPorts)) {
                $svcOk = $packetJavaPorts[$svcPortKey];
            }

            $svcKey = 'svc_' . $svcPort . '_' . ($svcIdx + 1);
            if ($svcOk === 1) {
                $servicesStatus[$svcKey] = 'green';
            } else {
                $servicesStatus[$svcKey] = 'red';
                $worstRank = max($worstRank, 3);
                $serviceIssues[] = $svcOk === null
                    ? 'Sin dato de "' . $svcLabel . '" en puerto ' . $svcPort . '.'
                    : '"' . $svcLabel . '" no esta escuchando en puerto ' . $svcPort . '.';
            }
        }
    }

    $overall = mgk_rank_status($worstRank);
    $label = 'Saludable';
    if ($overall === 'yellow') {
        $label = 'Alerta';
    }
    if ($overall === 'red') {
        $label = 'Critico';
    }

    $reason = $overall === 'green' ? 'Todos los indicadores dentro de umbral.' : 'Hay metricas por encima de umbral.';
    if ($missing) {
        $reason = 'Faltan metricas requeridas del equipo.';
    }
    if (count($serviceIssues) > 0) {
        $serviceReason = implode(' ', $serviceIssues);
        if ($reason !== '') {
            $reason .= ' ' . $serviceReason;
        } else {
            $reason = $serviceReason;
        }
    }

    return array(
        'overall' => $overall,
        'offline' => false,
        'reason' => $reason,
        'metrics' => $metricsStatus,
        'services' => $servicesStatus,
        'label' => $label
    );
}

function mgk_get_header_value($name) {
    $name = strtoupper(str_replace('-', '_', $name));
    $serverKey = 'HTTP_' . $name;
    if (isset($_SERVER[$serverKey])) {
        return trim((string) $_SERVER[$serverKey]);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower($key) === strtolower(str_replace('_', '-', $name))) {
                    return trim((string) $value);
                }
            }
        }
    }

    return '';
}

function mgk_http_get_json($url, $username, $password, $timeoutSeconds) {
    $url = trim((string) $url);
    if ($url === '') {
        return array('ok' => false, 'error' => 'URL vacia para pull_http.');
    }

    $timeoutSeconds = (int) $timeoutSeconds;
    if ($timeoutSeconds < 1) {
        $timeoutSeconds = 3;
    }

    $rawBody = '';
    $error = '';
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        if ($username !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        }

        $rawBody = curl_exec($ch);
        if ($rawBody === false) {
            $error = curl_error($ch);
        }
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $headers = "Accept: application/json\r\n";
        if ($username !== '') {
            $headers .= 'Authorization: Basic ' . base64_encode($username . ':' . $password) . "\r\n";
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'header' => $headers
            )
        ));

        $rawBody = @file_get_contents($url, false, $context);
        if ($rawBody === false) {
            $error = 'No fue posible conectar al endpoint remoto.';
        }

        if (isset($http_response_header) && is_array($http_response_header) && count($http_response_header) > 0) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
                $httpCode = (int) $match[1];
            }
        }
    }

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    if (!is_string($rawBody) || trim($rawBody) === '') {
        return array('ok' => false, 'error' => 'El endpoint remoto respondio vacio.');
    }

    if ($httpCode >= 400) {
        return array('ok' => false, 'error' => 'HTTP ' . $httpCode . ' recibido desde endpoint remoto.');
    }

    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        return array('ok' => false, 'error' => 'JSON invalido recibido en pull_http.');
    }

    return array('ok' => true, 'data' => $decoded, 'http_code' => $httpCode);
}

function mgk_pull_http_metrics($device) {
    $url = isset($device['pull_url']) ? $device['pull_url'] : '';
    $username = isset($device['username']) ? (string) $device['username'] : '';
    $password = isset($device['password_enc']) ? mgk_decrypt($device['password_enc']) : '';

    $response = mgk_http_get_json($url, $username, $password, 3);
    if (!$response['ok']) {
        return $response;
    }

    $payload = $response['data'];
    $metricsPayload = $payload;
    if (isset($payload['metrics']) && is_array($payload['metrics'])) {
        $metricsPayload = $payload['metrics'];
    }

    $metrics = mgk_normalize_metrics($metricsPayload);
    if (empty($metrics)) {
        return array('ok' => false, 'error' => 'No se detectaron metricas validas en pull_http.');
    }

    return array('ok' => true, 'metrics' => $metrics);
}

function mgk_is_windows_runtime() {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function mgk_find_binary($candidates) {
    static $cache = array();

    if (!is_array($candidates)) {
        return '';
    }

    for ($i = 0; $i < count($candidates); $i++) {
        $candidate = trim((string) $candidates[$i]);
        if ($candidate === '') {
            continue;
        }

        if (isset($cache[$candidate])) {
            if ($cache[$candidate] !== '') {
                return $cache[$candidate];
            }
            continue;
        }

        if (preg_match('/[\\\\\\/]/', $candidate)) {
            if (file_exists($candidate)) {
                $cache[$candidate] = $candidate;
                return $candidate;
            }
            $cache[$candidate] = '';
            continue;
        }

        $output = array();
        $exitCode = 1;
        if (mgk_is_windows_runtime()) {
            @exec('where ' . $candidate . ' 2>NUL', $output, $exitCode);
        } else {
            @exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null', $output, $exitCode);
        }

        if ($exitCode === 0 && isset($output[0]) && trim($output[0]) !== '') {
            $resolved = trim($output[0]);
            $cache[$candidate] = $resolved;
            return $resolved;
        }

        $cache[$candidate] = '';
    }

    return '';
}

function mgk_exec_shell_command($command) {
    $output = array();
    $exitCode = 1;
    @exec($command, $output, $exitCode);

    return array(
        'ok' => $exitCode === 0,
        'exit_code' => $exitCode,
        'output' => implode("\n", $output)
    );
}

function mgk_compact_text($text, $maxLen) {
    $compact = preg_replace('/\s+/', ' ', trim((string) $text));
    if (!is_string($compact)) {
        $compact = '';
    }
    if (strlen($compact) > $maxLen) {
        $compact = substr($compact, 0, $maxLen) . '...';
    }
    return $compact;
}

function mgk_extract_json_chunk($text) {
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $len = strlen($text);
    $start = -1;
    $depth = 0;
    $inString = false;
    $isEscaped = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];

        if ($start < 0) {
            if ($ch === '{') {
                $start = $i;
                $depth = 1;
                $inString = false;
                $isEscaped = false;
            }
            continue;
        }

        if ($inString) {
            if ($isEscaped) {
                $isEscaped = false;
                continue;
            }
            if ($ch === '\\') {
                $isEscaped = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            continue;
        }
        if ($ch === '{') {
            $depth++;
            continue;
        }
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }
    }

    return '';
}

function mgk_parse_metrics_text($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return array('ok' => false, 'error' => 'Salida vacia al consultar por SSH.');
    }

    $decodeCandidates = array($text);
    $chunk = mgk_extract_json_chunk($text);
    if ($chunk !== '' && $chunk !== $text) {
        $decodeCandidates[] = $chunk;
    }

    for ($i = 0; $i < count($decodeCandidates); $i++) {
        $candidate = $decodeCandidates[$i];
        $decoded = json_decode($candidate, true);
        if (!is_array($decoded)) {
            continue;
        }

        $metricsPayload = $decoded;
        if (isset($decoded['metrics']) && is_array($decoded['metrics'])) {
            $metricsPayload = $decoded['metrics'];
        }

        $metrics = mgk_normalize_metrics($metricsPayload);
        $checks = mgk_extract_process_checks($decoded);
        if (!empty($metrics)) {
            return array('ok' => true, 'metrics' => $metrics, 'checks' => $checks);
        }
    }

    $regexMetrics = array();
    if (preg_match('/cpu\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchCpu)) {
        $regexMetrics['cpu'] = (float) $matchCpu[1];
    }
    if (preg_match('/ram\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchRam)) {
        $regexMetrics['ram'] = (float) $matchRam[1];
    }
    if (preg_match('/disk\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchDisk)) {
        $regexMetrics['disk'] = (float) $matchDisk[1];
    }
    if (preg_match('/(?:network|net)\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchNetwork)) {
        $regexMetrics['network'] = (float) $matchNetwork[1];
    }
    if (preg_match('/ram_used_bytes\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchRamUsedBytes)) {
        $regexMetrics['ram_used_bytes'] = (float) $matchRamUsedBytes[1];
    }
    if (preg_match('/ram_total_bytes\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchRamTotalBytes)) {
        $regexMetrics['ram_total_bytes'] = (float) $matchRamTotalBytes[1];
    }
    if (preg_match('/disk_used_bytes\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchDiskUsedBytes)) {
        $regexMetrics['disk_used_bytes'] = (float) $matchDiskUsedBytes[1];
    }
    if (preg_match('/disk_total_bytes\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $matchDiskTotalBytes)) {
        $regexMetrics['disk_total_bytes'] = (float) $matchDiskTotalBytes[1];
    }

    $regexChecks = array(
        'iis' => null,
        'iis_ports' => array(),
        'java_port_ok' => null,
        'java_port' => null,
        'java_ports' => array(),
        'service_ports' => array()
    );
    if (preg_match('/iis\s*[:=]\s*([0-9]+|true|false|running|stopped|up|down)/i', $text, $matchIis)) {
        $regexChecks['iis'] = mgk_boolish_to_int($matchIis[1], null);
    }
    if (preg_match_all('/iis_port_([0-9]{1,5})_ok\s*[:=]\s*([0-9]+|true|false|running|stopped|up|down|ok|alive|dead)/i', $text, $matchIisPorts, PREG_SET_ORDER)) {
        for ($iisMatchIdx = 0; $iisMatchIdx < count($matchIisPorts); $iisMatchIdx++) {
            $port = (int) $matchIisPorts[$iisMatchIdx][1];
            if ($port < 1 || $port > 65535) {
                continue;
            }
            $regexChecks['iis_ports'][(string) $port] = mgk_boolish_to_int($matchIisPorts[$iisMatchIdx][2], null);
        }
    }
    if (preg_match('/java_port_ok\s*[:=]\s*([0-9]+|true|false|up|down|ok)/i', $text, $matchJavaOk)) {
        $regexChecks['java_port_ok'] = mgk_boolish_to_int($matchJavaOk[1], null);
    }
    if (preg_match('/java_port\s*[:=]\s*([0-9]+)/i', $text, $matchJavaPort)) {
        $port = (int) $matchJavaPort[1];
        if ($port >= 1 && $port <= 65535) {
            $regexChecks['java_port'] = $port;
        }
    }
    if (preg_match_all('/java_port_([0-9]{1,5})_ok\s*[:=]\s*([0-9]+|true|false|up|down|ok|alive|dead)/i', $text, $matchJavaPorts, PREG_SET_ORDER)) {
        for ($javaMatchIdx = 0; $javaMatchIdx < count($matchJavaPorts); $javaMatchIdx++) {
            $port = (int) $matchJavaPorts[$javaMatchIdx][1];
            if ($port < 1 || $port > 65535) {
                continue;
            }
            $regexChecks['java_ports'][(string) $port] = mgk_boolish_to_int($matchJavaPorts[$javaMatchIdx][2], null);
        }
    }
    if ($regexChecks['java_port'] !== null && !array_key_exists((string) $regexChecks['java_port'], $regexChecks['java_ports'])) {
        $regexChecks['java_ports'][(string) $regexChecks['java_port']] = $regexChecks['java_port_ok'];
    }
    if (preg_match_all('/svc_port_([0-9]{1,5})_ok\s*[:=]\s*([0-9]+|true|false|up|down|ok|alive|dead)/i', $text, $matchSvcPorts, PREG_SET_ORDER)) {
        for ($svcMatchIdx = 0; $svcMatchIdx < count($matchSvcPorts); $svcMatchIdx++) {
            $port = (int) $matchSvcPorts[$svcMatchIdx][1];
            if ($port < 1 || $port > 65535) {
                continue;
            }
            $regexChecks['service_ports'][(string) $port] = mgk_boolish_to_int($matchSvcPorts[$svcMatchIdx][2], null);
        }
    }

    if (!empty($regexMetrics)) {
        $metrics = mgk_normalize_metrics($regexMetrics);
        if (!empty($metrics)) {
            return array('ok' => true, 'metrics' => $metrics, 'checks' => $regexChecks);
        }
    }

    return array(
        'ok' => false,
        'error' => 'No se pudieron parsear metricas en salida SSH: ' . mgk_compact_text($text, 180)
    );
}

function mgk_extract_plink_hostkey($text) {
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $normalized = str_replace(array("\r\n", "\r"), "\n", $text);
    $linePatterns = array(
        '/^\s*(ssh-[a-z0-9-]+\s+\d+\s+SHA256:[A-Za-z0-9+\/=]+)\s*$/mi',
        '/^\s*(ssh-[a-z0-9-]+\s+\d+\s+(?:[0-9a-f]{2}:){15,}[0-9a-f]{2})\s*$/mi',
        '/^\s*(ssh-[a-z0-9-]+\s+SHA256:[A-Za-z0-9+\/=]+)\s*$/mi',
        '/^\s*(ssh-[a-z0-9-]+\s+(?:[0-9a-f]{2}:){15,}[0-9a-f]{2})\s*$/mi'
    );
    for ($patternIdx = 0; $patternIdx < count($linePatterns); $patternIdx++) {
        if (preg_match($linePatterns[$patternIdx], $normalized, $match)) {
            $candidate = trim($match[1]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    $inlinePatterns = array(
        '/key fingerprint is:\s*([^\n\r]+)/i',
        '/key fingerprint is:\s*[\r\n]+\s*([^\n\r]+)/i',
        '/fingerprint:\s*([^\n\r]+)/i'
    );
    for ($inlineIdx = 0; $inlineIdx < count($inlinePatterns); $inlineIdx++) {
        if (!preg_match($inlinePatterns[$inlineIdx], $normalized, $inlineMatch)) {
            continue;
        }

        $candidate = trim($inlineMatch[1], " \t\n\r\0\x0B.");
        if ($candidate === '') {
            continue;
        }

        if (preg_match('/^(ssh-[a-z0-9-]+\s+\d+\s+SHA256:[A-Za-z0-9+\/=]+)$/i', $candidate, $typedShaMatch)) {
            return trim($typedShaMatch[1]);
        }

        if (preg_match('/^(ssh-[a-z0-9-]+\s+\d+\s+(?:[0-9a-f]{2}:){15,}[0-9a-f]{2})$/i', $candidate, $typedHexMatch)) {
            return trim($typedHexMatch[1]);
        }

        if (preg_match('/^(SHA256:[A-Za-z0-9+\/=]+)$/i', $candidate, $shaOnlyMatch)) {
            return trim($shaOnlyMatch[1]);
        }

        if (preg_match('/^((?:[0-9a-f]{2}:){15,}[0-9a-f]{2})$/i', $candidate, $hexOnlyMatch)) {
            return strtolower(trim($hexOnlyMatch[1]));
        }
    }

    if (preg_match('/SHA256:[A-Za-z0-9+\/=]+/i', $normalized, $shaMatch)) {
        return trim($shaMatch[0]);
    }

    if (preg_match('/(?:[0-9a-f]{2}:){15,}[0-9a-f]{2}/i', $normalized, $hexMatch)) {
        return strtolower(trim($hexMatch[0]));
    }

    return '';
}

function mgk_build_linux_ssh_command($javaPorts, $servicePorts) {
    $javaPorts = mgk_parse_port_list($javaPorts, array());
    $servicePorts = mgk_parse_port_list($servicePorts, array());

    $command = "LC_ALL=C net_b1=\$(cat /proc/net/dev 2>/dev/null | awk -F'[: ]+' '/:/{if(\$1 !~ /lo/){rx+=\$3; tx+=\$11}} END {print rx+tx+0}'); idle=\$(vmstat 1 2 2>/dev/null | tail -1 | tr -s \\  | xargs | cut -d\\  -f15); if [ x\$idle = x ]; then idle=0; fi; cpu=\$((100-idle)); mem_line=\$(free -k 2>/dev/null | grep Mem: | tr -s \\  | xargs); mem_total=\$(echo \$mem_line | cut -d\\  -f2); mem_used=\$(echo \$mem_line | cut -d\\  -f3); if [ x\$mem_total = x ] || [ \$mem_total -lt 1 ] 2>/dev/null; then mem_total=0; fi; if [ x\$mem_used = x ] || [ \$mem_used -lt 0 ] 2>/dev/null; then mem_used=0; fi; if [ \$mem_total -gt 0 ] 2>/dev/null; then ram=\$((100*mem_used/mem_total)); else ram=0; fi; ram_total_bytes=\$((mem_total*1024)); ram_used_bytes=\$((mem_used*1024)); disk_line=\$(df -Pk / 2>/dev/null | tail -1 | tr -s \\  | xargs); disk_total=\$(echo \$disk_line | cut -d\\  -f2); disk_used=\$(echo \$disk_line | cut -d\\  -f3); disk_pct=\$(echo \$disk_line | cut -d\\  -f5 | tr -d %); if [ x\$disk_total = x ] || [ \$disk_total -lt 1 ] 2>/dev/null; then disk_total=0; fi; if [ x\$disk_used = x ] || [ \$disk_used -lt 0 ] 2>/dev/null; then disk_used=0; fi; if [ x\$disk_pct = x ] || [ \$disk_pct -lt 0 ] 2>/dev/null; then if [ \$disk_total -gt 0 ] 2>/dev/null; then disk_pct=\$((100*disk_used/disk_total)); else disk_pct=0; fi; fi; disk_total_bytes=\$((disk_total*1024)); disk_used_bytes=\$((disk_used*1024)); net_b2=\$(cat /proc/net/dev 2>/dev/null | awk -F'[: ]+' '/:/{if(\$1 !~ /lo/){rx+=\$3; tx+=\$11}} END {print rx+tx+0}'); net_delta=\$((net_b2-net_b1)); if [ x\$net_delta = x ] || [ \$net_delta -lt 0 ] 2>/dev/null; then net_delta=0; fi; net=\$(awk -v bytes=\$net_delta 'BEGIN{v=(bytes*8)/1000000; if(v<0){v=0}; if(v>100){v=100}; printf \"%.2f\", v}'); cpu=\${cpu:-0}; ram=\${ram:-0}; disk_pct=\${disk_pct:-0}; net=\${net:-0}; ram_total_bytes=\${ram_total_bytes:-0}; ram_used_bytes=\${ram_used_bytes:-0}; disk_total_bytes=\${disk_total_bytes:-0}; disk_used_bytes=\${disk_used_bytes:-0}; echo cpu=\$cpu; echo ram=\$ram; echo disk=\$disk_pct; echo network=\$net; echo ram_used_bytes=\$ram_used_bytes; echo ram_total_bytes=\$ram_total_bytes; echo disk_used_bytes=\$disk_used_bytes; echo disk_total_bytes=\$disk_total_bytes";
    $listenProbeBody = "if ss -ltn 2>/dev/null | grep -E :\$p[^0-9] >/dev/null 2>&1; then %RESULT%=1; elif netstat -ltn 2>/dev/null | grep -E :\$p[^0-9] >/dev/null 2>&1; then %RESULT%=1; elif lsof -iTCP -sTCP:LISTEN -n -P 2>/dev/null | grep -E :\$p[^0-9] >/dev/null 2>&1; then %RESULT%=1; fi";

    if (count($javaPorts) > 0) {
        for ($portIdx = 0; $portIdx < count($javaPorts); $portIdx++) {
            $javaPort = (int) $javaPorts[$portIdx];
            if ($javaPort < 1 || $javaPort > 65535) {
                continue;
            }

            $command .= "; p={$javaPort}; jok=0";
            $command .= "; jarg=\$(ps -efww 2>/dev/null | grep java | grep -v grep | grep -F server.port=\$p | wc -l | tr -d \\ )";
            $command .= "; jarg=\${jarg:-0}";
            $command .= "; if [ \$jarg -gt 0 ] 2>/dev/null; then jok=1; fi";
            $command .= "; if [ \$jok -ne 1 ]; then " . str_replace('%RESULT%', 'jok', $listenProbeBody) . "; fi";
            $command .= "; echo java_port_{$javaPort}_ok=\$jok";
            if ($portIdx === 0) {
                $command .= "; echo java_port=\$p; echo java_port_ok=\$jok";
            }
        }
    }

    if (count($servicePorts) > 0) {
        for ($svcIdx = 0; $svcIdx < count($servicePorts); $svcIdx++) {
            $svcPort = (int) $servicePorts[$svcIdx];
            if ($svcPort < 1 || $svcPort > 65535) {
                continue;
            }

            $command .= "; p={$svcPort}; slisten=0";
            $command .= "; " . str_replace('%RESULT%', 'slisten', $listenProbeBody);
            $command .= "; echo svc_port_{$svcPort}_ok=\$slisten";
        }
    }

    return $command;
}

function mgk_utf16le_base64($text) {
    $utf16 = '';
    if (function_exists('mb_convert_encoding')) {
        $utf16 = mb_convert_encoding((string) $text, 'UTF-16LE', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $utf16 = iconv('UTF-8', 'UTF-16LE', (string) $text);
    }

    if (!is_string($utf16) || $utf16 === '') {
        return '';
    }

    return base64_encode($utf16);
}

function mgk_build_windows_ssh_command($iisPorts, $servicePorts) {
    $iisPorts = mgk_parse_port_list($iisPorts, array());
    $iisPortsLiteral = '';
    if (count($iisPorts) > 0) {
        $iisPortsLiteral = implode(',', $iisPorts);
    }
    $servicePorts = mgk_parse_port_list($servicePorts, array());
    $servicePortsLiteral = '';
    if (count($servicePorts) > 0) {
        $servicePortsLiteral = implode(',', $servicePorts);
    }

    $psCommands = array(
        "\$cpu=\$null",
        "try{\$perfCpu=Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -ErrorAction Stop | Where-Object {\$_.Name -eq '_Total'} | Select-Object -First 1; if(\$perfCpu -and \$perfCpu.PercentProcessorTime -ne \$null){\$cpu=[double]\$perfCpu.PercentProcessorTime}}catch{}",
        "if(\$cpu -eq \$null){try{\$counter=Get-Counter '\\Processor(_Total)\\% Processor Time' -MaxSamples 1 -ErrorAction Stop; \$samples=@(\$counter.CounterSamples); if(\$samples.Count -gt 0){\$cpu=[double]\$samples[\$samples.Count-1].CookedValue}}catch{}}",
        "\$cpuUtility=\$null",
        "try{\$perfUtil=Get-CimInstance Win32_PerfFormattedData_Counters_ProcessorInformation -ErrorAction Stop | Where-Object {\$_.Name -eq '_Total'} | Select-Object -First 1; if(\$perfUtil -and \$perfUtil.PercentProcessorUtility -ne \$null){\$cpuUtility=[double]\$perfUtil.PercentProcessorUtility}}catch{}",
        "if(\$cpuUtility -ne \$null){if(\$cpu -eq \$null){\$cpu=\$cpuUtility} elseif(\$cpuUtility -gt \$cpu){\$cpu=\$cpuUtility}}",
        "if(\$cpu -eq \$null){try{\$cpuObj=Get-CimInstance Win32_Processor -ErrorAction Stop | Measure-Object -Property LoadPercentage -Average; if(\$cpuObj -and \$cpuObj.Average -ne \$null){\$cpu=[double]\$cpuObj.Average}}catch{}}",
        "if(\$cpu -eq \$null -or [double]::IsNaN([double]\$cpu) -or [double]::IsInfinity([double]\$cpu)){\$cpu=0}",
        "if(\$cpu -lt 0){\$cpu=0}",
        "if(\$cpu -gt 100){\$cpu=100}",
        "\$os=Get-CimInstance Win32_OperatingSystem",
        "\$ram=0",
        "\$ramTotalBytes=0",
        "\$ramUsedBytes=0",
        "if(\$os.TotalVisibleMemorySize -gt 0){\$ramTotalBytes=[double]\$os.TotalVisibleMemorySize*1024;\$ramUsedBytes=[double](\$os.TotalVisibleMemorySize-\$os.FreePhysicalMemory)*1024;\$ram=((\$os.TotalVisibleMemorySize-\$os.FreePhysicalMemory)/\$os.TotalVisibleMemorySize)*100}",
        "\$diskObj=Get-CimInstance Win32_LogicalDisk -Filter \"DeviceID='C:'\"",
        "\$disk=0",
        "\$diskTotalBytes=0",
        "\$diskUsedBytes=0",
        "if(\$diskObj -and \$diskObj.Size -gt 0){\$diskTotalBytes=[double]\$diskObj.Size;\$diskUsedBytes=[double](\$diskObj.Size-\$diskObj.FreeSpace);\$disk=((\$diskObj.Size-\$diskObj.FreeSpace)/\$diskObj.Size)*100}",
        "\$network=\$null",
        "try{\$netSamples=(Get-Counter '\\Network Interface(*)\\Bytes Total/sec' -ErrorAction Stop).CounterSamples; if(\$netSamples){\$netBytes=0; foreach(\$s in \$netSamples){if(\$s.InstanceName -notmatch 'Loopback|isatap|Teredo'){\$netBytes+=[double]\$s.CookedValue}}; if(\$netBytes -gt 0){\$network=(\$netBytes*8)/1000000}}}catch{}",
        "if(\$network -eq \$null){try{\$a1=Get-NetAdapterStatistics -ErrorAction Stop | Where-Object {\$_.Name -notmatch 'Loopback|isatap|Teredo'}; Start-Sleep -Milliseconds 900; \$a2=Get-NetAdapterStatistics -ErrorAction Stop | Where-Object {\$_.Name -notmatch 'Loopback|isatap|Teredo'}; \$b1=0; \$b2=0; foreach(\$it in \$a1){\$b1+=[double]\$it.ReceivedBytes+[double]\$it.SentBytes}; foreach(\$it in \$a2){\$b2+=[double]\$it.ReceivedBytes+[double]\$it.SentBytes}; if(\$b2 -ge \$b1){\$network=((\$b2-\$b1)*8)/1000000}}catch{}}",
        "if(\$network -eq \$null){\$network=0}",
        "if(\$network -lt 0){\$network=0}",
        "if(\$network -gt 100){\$network=100}",
        "\$iis=0",
        "\$svc=Get-Service W3SVC -ErrorAction SilentlyContinue",
        "if(\$svc -and \$svc.Status -eq 'Running'){\$iis=1} elseif(Get-Process w3wp -ErrorAction SilentlyContinue){\$iis=1}",
        "\$iisPorts=@(" . $iisPortsLiteral . ")",
        "\$iisPortsOk=@{}",
        "foreach(\$p in \$iisPorts){\$portUp=0;\$byConn=\$null;if(Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue){\$byConn=Get-NetTCPConnection -State Listen -LocalPort \$p -ErrorAction SilentlyContinue};if(\$byConn){\$portUp=1}else{\$pattern=':'+[string]\$p+'\\s+.*LISTENING';if(netstat -ano | Select-String -Pattern \$pattern -ErrorAction SilentlyContinue){\$portUp=1}};\$iisPortsOk[[string]\$p]=\$portUp}",
        "\$servicePorts=@(" . $servicePortsLiteral . ")",
        "\$servicePortsOk=@{}",
        "foreach(\$p in \$servicePorts){\$portUp=0;\$byConn=\$null;if(Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue){\$byConn=Get-NetTCPConnection -State Listen -LocalPort \$p -ErrorAction SilentlyContinue};if(\$byConn){\$portUp=1}else{\$pattern=':'+[string]\$p+'\\s+.*LISTENING';if(netstat -ano | Select-String -Pattern \$pattern -ErrorAction SilentlyContinue){\$portUp=1}};\$servicePortsOk[[string]\$p]=\$portUp}",
        "[PSCustomObject]@{cpu=[Math]::Round(\$cpu,2);ram=[Math]::Round(\$ram,2);disk=[Math]::Round(\$disk,2);network=[Math]::Round(\$network,2);ram_used_bytes=[Math]::Round(\$ramUsedBytes,0);ram_total_bytes=[Math]::Round(\$ramTotalBytes,0);disk_used_bytes=[Math]::Round(\$diskUsedBytes,0);disk_total_bytes=[Math]::Round(\$diskTotalBytes,0);iis=\$iis;iis_ports=\$iisPortsOk;service_ports=\$servicePortsOk} | ConvertTo-Json -Compress"
    );

    $script = implode(';', $psCommands);

    // Prefer the shorter transport command to avoid plink/cmd line-length limits.
    $escapedScript = str_replace('"', '\\"', $script);
    $plainCommand = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "' . $escapedScript . '"';

    $encoded = mgk_utf16le_base64($script);
    if ($encoded === '') {
        return $plainCommand;
    }

    $encodedCommand = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ' . $encoded;

    if (strlen($plainCommand) <= strlen($encodedCommand)) {
        return $plainCommand;
    }

    return $encodedCommand;
}

function mgk_guess_pubkey_path($privateKeyPath) {
    $privateKeyPath = trim((string) $privateKeyPath);
    if ($privateKeyPath === '') {
        return '';
    }

    if (preg_match('/\.pub$/i', $privateKeyPath)) {
        return $privateKeyPath;
    }

    $candidate = $privateKeyPath . '.pub';
    if (file_exists($candidate)) {
        return $candidate;
    }

    return '';
}

function mgk_ssh_exec_with_extension($device, $remoteCommand) {
    if (!function_exists('ssh2_connect')) {
        return array('ok' => false, 'error' => 'Extension ssh2 no disponible en PHP.');
    }

    $host = isset($device['host']) ? trim((string) $device['host']) : '';
    $port = isset($device['ssh_port']) ? (int) $device['ssh_port'] : 22;
    $username = isset($device['username']) ? trim((string) $device['username']) : '';
    $password = isset($device['password_enc']) ? mgk_decrypt($device['password_enc']) : '';
    $privateKeyPath = isset($device['ssh_key_path']) ? trim((string) $device['ssh_key_path']) : '';

    if ($host === '' || $username === '') {
        return array('ok' => false, 'error' => 'Falta host o usuario para conexion SSH.');
    }

    $conn = @ssh2_connect($host, $port);
    if (!$conn) {
        return array('ok' => false, 'error' => 'No se pudo abrir sesion SSH con extension ssh2.');
    }

    $authOk = false;
    if ($privateKeyPath !== '' && function_exists('ssh2_auth_pubkey_file')) {
        if (!file_exists($privateKeyPath)) {
            return array('ok' => false, 'error' => 'No existe la llave privada SSH configurada.');
        }
        $publicKeyPath = mgk_guess_pubkey_path($privateKeyPath);
        if ($publicKeyPath !== '' && file_exists($publicKeyPath)) {
            $authOk = @ssh2_auth_pubkey_file($conn, $username, $publicKeyPath, $privateKeyPath, $password);
        }
    }

    if (!$authOk && $password !== '' && function_exists('ssh2_auth_password')) {
        $authOk = @ssh2_auth_password($conn, $username, $password);
    }

    if (!$authOk && function_exists('ssh2_auth_agent')) {
        $authOk = @ssh2_auth_agent($conn, $username);
    }

    if (!$authOk) {
        return array('ok' => false, 'error' => 'Autenticacion SSH fallida (ssh2).');
    }

    $stream = @ssh2_exec($conn, $remoteCommand);
    if (!is_resource($stream)) {
        return array('ok' => false, 'error' => 'No fue posible ejecutar comando remoto por ssh2.');
    }

    $errOutput = '';
    $errStream = null;
    if (function_exists('ssh2_fetch_stream')) {
        $errStream = @ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        if (is_resource($errStream)) {
            stream_set_blocking($errStream, true);
            $errOutput = (string) stream_get_contents($errStream);
            fclose($errStream);
        }
    }

    stream_set_blocking($stream, true);
    $out = (string) stream_get_contents($stream);
    $meta = stream_get_meta_data($stream);
    fclose($stream);

    if (is_array($meta) && isset($meta['timed_out']) && $meta['timed_out']) {
        return array('ok' => false, 'error' => 'Timeout en comando SSH (ssh2).');
    }

    if (trim($out) === '' && trim($errOutput) !== '') {
        return array('ok' => false, 'error' => 'Error remoto SSH (ssh2): ' . mgk_compact_text($errOutput, 180));
    }

    if (trim($out) === '') {
        return array('ok' => false, 'error' => 'Comando SSH respondio sin salida util (ssh2).');
    }

    return array('ok' => true, 'output' => $out);
}

function mgk_ssh_exec_with_cli($device, $remoteCommand) {
    $host = isset($device['host']) ? trim((string) $device['host']) : '';
    $port = isset($device['ssh_port']) ? (int) $device['ssh_port'] : 22;
    $username = isset($device['username']) ? trim((string) $device['username']) : '';
    $password = isset($device['password_enc']) ? mgk_decrypt($device['password_enc']) : '';
    $privateKeyPath = isset($device['ssh_key_path']) ? trim((string) $device['ssh_key_path']) : '';

    if ($host === '' || $username === '') {
        return array('ok' => false, 'error' => 'Falta host o usuario para conexion SSH.');
    }

    if ($privateKeyPath !== '' && !file_exists($privateKeyPath)) {
        return array('ok' => false, 'error' => 'No existe la llave privada SSH configurada.');
    }

    $plinkPath = mgk_find_binary(array(
        'plink',
        MONITORAPP_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'plink.exe',
        'C:\\Program Files\\PuTTY\\plink.exe',
        'C:\\Program Files (x86)\\PuTTY\\plink.exe'
    ));

    if ($plinkPath !== '') {
        static $plinkHostKeyCache = array();

        $cacheKey = strtolower($host) . ':' . (int) $port;
        $configuredHostKey = isset($device['ssh_hostkey']) ? trim((string) $device['ssh_hostkey']) : '';
        if ($configuredHostKey === '' && isset($plinkHostKeyCache[$cacheKey])) {
            $configuredHostKey = $plinkHostKeyCache[$cacheKey];
        }

        $baseParts = array(
            escapeshellarg($plinkPath),
            '-batch',
            '-P ' . (int) $port,
            '-l ' . escapeshellarg($username)
        );

        if ($privateKeyPath !== '') {
            $baseParts[] = '-i ' . escapeshellarg($privateKeyPath);
        } elseif ($password !== '') {
            $baseParts[] = '-pw ' . escapeshellarg($password);
        }

        if ($configuredHostKey !== '') {
            $baseParts[] = '-hostkey ' . escapeshellarg($configuredHostKey);
        }

        $runPlink = function ($parts) use ($host, $remoteCommand) {
            $scriptPath = @tempnam(sys_get_temp_dir(), 'mgkcmd_');
            if (!is_string($scriptPath) || $scriptPath === '') {
                return array('ok' => false, 'exit_code' => 1, 'output' => 'No se pudo crear archivo temporal para comando remoto.');
            }

            $scriptBody = str_replace(array("\r\n", "\r"), "\n", (string) $remoteCommand);
            if ($scriptBody === '' || substr($scriptBody, -1) !== "\n") {
                $scriptBody .= "\n";
            }

            $writeOk = @file_put_contents($scriptPath, $scriptBody);
            if ($writeOk === false) {
                @unlink($scriptPath);
                return array('ok' => false, 'exit_code' => 1, 'output' => 'No se pudo escribir comando temporal para plink.');
            }

            $parts[] = '-m ' . escapeshellarg($scriptPath);
            $parts[] = escapeshellarg($host);
            $command = implode(' ', $parts) . ' 2>&1';
            $result = mgk_exec_shell_command($command);
            @unlink($scriptPath);

            return $result;
        };

        $persistDetectedHostKey = function ($detectedHostKey) use (&$plinkHostKeyCache, $cacheKey, $device) {
            $detectedHostKey = trim((string) $detectedHostKey);
            if ($detectedHostKey === '') {
                return;
            }

            $plinkHostKeyCache[$cacheKey] = $detectedHostKey;

            if (isset($device['id']) && trim((string) $device['id']) !== '') {
                $deviceId = (string) $device['id'];
                $devices = mgk_get_devices();
                $deviceIndex = mgk_find_device_index($devices, $deviceId);
                if ($deviceIndex >= 0) {
                    $devices[$deviceIndex]['ssh_hostkey'] = $detectedHostKey;
                    $devices[$deviceIndex]['updated_at'] = mgk_now_iso();
                    mgk_save_devices($devices);
                }
            }
        };

        $result = $runPlink($baseParts);
        if ($result['ok']) {
            return array('ok' => true, 'output' => $result['output']);
        }

        $shouldRetryWithDetectedHostKey = $configuredHostKey === '';
        if (!$shouldRetryWithDetectedHostKey) {
            $resultOutputLower = strtolower(isset($result['output']) ? (string) $result['output'] : '');
            if (
                strpos($resultOutputLower, 'host key not in manually configured list') !== false ||
                strpos($resultOutputLower, 'host key did not match') !== false ||
                strpos($resultOutputLower, 'host key verification failed') !== false ||
                strpos($resultOutputLower, 'host key is not cached for this server') !== false ||
                strpos($resultOutputLower, 'host key does not match') !== false ||
                strpos($resultOutputLower, 'potential security breach') !== false ||
                strpos($resultOutputLower, 'key fingerprint is') !== false
            ) {
                $shouldRetryWithDetectedHostKey = true;
            }
        }

        if ($shouldRetryWithDetectedHostKey) {
            $detectedHostKey = mgk_extract_plink_hostkey(isset($result['output']) ? $result['output'] : '');
            if ($detectedHostKey !== '' && ($configuredHostKey === '' || strcasecmp($configuredHostKey, $detectedHostKey) !== 0)) {
                $retryParts = $baseParts;
                $retryParts[] = '-hostkey ' . escapeshellarg($detectedHostKey);
                $retry = $runPlink($retryParts);
                if ($retry['ok']) {
                    $persistDetectedHostKey($detectedHostKey);
                    return array('ok' => true, 'output' => $retry['output']);
                }

                $result = $retry;
            } elseif ($configuredHostKey !== '') {
                $retryParts = array();
                for ($partIdx = 0; $partIdx < count($baseParts); $partIdx++) {
                    $part = (string) $baseParts[$partIdx];
                    if (stripos($part, '-hostkey ') === 0) {
                        continue;
                    }
                    $retryParts[] = $part;
                }

                $retry = $runPlink($retryParts);
                if ($retry['ok']) {
                    if (isset($device['id']) && trim((string) $device['id']) !== '') {
                        $deviceId = (string) $device['id'];
                        $devices = mgk_get_devices();
                        $deviceIndex = mgk_find_device_index($devices, $deviceId);
                        if ($deviceIndex >= 0) {
                            $devices[$deviceIndex]['ssh_hostkey'] = '';
                            $devices[$deviceIndex]['updated_at'] = mgk_now_iso();
                            mgk_save_devices($devices);
                        }
                    }
                    return array('ok' => true, 'output' => $retry['output']);
                }

                $detectedAfterHostkeyRemoval = mgk_extract_plink_hostkey(isset($retry['output']) ? $retry['output'] : '');
                if ($detectedAfterHostkeyRemoval !== '' && strcasecmp($configuredHostKey, $detectedAfterHostkeyRemoval) !== 0) {
                    $retryWithDetectedParts = $retryParts;
                    $retryWithDetectedParts[] = '-hostkey ' . escapeshellarg($detectedAfterHostkeyRemoval);
                    $retryWithDetected = $runPlink($retryWithDetectedParts);
                    if ($retryWithDetected['ok']) {
                        $persistDetectedHostKey($detectedAfterHostkeyRemoval);
                        return array('ok' => true, 'output' => $retryWithDetected['output']);
                    }

                    $result = $retryWithDetected;
                } else {
                    $result = $retry;
                }
            }
        }

        return array(
            'ok' => false,
            'error' => 'Fallo comando plink (' . $result['exit_code'] . '): ' . mgk_compact_text($result['output'], 180)
        );
    }

    $sshPath = mgk_find_binary(array(
        'ssh',
        'C:\\Windows\\Sysnative\\OpenSSH\\ssh.exe',
        'C:\\Windows\\System32\\OpenSSH\\ssh.exe',
        'C:\\Windows\\SysWOW64\\OpenSSH\\ssh.exe'
    ));
    if ($sshPath === '') {
        return array('ok' => false, 'error' => 'No se encontro cliente SSH (ssh/plink) en el servidor.');
    }

    if ($password !== '' && $privateKeyPath === '') {
        return array('ok' => false, 'error' => 'Con ssh cli se requiere llave o agente. Para password instala PuTTY/plink o extension ssh2.');
    }

    $nullDevice = mgk_is_windows_runtime() ? 'NUL' : '/dev/null';
    $parts = array(
        escapeshellarg($sshPath),
        '-p ' . (int) $port,
        '-o ConnectTimeout=4',
        '-o BatchMode=yes',
        '-o StrictHostKeyChecking=no',
        '-o UserKnownHostsFile=' . escapeshellarg($nullDevice),
        '-o NumberOfPasswordPrompts=0',
        '-l ' . escapeshellarg($username)
    );

    if ($privateKeyPath !== '') {
        $parts[] = '-i ' . escapeshellarg($privateKeyPath);
    }

    $parts[] = escapeshellarg($host);
    $parts[] = escapeshellarg($remoteCommand);

    $command = implode(' ', $parts) . ' 2>&1';
    $result = mgk_exec_shell_command($command);
    if (!$result['ok']) {
        return array(
            'ok' => false,
            'error' => 'Fallo comando ssh (' . $result['exit_code'] . '): ' . mgk_compact_text($result['output'], 180)
        );
    }

    return array('ok' => true, 'output' => $result['output']);
}

function mgk_ssh_exec_command($device, $remoteCommand) {
    $cliError = '';
    $extensionError = '';

    // Prefer CLI first (plink/ssh) because ssh2 extension retries can add significant latency.
    $cliResult = mgk_ssh_exec_with_cli($device, $remoteCommand);
    if (isset($cliResult['ok']) && $cliResult['ok']) {
        return $cliResult;
    }
    if (isset($cliResult['error']) && trim((string) $cliResult['error']) !== '') {
        $cliError = trim((string) $cliResult['error']);
        $cliLower = strtolower($cliError);
        $shouldTryExtension = strpos($cliLower, 'no se encontro cliente ssh') !== false
            || strpos($cliLower, 'se requiere llave o agente') !== false;
        if (!$shouldTryExtension) {
            return array('ok' => false, 'error' => $cliError);
        }
    }

    $extensionResult = mgk_ssh_exec_with_extension($device, $remoteCommand);
    if (isset($extensionResult['ok']) && $extensionResult['ok']) {
        return $extensionResult;
    }
    if (isset($extensionResult['error']) && trim((string) $extensionResult['error']) !== '') {
        $extensionError = trim((string) $extensionResult['error']);
    }

    if ($cliError !== '') {
        return array('ok' => false, 'error' => $cliError);
    }
    if ($extensionError !== '') {
        return array('ok' => false, 'error' => $extensionError);
    }

    return array('ok' => false, 'error' => 'No se pudo ejecutar consulta SSH en el servidor.');
}

function mgk_pull_ssh_metrics($device) {
    $targetOs = mgk_normalize_ssh_os(isset($device['ssh_os']) ? $device['ssh_os'] : 'auto');
    $configuredIisPorts = mgk_get_expected_iis_ports($device);
    $configuredJavaPorts = mgk_get_expected_java_ports($device);
    $customServiceChecks = mgk_get_custom_service_checks($device);
    $customServicePorts = array();
    for ($svcIdx = 0; $svcIdx < count($customServiceChecks); $svcIdx++) {
        if (isset($customServiceChecks[$svcIdx]['port']) && is_numeric($customServiceChecks[$svcIdx]['port'])) {
            $customServicePorts[] = (int) $customServiceChecks[$svcIdx]['port'];
        }
    }
    $customServicePorts = mgk_parse_port_list($customServicePorts, array());
    $defaultLinuxJavaPorts = array(8080);

    $attemptOrder = array();
    if ($targetOs === 'auto') {
        $attemptOrder = array('linux', 'windows');
    } else {
        $attemptOrder = array($targetOs);
    }

    $errors = array();

    for ($i = 0; $i < count($attemptOrder); $i++) {
        $os = $attemptOrder[$i];
        $iisWatchPorts = array();
        $javaWatchPorts = array();
        $serviceWatchPorts = $customServicePorts;
        if ($os === 'windows') {
            $iisWatchPorts = mgk_parse_port_list(array_merge($configuredIisPorts, array(80, 443)), array(80, 443));
        }
        if ($os === 'linux') {
            $javaWatchPorts = count($configuredJavaPorts) > 0 ? $configuredJavaPorts : $defaultLinuxJavaPorts;
        }

        $remoteCommand = $os === 'windows'
            ? mgk_build_windows_ssh_command($iisWatchPorts, $serviceWatchPorts)
            : mgk_build_linux_ssh_command($javaWatchPorts, $serviceWatchPorts);

        $execResult = mgk_ssh_exec_command($device, $remoteCommand);
        if (!$execResult['ok']) {
            $errors[] = $os . ': ' . $execResult['error'];
            continue;
        }

        $parsed = mgk_parse_metrics_text(isset($execResult['output']) ? $execResult['output'] : '');
        if ($parsed['ok']) {
            $checks = isset($parsed['checks']) && is_array($parsed['checks'])
                ? $parsed['checks']
                : array(
                    'iis' => null,
                    'iis_ports' => array(),
                    'java_port_ok' => null,
                    'java_port' => null,
                    'java_ports' => array(),
                    'service_ports' => array()
                );

            if (!isset($checks['iis_ports']) || !is_array($checks['iis_ports'])) {
                $checks['iis_ports'] = array();
            }
            if (!isset($checks['java_ports']) || !is_array($checks['java_ports'])) {
                $checks['java_ports'] = array();
            }
            if (!isset($checks['service_ports']) || !is_array($checks['service_ports'])) {
                $checks['service_ports'] = array();
            }

            for ($iisIdx = 0; $iisIdx < count($iisWatchPorts); $iisIdx++) {
                $iisPort = (int) $iisWatchPorts[$iisIdx];
                $iisKey = (string) $iisPort;
                if (!array_key_exists($iisKey, $checks['iis_ports'])) {
                    $checks['iis_ports'][$iisKey] = null;
                }
            }

            for ($javaIdx = 0; $javaIdx < count($javaWatchPorts); $javaIdx++) {
                $javaPort = (int) $javaWatchPorts[$javaIdx];
                $javaKey = (string) $javaPort;
                if (!array_key_exists($javaKey, $checks['java_ports'])) {
                    $checks['java_ports'][$javaKey] = null;
                }
            }
            for ($svcPortIdx = 0; $svcPortIdx < count($serviceWatchPorts); $svcPortIdx++) {
                $svcPort = (int) $serviceWatchPorts[$svcPortIdx];
                $svcKey = (string) $svcPort;
                if (!array_key_exists($svcKey, $checks['service_ports'])) {
                    $checks['service_ports'][$svcKey] = null;
                }
            }

            if (count($javaWatchPorts) > 0 && (!isset($checks['java_port']) || !is_numeric($checks['java_port']))) {
                $checks['java_port'] = (int) $javaWatchPorts[0];
            }
            if (isset($checks['java_port']) && is_numeric($checks['java_port'])) {
                $legacyJavaPort = (int) $checks['java_port'];
                if ($legacyJavaPort >= 1 && $legacyJavaPort <= 65535) {
                    $legacyJavaKey = (string) $legacyJavaPort;
                    if (array_key_exists($legacyJavaKey, $checks['java_ports'])) {
                        if (!array_key_exists('java_port_ok', $checks) || $checks['java_port_ok'] === null || $checks['java_port_ok'] === '') {
                            $checks['java_port_ok'] = $checks['java_ports'][$legacyJavaKey];
                        }
                    } elseif (array_key_exists('java_port_ok', $checks)) {
                        $checks['java_ports'][$legacyJavaKey] = mgk_boolish_to_int($checks['java_port_ok'], null);
                    }
                }
            }

            return array(
                'ok' => true,
                'metrics' => $parsed['metrics'],
                'checks' => $checks,
                'os_used' => $os
            );
        }

        $errors[] = $os . ': ' . $parsed['error'];
    }

    if (count($errors) === 0) {
        $errors[] = 'No se pudo completar la consulta SSH.';
    }

    return array('ok' => false, 'error' => 'pull_ssh fallo. ' . implode(' | ', $errors));
}
