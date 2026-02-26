<?php
require __DIR__ . '/bootstrap.php';

mgk_init_storage();

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method === 'GET') {
    $devices = mgk_get_devices();
    mgk_sort_devices($devices);

    $publicDevices = array();
    foreach ($devices as $device) {
        $publicDevices[] = mgk_public_device($device);
    }

    mgk_ok(array('devices' => $publicDevices, 'count' => count($publicDevices)));
}

if ($method !== 'POST') {
    mgk_error('Metodo no permitido.', 405, array());
}

$payload = mgk_read_json_body();
$action = strtolower(trim((string) mgk_get_nested($payload, array('action'), 'upsert')));
$devices = mgk_get_devices();

if ($action === 'upsert') {
    $deviceInput = isset($payload['device']) && is_array($payload['device']) ? $payload['device'] : $payload;
    $targetId = mgk_clean_text(mgk_get_nested($deviceInput, array('id'), ''), 80);
    $existingIndex = $targetId !== '' ? mgk_find_device_index($devices, $targetId) : -1;

    $existingDevice = $existingIndex >= 0 ? $devices[$existingIndex] : null;
    list($ok, $prepared) = mgk_prepare_device($deviceInput, $existingDevice);
    if (!$ok) {
        mgk_error($prepared, 422, array());
    }

    if ($existingIndex >= 0) {
        $devices[$existingIndex] = $prepared;
    } else {
        $devices[] = $prepared;
    }

    mgk_sort_devices($devices);
    if (!mgk_save_devices($devices)) {
        mgk_error('No se pudo guardar el equipo.', 500, array());
    }

    mgk_ok(array('device' => mgk_public_device($prepared)));
}

if ($action === 'delete') {
    $deviceId = mgk_clean_text(mgk_get_nested($payload, array('id', 'device_id'), ''), 80);
    if ($deviceId === '') {
        mgk_error('Debes enviar el id del equipo para eliminar.', 422, array());
    }

    $index = mgk_find_device_index($devices, $deviceId);
    if ($index < 0) {
        mgk_error('Equipo no encontrado.', 404, array());
    }

    array_splice($devices, $index, 1);
    if (!mgk_save_devices($devices)) {
        mgk_error('No se pudo eliminar el equipo.', 500, array());
    }

    $metricsStore = mgk_get_metrics_store();
    if (isset($metricsStore[$deviceId])) {
        unset($metricsStore[$deviceId]);
        mgk_save_metrics_store($metricsStore);
    }
    $eventsStore = mgk_get_events_store();
    if (isset($eventsStore['by_device'][$deviceId])) {
        unset($eventsStore['by_device'][$deviceId]);
        mgk_save_events_store($eventsStore);
    }
    $historyStore = mgk_get_history_store();
    if (isset($historyStore['by_device'][$deviceId])) {
        unset($historyStore['by_device'][$deviceId]);
        mgk_save_history_store($historyStore);
    }

    mgk_ok(array('deleted_id' => $deviceId));
}

if ($action === 'bulk_delete') {
    $ids = isset($payload['ids']) && is_array($payload['ids']) ? $payload['ids'] : array();
    $normalizedIds = array();
    foreach ($ids as $id) {
        $clean = mgk_clean_text($id, 80);
        if ($clean !== '') {
            $normalizedIds[$clean] = true;
        }
    }

    if (count($normalizedIds) === 0) {
        mgk_error('No se recibieron ids para borrado masivo.', 422, array());
    }

    $remaining = array();
    $deletedIds = array();
    foreach ($devices as $device) {
        $deviceId = isset($device['id']) ? $device['id'] : '';
        if (isset($normalizedIds[$deviceId])) {
            $deletedIds[] = $deviceId;
        } else {
            $remaining[] = $device;
        }
    }

    if (!mgk_save_devices($remaining)) {
        mgk_error('No se pudo completar el borrado masivo.', 500, array());
    }

    if (count($deletedIds) > 0) {
        $metricsStore = mgk_get_metrics_store();
        $eventsStore = mgk_get_events_store();
        $historyStore = mgk_get_history_store();
        foreach ($deletedIds as $deletedId) {
            if (isset($metricsStore[$deletedId])) {
                unset($metricsStore[$deletedId]);
            }
            if (isset($eventsStore['by_device'][$deletedId])) {
                unset($eventsStore['by_device'][$deletedId]);
            }
            if (isset($historyStore['by_device'][$deletedId])) {
                unset($historyStore['by_device'][$deletedId]);
            }
        }
        mgk_save_metrics_store($metricsStore);
        mgk_save_events_store($eventsStore);
        mgk_save_history_store($historyStore);
    }

    mgk_ok(array('deleted_count' => count($deletedIds), 'deleted_ids' => array_values($deletedIds)));
}

if ($action === 'bulk_upsert_text') {
    $text = (string) mgk_get_nested($payload, array('text', 'csv', 'bulk_text'), '');
    if (trim($text) === '') {
        mgk_error('No se recibio texto para la carga masiva.', 422, array());
    }

    list($rows, $parseErrors) = mgk_parse_bulk_rows($text);
    if (count($rows) === 0) {
        mgk_error('No se encontro ninguna fila valida para procesar.', 422, array('details' => $parseErrors));
    }

    $processed = 0;
    $created = 0;
    $updated = 0;
    $validationErrors = array();

    foreach ($rows as $rowInfo) {
        $lineNo = $rowInfo['line'];
        $item = $rowInfo['item'];

        $targetId = mgk_clean_text(mgk_get_nested($item, array('id'), ''), 80);
        $targetHost = mgk_clean_text(mgk_get_nested($item, array('host', 'ip', 'hostname'), ''), 120);

        $existingIndex = -1;
        if ($targetId !== '') {
            $existingIndex = mgk_find_device_index($devices, $targetId);
        }
        if ($existingIndex < 0 && $targetHost !== '') {
            $existingIndex = mgk_find_device_index_by_host($devices, $targetHost);
        }

        $existingDevice = $existingIndex >= 0 ? $devices[$existingIndex] : null;
        list($ok, $prepared) = mgk_prepare_device($item, $existingDevice);
        if (!$ok) {
            $validationErrors[] = 'Linea ' . $lineNo . ': ' . $prepared;
            continue;
        }

        if ($existingIndex >= 0) {
            $devices[$existingIndex] = $prepared;
            $updated++;
        } else {
            $devices[] = $prepared;
            $created++;
        }

        $processed++;
    }

    if ($processed > 0) {
        mgk_sort_devices($devices);
        if (!mgk_save_devices($devices)) {
            mgk_error('No se pudieron guardar los equipos de la carga masiva.', 500, array());
        }
    }

    mgk_ok(array(
        'processed' => $processed,
        'created' => $created,
        'updated' => $updated,
        'parse_errors' => $parseErrors,
        'validation_errors' => $validationErrors
    ));
}

if ($action === 'regenerate_token') {
    $deviceId = mgk_clean_text(mgk_get_nested($payload, array('id', 'device_id'), ''), 80);
    if ($deviceId === '') {
        mgk_error('Debes enviar el id del equipo para regenerar token.', 422, array());
    }

    $index = mgk_find_device_index($devices, $deviceId);
    if ($index < 0) {
        mgk_error('Equipo no encontrado.', 404, array());
    }

    $devices[$index]['token'] = mgk_random_token(32);
    $devices[$index]['updated_at'] = mgk_now_iso();

    if (!mgk_save_devices($devices)) {
        mgk_error('No se pudo regenerar el token.', 500, array());
    }

    mgk_ok(array('device' => mgk_public_device($devices[$index])));
}

mgk_error('Accion no reconocida.', 400, array());

function mgk_parse_bulk_rows($text) {
    $rows = array();
    $errors = array();

    $lines = preg_split('/\r\n|\n|\r/', (string) $text);
    $headerSkipped = false;

    for ($i = 0; $i < count($lines); $i++) {
        $lineNumber = $i + 1;
        $line = trim($lines[$i]);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $delimiter = ',';
        if (strpos($line, ';') !== false) {
            $delimiter = ';';
        } elseif (strpos($line, "\t") !== false) {
            $delimiter = "\t";
        }

        $cols = str_getcsv($line, $delimiter);
        $cols = array_map('trim', $cols);

        if (!$headerSkipped) {
            $first = isset($cols[0]) ? strtolower($cols[0]) : '';
            if ($first === 'name' || $first === 'nombre' || $first === 'equipo') {
                $headerSkipped = true;
                continue;
            }
            $headerSkipped = true;
        }

        if (count($cols) < 2) {
            $errors[] = 'Linea ' . $lineNumber . ': se requieren al menos nombre y host.';
            continue;
        }

        $item = array(
            'name' => isset($cols[0]) ? $cols[0] : '',
            'host' => isset($cols[1]) ? $cols[1] : '',
            'group' => isset($cols[2]) ? $cols[2] : '',
            'mode' => isset($cols[3]) ? $cols[3] : 'push',
            'pull_url' => isset($cols[4]) ? $cols[4] : '',
            'username' => isset($cols[5]) ? $cols[5] : '',
            'password' => isset($cols[6]) ? $cols[6] : '',
            'cpu_warning' => isset($cols[7]) ? $cols[7] : '',
            'cpu_critical' => isset($cols[8]) ? $cols[8] : '',
            'ram_warning' => isset($cols[9]) ? $cols[9] : '',
            'ram_critical' => isset($cols[10]) ? $cols[10] : '',
            'disk_warning' => isset($cols[11]) ? $cols[11] : '',
            'disk_critical' => isset($cols[12]) ? $cols[12] : '',
            'token' => isset($cols[13]) ? $cols[13] : ''
        );

        if (isset($cols[14]) && $cols[14] !== '') {
            $item['id'] = $cols[14];
        }
        if (isset($cols[15]) && $cols[15] !== '') {
            $item['ssh_port'] = $cols[15];
        }
        if (isset($cols[16]) && $cols[16] !== '') {
            $item['ssh_os'] = $cols[16];
        }
        if (isset($cols[17]) && $cols[17] !== '') {
            $item['ssh_key_path'] = $cols[17];
        }
        if (isset($cols[18]) && $cols[18] !== '') {
            $item['poll_interval_seconds'] = $cols[18];
        }
        if (isset($cols[19]) && $cols[19] !== '') {
            $item['expect_iis'] = $cols[19];
        }
        if (isset($cols[20]) && $cols[20] !== '') {
            $item['expect_java_port'] = $cols[20];
        }
        if (isset($cols[21]) && $cols[21] !== '') {
            $item['expect_iis_ports'] = $cols[21];
        }
        if (isset($cols[22]) && $cols[22] !== '') {
            $item['expect_java_ports'] = $cols[22];
        }
        if (isset($cols[23]) && $cols[23] !== '') {
            $item['service_checks'] = $cols[23];
        }

        $rows[] = array('line' => $lineNumber, 'item' => $item);
    }

    return array($rows, $errors);
}
