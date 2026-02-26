# MonitorGEKO

Dashboard web para monitorear equipos en tiempo real (CPU, RAM, DISK), con umbrales por color, gestion individual, y operaciones masivas.

## Funciones

- Monitoreo en vivo por tarjetas con estado `verde`, `amarillo`, `rojo`.
- Umbrales configurables por equipo para CPU/RAM/DISK.
- Alta, edicion y eliminacion de equipos.
- Alta masiva por texto CSV.
- Eliminacion masiva por seleccion.
- Soporte de credenciales por equipo para modo `pull_http`.
- Soporte de monitoreo por `pull_ssh` (puerto 22) para Linux y Windows.
- Modo `push` con token por equipo para ingesta segura.

## Estructura

- `index.php`: UI del dashboard.
- `api/devices.php`: CRUD + masivos.
- `api/state.php`: estado consolidado para la UI.
- `api/ingest.php`: endpoint para que agentes envien metricas.
- `assets/css/app.css`: estilos.
- `assets/js/app.js`: logica frontend.
- `agents/windows-agent.ps1`: agente de ejemplo para Windows.
- `agents/linux-agent.sh`: agente de ejemplo para Linux.

## Flujo recomendado (push)

1. En la UI agrega un equipo en modo `push`.
2. Copia el `token` del equipo.
3. Ejecuta el agente en el equipo remoto:

### Windows

```powershell
powershell -ExecutionPolicy Bypass -File .\agents\windows-agent.ps1 \
  -Endpoint "http://TU_SERVIDOR/monitoreos/monitorgeko/api/ingest.php" \
  -DeviceId "ID_DEL_EQUIPO" \
  -Token "TOKEN_DEL_EQUIPO" \
  -IntervalSeconds 5
```

### Linux

```bash
bash ./agents/linux-agent.sh \
  "http://TU_SERVIDOR/monitoreos/monitorgeko/api/ingest.php" \
  "ID_DEL_EQUIPO" \
  "TOKEN_DEL_EQUIPO" \
  5
```

## Modo alterno (pull_http)

- Configura un equipo en modo `pull_http` con una URL que responda JSON.
- Puedes definir `usuario/password` por equipo para Basic Auth.
- JSON esperado:

```json
{
  "metrics": {
    "cpu": 22.4,
    "ram": 64.1,
    "disk": 71.0
  }
}
```

## Modo SSH (pull_ssh, puerto 22)

- Configura el equipo en modo `pull_ssh`.
- Define `usuario`, `password` o `llave privada` (`ssh_key_path`) en el servidor.
- Configura `ssh_os`:
  - `linux` para equipos Linux.
  - `windows` para equipos Windows con OpenSSH habilitado.
  - `auto` intenta Linux y luego Windows.

### Requisitos

- Puerto `22` accesible desde el servidor MonitorGEKO hacia los equipos.
- En Windows: servicio `OpenSSH Server (sshd)` activo.
- Si usaras `ssh` CLI sin `ssh2/plink`, recomienda llave privada en `ssh_key_path`.

### Comandos remotos usados

- Linux: `top`, `free`, `df` para CPU/RAM/DISK.
- Windows: `powershell` con `Get-Counter` y `Get-CimInstance` para CPU/RAM/DISK.

## Seguridad

- Password por equipo se guarda cifrada (`AES-256-CBC`) en `data/devices.json`.
- Puedes definir variable de entorno `MONITORGEKO_SECRET` para controlar la llave de cifrado.
- Si no se define, el sistema genera una llave local en `data/.secret`.
- Si `OpenSSL` no esta disponible en PHP, se usa un fallback de compatibilidad para almacenar password de forma ofuscada.

## Nota

Para proteger archivos internos se incluye `data/.htaccess`.
