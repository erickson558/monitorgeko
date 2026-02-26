<?php
require __DIR__ . '/bootstrap.php';

mgk_init_storage();

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method === 'GET') {
    mgk_ok(array('settings' => mgk_get_settings()));
}

if ($method !== 'POST') {
    mgk_error('Metodo no permitido.', 405, array());
}

$payload = mgk_read_json_body();
$current = mgk_get_settings();
$next = mgk_merge_settings($payload, $current);

if (!mgk_save_settings($next)) {
    mgk_error('No se pudo guardar la configuracion.', 500, array());
}

mgk_ok(array('settings' => $next));
