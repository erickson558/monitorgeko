<?php
require __DIR__ . '/bootstrap.php';

mgk_init_storage();

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method !== 'GET') {
    mgk_error('Metodo no permitido.', 405, array());
}

$action = strtolower(trim((string) mgk_get_nested($_GET, array('action'), 'events')));
$deviceId = mgk_clean_text(mgk_get_nested($_GET, array('device_id', 'id'), ''), 80);

if ($action === 'ping') {
    mgk_ok(array('message' => 'ok'));
}

if ($deviceId === '') {
    mgk_error('Debes enviar device_id.', 422, array());
}

$devices = mgk_get_devices();
$deviceIndex = mgk_find_device_index($devices, $deviceId);
if ($deviceIndex < 0) {
    mgk_error('Equipo no encontrado.', 404, array('device_id' => $deviceId));
}

$device = $devices[$deviceIndex];
$metricsStore = mgk_get_metrics_store();
$metricPacket = isset($metricsStore[$deviceId]) && is_array($metricsStore[$deviceId])
    ? $metricsStore[$deviceId]
    : array();

if ($action === 'events') {
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 200;
    if ($limit < 20) {
        $limit = 20;
    }
    if ($limit > 2000) {
        $limit = 2000;
    }

    $events = mgk_get_device_events($deviceId, $limit);
    mgk_ok(array(
        'device' => mgk_public_device($device),
        'events' => $events,
        'count' => count($events)
    ));
}

if ($action === 'events_download') {
    $events = mgk_get_device_events($deviceId, 2000);
    $csv = mgk_events_to_csv($events);
    $safeDevice = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $deviceId);
    $downloadName = 'monitorapp-events-' . $safeDevice . '-' . gmdate('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    echo $csv;
    exit;
}

if ($action === 'history') {
    $hours = isset($_GET['hours']) && is_numeric($_GET['hours']) ? (int) $_GET['hours'] : 168;
    $points = mgk_get_device_hourly_history($deviceId, $hours);
    mgk_ok(array(
        'device' => mgk_public_device($device),
        'hours' => $hours,
        'points' => $points,
        'count' => count($points)
    ));
}

if ($action === 'linux_logs_list') {
    $directory = trim((string) mgk_get_nested($_GET, array('directory', 'dir'), '/opt/spring/bancamovil/logs'));
    if ($directory === '') {
        $directory = '/opt/spring/bancamovil/logs';
    }

    if (!mgk_is_linux_ssh_device($device, $metricPacket)) {
        mgk_error('El equipo no esta configurado como Linux por SSH.', 422, array('device_id' => $deviceId));
    }

    $listResult = mgk_list_linux_log_files($device, $directory);
    if (!$listResult['ok']) {
        mgk_error('No se pudo consultar directorio remoto: ' . $listResult['error'], 502, array());
    }

    mgk_ok(array(
        'device' => mgk_public_device($device),
        'directory' => isset($listResult['directory']) ? $listResult['directory'] : $directory,
        'missing_dir' => isset($listResult['missing_dir']) ? (bool) $listResult['missing_dir'] : false,
        'files' => isset($listResult['files']) && is_array($listResult['files']) ? $listResult['files'] : array()
    ));
}

if ($action === 'linux_log_download') {
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $directory = trim((string) mgk_get_nested($_GET, array('directory', 'dir'), '/opt/spring/bancamovil/logs'));
    if ($directory === '') {
        $directory = '/opt/spring/bancamovil/logs';
    }

    $fileName = mgk_sanitize_remote_log_filename(mgk_get_nested($_GET, array('file', 'filename', 'name'), ''));
    if ($fileName === '') {
        mgk_error('Debes indicar file para descargar.', 422, array());
    }

    if (!mgk_is_linux_ssh_device($device, $metricPacket)) {
        mgk_error('El equipo no esta configurado como Linux por SSH.', 422, array('device_id' => $deviceId));
    }

    $listResult = mgk_list_linux_log_files($device, $directory);
    if (!$listResult['ok']) {
        mgk_error('No se pudo consultar directorio remoto: ' . $listResult['error'], 502, array());
    }

    $targetFile = null;
    $files = isset($listResult['files']) && is_array($listResult['files']) ? $listResult['files'] : array();
    for ($i = 0; $i < count($files); $i++) {
        $candidate = is_array($files[$i]) ? $files[$i] : array();
        if (isset($candidate['name']) && (string) $candidate['name'] === $fileName) {
            $targetFile = $candidate;
            break;
        }
    }
    if (!is_array($targetFile)) {
        mgk_error('El archivo no existe en el directorio remoto.', 404, array('file' => $fileName));
    }

    $maxDownloadBytes = 250 * 1024 * 1024;
    $fileSize = isset($targetFile['size']) && is_numeric($targetFile['size']) ? (int) $targetFile['size'] : 0;
    if ($fileSize > $maxDownloadBytes) {
        mgk_error('El archivo supera 250 MB. Ajusta el log remoto antes de descargar.', 413, array('size' => $fileSize));
    }

    $downloadResult = mgk_read_linux_log_file_base64($device, $directory, $fileName);
    if (!$downloadResult['ok']) {
        mgk_error('No se pudo descargar archivo remoto: ' . $downloadResult['error'], 502, array());
    }

    $binary = base64_decode((string) $downloadResult['base64'], true);
    if ($binary === false) {
        mgk_error('No se pudo decodificar el archivo remoto.', 500, array());
    }

    $safeDeviceName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', isset($device['name']) ? $device['name'] : $deviceId);
    $downloadName = $safeDeviceName . '-' . $fileName;

    header('Content-Type: application/octet-stream');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Length: ' . strlen($binary));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    echo $binary;
    exit;
}

if ($action === 'windows_iis_logs_list') {
    $directory = trim((string) mgk_get_nested($_GET, array('directory', 'dir'), mgk_windows_iis_default_log_directory()));
    if ($directory === '') {
        $directory = mgk_windows_iis_default_log_directory();
    }

    if (!mgk_is_windows_ssh_device($device, $metricPacket)) {
        mgk_error('El equipo no esta configurado como Windows por SSH.', 422, array('device_id' => $deviceId));
    }

    $listResult = mgk_list_windows_iis_log_files($device, $directory);
    if (!$listResult['ok']) {
        mgk_error('No se pudo consultar directorio IIS remoto: ' . $listResult['error'], 502, array());
    }

    mgk_ok(array(
        'device' => mgk_public_device($device),
        'directory' => isset($listResult['directory']) ? $listResult['directory'] : $directory,
        'missing_dir' => isset($listResult['missing_dir']) ? (bool) $listResult['missing_dir'] : false,
        'files' => isset($listResult['files']) && is_array($listResult['files']) ? $listResult['files'] : array()
    ));
}

if ($action === 'windows_iis_log_download') {
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $directory = trim((string) mgk_get_nested($_GET, array('directory', 'dir'), mgk_windows_iis_default_log_directory()));
    if ($directory === '') {
        $directory = mgk_windows_iis_default_log_directory();
    }

    $fileName = mgk_sanitize_remote_log_relative_path(mgk_get_nested($_GET, array('file', 'filename', 'name'), ''));
    if ($fileName === '') {
        mgk_error('Debes indicar file para descargar.', 422, array());
    }

    if (!mgk_is_windows_ssh_device($device, $metricPacket)) {
        mgk_error('El equipo no esta configurado como Windows por SSH.', 422, array('device_id' => $deviceId));
    }

    $listResult = mgk_list_windows_iis_log_files($device, $directory);
    if (!$listResult['ok']) {
        mgk_error('No se pudo consultar directorio IIS remoto: ' . $listResult['error'], 502, array());
    }

    $targetFile = null;
    $files = isset($listResult['files']) && is_array($listResult['files']) ? $listResult['files'] : array();
    for ($i = 0; $i < count($files); $i++) {
        $candidate = is_array($files[$i]) ? $files[$i] : array();
        if (isset($candidate['name']) && (string) $candidate['name'] === $fileName) {
            $targetFile = $candidate;
            break;
        }
    }
    if (!is_array($targetFile)) {
        mgk_error('El archivo no existe en el directorio IIS remoto.', 404, array('file' => $fileName));
    }

    $maxDownloadBytes = 250 * 1024 * 1024;
    $fileSize = isset($targetFile['size']) && is_numeric($targetFile['size']) ? (int) $targetFile['size'] : 0;
    if ($fileSize > $maxDownloadBytes) {
        mgk_error('El archivo IIS supera 250 MB. Ajusta el archivo remoto antes de descargar.', 413, array('size' => $fileSize));
    }

    $downloadResult = mgk_read_windows_log_file_base64($device, $directory, $fileName);
    if (!$downloadResult['ok']) {
        mgk_error('No se pudo descargar archivo IIS remoto: ' . $downloadResult['error'], 502, array());
    }

    $base64Payload = isset($downloadResult['base64']) ? (string) $downloadResult['base64'] : '';
    if ($base64Payload === '') {
        mgk_error('No se recibio contenido base64 del archivo IIS remoto.', 500, array());
    }

    $binary = base64_decode($base64Payload, true);
    if ($binary === false) {
        $base64Payload = preg_replace('/\s+/', '', $base64Payload);
        if (!is_string($base64Payload) || $base64Payload === '') {
            mgk_error('No se pudo procesar el archivo IIS remoto.', 500, array());
        }
        $binary = base64_decode($base64Payload, true);
    }
    if ($binary === false) {
        mgk_error('No se pudo decodificar el archivo IIS remoto.', 500, array());
    }
    unset($downloadResult);
    unset($base64Payload);

    $safeDeviceName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', isset($device['name']) ? $device['name'] : $deviceId);
    $safeFileName = str_replace(array('\\', '/'), '-', $fileName);
    $downloadName = $safeDeviceName . '-' . $safeFileName;

    header('Content-Type: application/octet-stream');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Length: ' . strlen($binary));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    echo $binary;
    exit;
}

if ($action === 'linux_log_view') {
    $directory = trim((string) mgk_get_nested($_GET, array('directory', 'dir'), '/opt/spring/bancamovil/logs'));
    if ($directory === '') {
        $directory = '/opt/spring/bancamovil/logs';
    }
    $fileName = mgk_sanitize_remote_log_filename(mgk_get_nested($_GET, array('file', 'filename', 'name'), ''));
    if ($fileName === '') {
        mgk_error('Debes indicar file para visualizar.', 422, array());
    }
    $lineLimit = isset($_GET['lines']) && is_numeric($_GET['lines']) ? (int) $_GET['lines'] : 1000;
    if ($lineLimit < 0) {
        $lineLimit = 0;
    }
    if ($lineLimit > 5000) {
        $lineLimit = 5000;
    }
    $query = trim((string) mgk_get_nested($_GET, array('query', 'q', 'search'), ''));

    if (!mgk_is_linux_ssh_device($device, $metricPacket)) {
        mgk_error('El equipo no esta configurado como Linux por SSH.', 422, array('device_id' => $deviceId));
    }

    $viewResult = mgk_view_linux_log_text_base64($device, $directory, $fileName, $lineLimit, $query);
    if (!$viewResult['ok']) {
        mgk_error('No se pudo leer log remoto Linux: ' . $viewResult['error'], 502, array());
    }

    $text = base64_decode((string) $viewResult['base64'], true);
    if ($text === false) {
        mgk_error('No se pudo decodificar contenido del log Linux.', 500, array());
    }

    mgk_ok(array(
        'device' => mgk_public_device($device),
        'directory' => isset($viewResult['directory']) ? (string) $viewResult['directory'] : $directory,
        'file' => isset($viewResult['file']) ? (string) $viewResult['file'] : $fileName,
        'lines' => isset($viewResult['lines']) ? (int) $viewResult['lines'] : $lineLimit,
        'searched' => isset($viewResult['searched']) ? (bool) $viewResult['searched'] : ($query !== ''),
        'matches' => isset($viewResult['matches']) && is_numeric($viewResult['matches']) ? (int) $viewResult['matches'] : -1,
        'truncated' => isset($viewResult['truncated']) ? (bool) $viewResult['truncated'] : false,
        'preview_limit_bytes' => isset($viewResult['preview_limit_bytes']) && is_numeric($viewResult['preview_limit_bytes'])
            ? (int) $viewResult['preview_limit_bytes']
            : 0,
        'text' => (string) $text
    ));
}

if ($action === 'windows_iis_log_view') {
    $directory = trim((string) mgk_get_nested($_GET, array('directory', 'dir'), mgk_windows_iis_default_log_directory()));
    if ($directory === '') {
        $directory = mgk_windows_iis_default_log_directory();
    }
    $fileName = mgk_sanitize_remote_log_relative_path(mgk_get_nested($_GET, array('file', 'filename', 'name'), ''));
    if ($fileName === '') {
        mgk_error('Debes indicar file para visualizar.', 422, array());
    }
    $lineLimit = isset($_GET['lines']) && is_numeric($_GET['lines']) ? (int) $_GET['lines'] : 1000;
    if ($lineLimit < 0) {
        $lineLimit = 0;
    }
    if ($lineLimit > 5000) {
        $lineLimit = 5000;
    }
    $query = trim((string) mgk_get_nested($_GET, array('query', 'q', 'search'), ''));

    if (!mgk_is_windows_ssh_device($device, $metricPacket)) {
        mgk_error('El equipo no esta configurado como Windows por SSH.', 422, array('device_id' => $deviceId));
    }

    $viewResult = mgk_view_windows_log_text_base64($device, $directory, $fileName, $lineLimit, $query);
    if (!$viewResult['ok']) {
        mgk_error('No se pudo leer log IIS remoto: ' . $viewResult['error'], 502, array());
    }

    $text = base64_decode((string) $viewResult['base64'], true);
    if ($text === false) {
        mgk_error('No se pudo decodificar contenido del log IIS.', 500, array());
    }

    mgk_ok(array(
        'device' => mgk_public_device($device),
        'directory' => isset($viewResult['directory']) ? (string) $viewResult['directory'] : $directory,
        'file' => isset($viewResult['file']) ? (string) $viewResult['file'] : $fileName,
        'lines' => isset($viewResult['lines']) ? (int) $viewResult['lines'] : $lineLimit,
        'searched' => isset($viewResult['searched']) ? (bool) $viewResult['searched'] : ($query !== ''),
        'matches' => isset($viewResult['matches']) && is_numeric($viewResult['matches']) ? (int) $viewResult['matches'] : -1,
        'text' => (string) $text
    ));
}

mgk_error('Accion no reconocida.', 400, array('action' => $action));
