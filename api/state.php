<?php
require __DIR__ . '/bootstrap.php';

mgk_init_storage();

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method !== 'GET') {
    mgk_error('Metodo no permitido.', 405, array());
}

$devices = mgk_get_devices();
$metricsStore = mgk_get_metrics_store();
$settings = mgk_get_settings();

$uiPollMs = 500;
if (isset($_GET['ui_poll_ms']) && is_numeric($_GET['ui_poll_ms'])) {
    $uiPollMs = (int) $_GET['ui_poll_ms'];
}
if ($uiPollMs < 100) {
    $uiPollMs = 100;
}
if ($uiPollMs > 30000) {
    $uiPollMs = 30000;
}
$dashboardPollSeconds = (int) ceil($uiPollMs / 1000);
if ($dashboardPollSeconds < 1) {
    $dashboardPollSeconds = 1;
}

$pullParam = isset($_GET['pull']) ? strtolower(trim((string) $_GET['pull'])) : '1';
$pullEnabled = true;
if ($pullParam === '0' || $pullParam === 'false' || $pullParam === 'no' || $pullParam === 'off') {
    $pullEnabled = false;
}

$offlineAfterSecondsMin = 45;
$maxPullPerRequest = 4;
if (isset($_GET['max_pull']) && is_numeric($_GET['max_pull'])) {
    $maxPullPerRequest = (int) $_GET['max_pull'];
}
if ($maxPullPerRequest < 1) {
    $maxPullPerRequest = 1;
}
if ($maxPullPerRequest > 12) {
    $maxPullPerRequest = 12;
}
$pullAttempts = 0;
$metricsChanged = false;
$pullWindowSeconds = 2.2;
$pullStartTs = microtime(true);
if ($pullEnabled) {
    $nowTs = time();
    $pullCandidates = array();

    for ($i = 0; $i < count($devices); $i++) {
        $device = $devices[$i];
        $mode = isset($device['mode']) ? (string) $device['mode'] : '';
        if ($mode !== 'pull_http' && $mode !== 'pull_ssh') {
            continue;
        }

        if (isset($device['enabled']) && !$device['enabled']) {
            continue;
        }

        $deviceId = isset($device['id']) ? $device['id'] : '';
        if ($deviceId === '') {
            continue;
        }

        $currentPacket = isset($metricsStore[$deviceId]) && is_array($metricsStore[$deviceId])
            ? $metricsStore[$deviceId]
            : array();
        $retryAfterTs = isset($currentPacket['retry_after_ts']) ? (int) $currentPacket['retry_after_ts'] : 0;
        if ($retryAfterTs > $nowTs) {
            continue;
        }
        $ageReference = '';
        if (isset($currentPacket['last_attempt_at']) && trim((string) $currentPacket['last_attempt_at']) !== '') {
            $ageReference = $currentPacket['last_attempt_at'];
        } elseif (isset($currentPacket['updated_at'])) {
            $ageReference = $currentPacket['updated_at'];
        }

        $age = mgk_age_seconds($ageReference);
        $devicePollRaw = isset($device['poll_interval_seconds'])
            ? mgk_normalize_poll_interval($device['poll_interval_seconds'], 0)
            : 0;
        $usesDashboardSync = $devicePollRaw < 2;
        $devicePollSeconds = $usesDashboardSync ? $dashboardPollSeconds : $devicePollRaw;
        if (!$usesDashboardSync && $age !== null && $age < $devicePollSeconds) {
            continue;
        }

        $pullCandidates[] = array(
            'device' => $device,
            'device_id' => $deviceId,
            'mode' => $mode,
            'age_score' => ($age === null) ? 1000000000 : (int) $age,
            'poll_seconds' => $devicePollSeconds
        );
    }

    if (count($pullCandidates) > 1) {
        usort($pullCandidates, function ($a, $b) {
            if ($a['age_score'] === $b['age_score']) {
                return strcmp($a['device_id'], $b['device_id']);
            }
            return ($a['age_score'] > $b['age_score']) ? -1 : 1;
        });
    }

    for ($candidateIdx = 0; $candidateIdx < count($pullCandidates); $candidateIdx++) {
        if ($pullAttempts > 0 && (microtime(true) - $pullStartTs) >= $pullWindowSeconds) {
            break;
        }

        if ($pullAttempts >= $maxPullPerRequest) {
            break;
        }

        $candidate = $pullCandidates[$candidateIdx];
        $device = $candidate['device'];
        $deviceId = $candidate['device_id'];
        $mode = $candidate['mode'];
        $devicePollSeconds = isset($candidate['poll_seconds']) ? (int) $candidate['poll_seconds'] : $dashboardPollSeconds;
        if ($devicePollSeconds < 1) {
            $devicePollSeconds = 1;
        }

        $pullAttempts++;
        if ($mode === 'pull_ssh') {
            $pullResult = mgk_pull_ssh_metrics($device);
        } else {
            $pullResult = mgk_pull_http_metrics($device);
        }

        if (!$pullResult['ok']) {
            if (!isset($metricsStore[$deviceId]) || !is_array($metricsStore[$deviceId])) {
                $metricsStore[$deviceId] = array();
            }
            $metricsStore[$deviceId]['source'] = $mode;
            $metricsStore[$deviceId]['last_error'] = $pullResult['error'];
            $metricsStore[$deviceId]['last_attempt_at'] = mgk_now_iso();
            if (!isset($metricsStore[$deviceId]['updated_at'])) {
                $metricsStore[$deviceId]['updated_at'] = '';
            }

            $previousFailures = isset($metricsStore[$deviceId]['consecutive_failures'])
                ? (int) $metricsStore[$deviceId]['consecutive_failures']
                : 0;
            $nextFailures = $previousFailures + 1;
            $metricsStore[$deviceId]['consecutive_failures'] = $nextFailures;

            $errorText = isset($pullResult['error']) ? (string) $pullResult['error'] : '';
            $backoffSeconds = $devicePollSeconds * $nextFailures;
            if ($backoffSeconds < 3) {
                $backoffSeconds = 3;
            }
            $isTimeoutError =
                stripos($errorText, 'timed out') !== false ||
                stripos($errorText, 'timeout') !== false ||
                stripos($errorText, 'network error') !== false;
            if (
                $isTimeoutError
            ) {
                if ($backoffSeconds < 180) {
                    $backoffSeconds = 180;
                }
                if ($backoffSeconds > 900) {
                    $backoffSeconds = 900;
                }
            } elseif ($backoffSeconds > 60) {
                $backoffSeconds = 60;
            }
            $metricsStore[$deviceId]['retry_after_ts'] = time() + $backoffSeconds;
            if ($nextFailures === 1) {
                mgk_append_device_event(
                    $device,
                    'pull_error',
                    'yellow',
                    'Error de consulta (' . $mode . '): ' . mgk_compact_text($pullResult['error'], 180),
                    array(
                        'mode' => $mode,
                        'failures' => $nextFailures
                    )
                );
            }
            $metricsChanged = true;
            continue;
        }

        $packet = isset($metricsStore[$deviceId]) && is_array($metricsStore[$deviceId])
            ? $metricsStore[$deviceId]
            : array();
        $previousPacket = $packet;
        $previousFailures = isset($packet['consecutive_failures'])
            ? (int) $packet['consecutive_failures']
            : 0;
        $hadPreviousSample = isset($previousPacket['updated_at']) && trim((string) $previousPacket['updated_at']) !== '';
        $offlineAfterSeconds = mgk_offline_after_seconds_for_poll($devicePollSeconds, $offlineAfterSecondsMin);
        $previousStatus = $hadPreviousSample
            ? mgk_build_device_status($device, $previousPacket, $offlineAfterSeconds)
            : array();
        $previousJavaPorts = isset($packet['proc_java_ports']) && is_array($packet['proc_java_ports'])
            ? mgk_normalize_port_status_map($packet['proc_java_ports'])
            : array();
        $previousJavaDownStreak = isset($packet['proc_java_ports_down_streak']) && is_array($packet['proc_java_ports_down_streak'])
            ? $packet['proc_java_ports_down_streak']
            : array();

        foreach ($pullResult['metrics'] as $metricKey => $metricValue) {
            $packet[$metricKey] = $metricValue;
        }

        if (isset($pullResult['checks']) && is_array($pullResult['checks'])) {
            if (array_key_exists('iis', $pullResult['checks'])) {
                if ($pullResult['checks']['iis'] === null || $pullResult['checks']['iis'] === '') {
                    unset($packet['proc_iis_up']);
                } else {
                    $packet['proc_iis_up'] = ((int) $pullResult['checks']['iis']) > 0 ? 1 : 0;
                }
            }
            if (array_key_exists('iis_ports', $pullResult['checks'])) {
                $iisPorts = mgk_normalize_port_status_map($pullResult['checks']['iis_ports']);
                if (count($iisPorts) > 0) {
                    $packet['proc_iis_ports'] = $iisPorts;
                } else {
                    unset($packet['proc_iis_ports']);
                }
            }

            if (array_key_exists('java_port', $pullResult['checks'])) {
                if (is_numeric($pullResult['checks']['java_port'])) {
                    $javaPort = (int) $pullResult['checks']['java_port'];
                    if ($javaPort >= 1 && $javaPort <= 65535) {
                        $packet['proc_java_port'] = $javaPort;
                    } else {
                        unset($packet['proc_java_port']);
                    }
                } else {
                    unset($packet['proc_java_port']);
                }
            }

            if (array_key_exists('java_port_ok', $pullResult['checks'])) {
                if ($pullResult['checks']['java_port_ok'] === null || $pullResult['checks']['java_port_ok'] === '') {
                    unset($packet['proc_java_port_ok']);
                } else {
                    $packet['proc_java_port_ok'] = ((int) $pullResult['checks']['java_port_ok']) > 0 ? 1 : 0;
                }
            }
            if (array_key_exists('java_ports', $pullResult['checks'])) {
                $javaPortsRaw = mgk_normalize_port_status_map($pullResult['checks']['java_ports']);
                if (count($javaPortsRaw) > 0) {
                    $javaPorts = array();
                    $javaDownStreak = array();

                    foreach ($javaPortsRaw as $javaPortKey => $javaPortStateRaw) {
                        $javaPortState = mgk_boolish_to_int($javaPortStateRaw, null);
                        $prevState = array_key_exists($javaPortKey, $previousJavaPorts)
                            ? mgk_boolish_to_int($previousJavaPorts[$javaPortKey], null)
                            : null;
                        $prevDownStreak = array_key_exists($javaPortKey, $previousJavaDownStreak) && is_numeric($previousJavaDownStreak[$javaPortKey])
                            ? max(0, (int) $previousJavaDownStreak[$javaPortKey])
                            : 0;

                        if ($javaPortState === 1) {
                            $javaPorts[$javaPortKey] = 1;
                            $javaDownStreak[$javaPortKey] = 0;
                            continue;
                        }

                        if ($javaPortState === 0) {
                            $nextDownStreak = $prevDownStreak + 1;
                            $javaDownStreak[$javaPortKey] = $nextDownStreak;
                            // Debounce one transient miss to reduce flapping when Java restarts briefly.
                            if ($prevState === 1 && $nextDownStreak < 2) {
                                $javaPorts[$javaPortKey] = 1;
                            } else {
                                $javaPorts[$javaPortKey] = 0;
                            }
                            continue;
                        }

                        $javaPorts[$javaPortKey] = null;
                        $javaDownStreak[$javaPortKey] = $prevDownStreak;
                    }

                    $packet['proc_java_ports'] = $javaPorts;
                    $packet['proc_java_ports_down_streak'] = $javaDownStreak;

                    if (isset($packet['proc_java_port']) && is_numeric($packet['proc_java_port'])) {
                        $legacyJavaPortKey = (string) ((int) $packet['proc_java_port']);
                        if (array_key_exists($legacyJavaPortKey, $javaPorts)) {
                            if ($javaPorts[$legacyJavaPortKey] === null) {
                                unset($packet['proc_java_port_ok']);
                            } else {
                                $packet['proc_java_port_ok'] = $javaPorts[$legacyJavaPortKey] > 0 ? 1 : 0;
                            }
                        }
                    }
                } else {
                    unset($packet['proc_java_ports']);
                    if (isset($packet['proc_java_ports_down_streak'])) {
                        unset($packet['proc_java_ports_down_streak']);
                    }
                }
            }
            if (array_key_exists('service_ports', $pullResult['checks'])) {
                $servicePorts = mgk_normalize_port_status_map($pullResult['checks']['service_ports']);
                if (count($servicePorts) > 0) {
                    $packet['proc_service_ports'] = $servicePorts;
                } else {
                    unset($packet['proc_service_ports']);
                }
            }
        }

        $packet['updated_at'] = mgk_now_iso();
        $packet['last_attempt_at'] = $packet['updated_at'];
        $packet['source'] = $mode;
        $packet['last_error'] = '';
        $packet['consecutive_failures'] = 0;
        if (isset($packet['retry_after_ts'])) {
            unset($packet['retry_after_ts']);
        }
        if (isset($pullResult['os_used']) && $pullResult['os_used'] !== '') {
            $packet['ssh_os_used'] = $pullResult['os_used'];
            $packet['source'] = $mode . '/' . $pullResult['os_used'];
        }

        $nextStatus = mgk_build_device_status($device, $packet, $offlineAfterSeconds);
        mgk_record_hourly_history($device, $packet, $nextStatus);
        if ($hadPreviousSample) {
            mgk_record_status_events($device, $previousStatus, $nextStatus, $mode);
        }
        if ($previousFailures > 0) {
            mgk_append_device_event(
                $device,
                'pull_recovered',
                'green',
                'Consulta recuperada por ' . $mode . ' tras ' . $previousFailures . ' fallos consecutivos.',
                array(
                    'mode' => $mode,
                    'failures' => $previousFailures
                )
            );
        }

        $metricsStore[$deviceId] = $packet;
        $metricsChanged = true;
    }

    if ($metricsChanged) {
        mgk_save_metrics_store($metricsStore);
    }
}

mgk_sort_devices($devices);

$outputDevices = array();
$summary = array(
    'total' => 0,
    'green' => 0,
    'yellow' => 0,
    'red' => 0,
    'offline' => 0
);

for ($i = 0; $i < count($devices); $i++) {
    $device = $devices[$i];
    $deviceId = isset($device['id']) ? $device['id'] : '';

    $metricPacket = isset($metricsStore[$deviceId]) && is_array($metricsStore[$deviceId])
        ? $metricsStore[$deviceId]
        : array();

    $ageSeconds = mgk_age_seconds(isset($metricPacket['updated_at']) ? $metricPacket['updated_at'] : '');
    $devicePollRaw = isset($device['poll_interval_seconds'])
        ? mgk_normalize_poll_interval($device['poll_interval_seconds'], 0)
        : 0;
    $effectivePollSeconds = $devicePollRaw >= 2 ? $devicePollRaw : $dashboardPollSeconds;
    if ($effectivePollSeconds < 1) {
        $effectivePollSeconds = 1;
    }
    $offlineAfterSeconds = $effectivePollSeconds * 3;
    if ($offlineAfterSeconds < $offlineAfterSecondsMin) {
        $offlineAfterSeconds = $offlineAfterSecondsMin;
    }

    $publicDevice = mgk_public_device($device);
    $publicDevice['effective_poll_interval_seconds'] = $effectivePollSeconds;
    $publicDevice['metrics'] = array(
        'cpu' => mgk_number_or_null(isset($metricPacket['cpu']) ? $metricPacket['cpu'] : null, 2),
        'ram' => mgk_number_or_null(isset($metricPacket['ram']) ? $metricPacket['ram'] : null, 2),
        'ram_used_bytes' => mgk_number_or_null(isset($metricPacket['ram_used_bytes']) ? $metricPacket['ram_used_bytes'] : null, 0),
        'ram_total_bytes' => mgk_number_or_null(isset($metricPacket['ram_total_bytes']) ? $metricPacket['ram_total_bytes'] : null, 0),
        'disk' => mgk_number_or_null(isset($metricPacket['disk']) ? $metricPacket['disk'] : null, 2),
        'disk_used_bytes' => mgk_number_or_null(isset($metricPacket['disk_used_bytes']) ? $metricPacket['disk_used_bytes'] : null, 0),
        'disk_total_bytes' => mgk_number_or_null(isset($metricPacket['disk_total_bytes']) ? $metricPacket['disk_total_bytes'] : null, 0),
        'network' => mgk_number_or_null(isset($metricPacket['network']) ? $metricPacket['network'] : null, 2),
        'temp' => mgk_number_or_null(isset($metricPacket['temp']) ? $metricPacket['temp'] : null, 2),
        'latency' => mgk_number_or_null(isset($metricPacket['latency']) ? $metricPacket['latency'] : null, 2),
        'updated_at' => isset($metricPacket['updated_at']) ? $metricPacket['updated_at'] : '',
        'age_seconds' => $ageSeconds,
        'source' => isset($metricPacket['source']) ? $metricPacket['source'] : '',
        'last_attempt_at' => isset($metricPacket['last_attempt_at']) ? $metricPacket['last_attempt_at'] : '',
        'ssh_os_used' => isset($metricPacket['ssh_os_used']) ? $metricPacket['ssh_os_used'] : '',
        'last_error' => isset($metricPacket['last_error']) ? $metricPacket['last_error'] : '',
        'proc_iis_up' => isset($metricPacket['proc_iis_up']) ? $metricPacket['proc_iis_up'] : null,
        'proc_iis_ports' => isset($metricPacket['proc_iis_ports']) && is_array($metricPacket['proc_iis_ports']) ? $metricPacket['proc_iis_ports'] : array(),
        'proc_java_port' => isset($metricPacket['proc_java_port']) ? $metricPacket['proc_java_port'] : null,
        'proc_java_port_ok' => isset($metricPacket['proc_java_port_ok']) ? $metricPacket['proc_java_port_ok'] : null,
        'proc_java_ports' => isset($metricPacket['proc_java_ports']) && is_array($metricPacket['proc_java_ports']) ? $metricPacket['proc_java_ports'] : array(),
        'proc_service_ports' => isset($metricPacket['proc_service_ports']) && is_array($metricPacket['proc_service_ports']) ? $metricPacket['proc_service_ports'] : array()
    );

    $publicDevice['status'] = mgk_build_device_status($device, $metricPacket, $offlineAfterSeconds);

    $overall = $publicDevice['status']['overall'];
    if (!isset($summary[$overall])) {
        $overall = 'red';
    }

    $summary['total']++;
    $summary[$overall]++;

    if ($publicDevice['status']['offline']) {
        $summary['offline']++;
    }

    $outputDevices[] = $publicDevice;
}

mgk_ok(array(
    'app_version' => mgk_get_app_version(),
    'generated_at' => mgk_now_iso(),
    'settings' => $settings,
    'summary' => $summary,
    'devices' => $outputDevices,
    'pull_attempts' => $pullAttempts,
    'pull_enabled' => $pullEnabled
));
