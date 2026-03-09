# MonitorApp

MonitorApp es un dashboard web para monitoreo de equipos en tiempo real con enfoque operativo:

- Estado de salud por equipo (`green`, `yellow`, `red`)
- Metricas: CPU, RAM, DISK y RED
- Gestion de equipos individual y masiva
- Captura por `push`, `pull_http` y `pull_ssh`
- Historial por hora y eventos de estado/servicios

## Que Hace El Programa

1. Registra equipos y su forma de consulta.
2. Obtiene metricas periodicamente o recibe ingesta desde agentes.
3. Evalua umbrales por equipo.
4. Calcula estado consolidado y muestra tarjetas en vivo.
5. Guarda metricas, eventos e historico en archivos JSON locales.

## Arquitectura

- `index.php`: frontend y configuracion inicial.
- `assets/js/app.js`: logica de UI, render, polling y acciones.
- `api/state.php`: estado consolidado para dashboard.
- `api/devices.php`: CRUD de equipos y operaciones masivas.
- `api/ingest.php`: ingesta `push` de agentes.
- `api/bootstrap.php`: utilidades compartidas, normalizacion, SSH y seguridad.
- `agents/windows-agent.ps1`: agente `push` para Windows.
- `agents/linux-agent.sh`: agente `push` para Linux.
- `data/*.json`: almacenamiento local.

## Modos De Monitoreo

## `push` (recomendado)

El agente remoto envia metricas al endpoint `api/ingest.php` con `device_id` y `token`.

### Windows

```powershell
powershell -ExecutionPolicy Bypass -File .\agents\windows-agent.ps1 \
  -Endpoint "http://TU_SERVIDOR/monitoreos/<tu-proyecto>/api/ingest.php" \
  -DeviceId "ID_DEL_EQUIPO" \
  -Token "TOKEN_DEL_EQUIPO" \
  -IntervalSeconds 5
```

### Linux

```bash
bash ./agents/linux-agent.sh \
  "http://TU_SERVIDOR/monitoreos/<tu-proyecto>/api/ingest.php" \
  "ID_DEL_EQUIPO" \
  "TOKEN_DEL_EQUIPO" \
  5
```

## `pull_http`

Consulta un endpoint HTTP remoto que responde JSON con metricas.

```json
{
  "metrics": {
    "cpu": 22.4,
    "ram": 64.1,
    "disk": 71.0,
    "network": 18.7
  }
}
```

## `pull_ssh`

El servidor MonitorApp entra por SSH y ejecuta comandos remotos en Linux o Windows.

- `ssh_os=linux`: comandos Linux
- `ssh_os=windows`: PowerShell remoto
- `ssh_os=auto`: intenta Linux y luego Windows

## Metricas De Red

La metrica `network` se maneja como porcentaje normalizado `0-100`, usando Mbps instantaneos capados a 100 para visualizacion homogena.

- Linux: delta de bytes (`/proc/net/dev`) convertido a Mbps con precision decimal.
- Windows: `Get-Counter` sobre interfaces y fallback por muestreo de `Get-NetAdapterStatistics`.
- Agentes `push` Linux/Windows ya incluyen `network`.

Si la red aparece baja en pruebas:

1. Verifica que el intervalo del agente coincida con la duracion de la prueba.
2. Asegura que no solo se mida interfaz loopback.
3. Revisa que el trafico salga por interfaz fisica activa.

## Dependencias

Consulta `DEPENDENCIES.md` para detalle completo de runtime y herramientas.

## Seguridad

- Credenciales de equipos cifradas en reposo (`AES-256-CBC`) cuando OpenSSL esta disponible.
- Clave por variable `MONITORAPP_SECRET` o archivo local `data/.secret`.
- Token por equipo para ingesta `push`.
- Recomendado: restringir acceso web a `data/` y respaldar periodicamente.

## Versionado Y Releases

El proyecto usa SemVer con prefijo `Vx.x.x` en formato almacenado `vX.Y.Z`.

- Fuente unica de verdad: archivo `VERSION`.
- La app muestra la version en UI y `api/state.php` (`app_version`).
- Cada commit de release debe incrementar version.
- Cada release debe crear tag git igual a `VERSION`.

Flujo recomendado:

1. Actualizar `VERSION`.
2. Actualizar `CHANGELOG.md`.
3. Commit con mensaje de release.
4. Crear tag (`git tag vX.Y.Z`).
5. Push branch y tag (`git push origin main` y `git push origin vX.Y.Z`).

## Buenas Practicas En GitHub

- Mantener `main` siempre desplegable (sin commits que rompan runtime).
- Usar mensajes de commit claros en imperativo (ejemplo: `release: v1.1.0 rename branding to monitorapp`).
- Hacer un commit por cambio atomico y subir su version asociada en `VERSION`.
- Crear un tag por release exactamente igual al contenido de `VERSION`.
- Mantener `CHANGELOG.md` actualizado en cada release con fecha y categorias (`Added`, `Changed`, `Fixed`, `Security`).

## Licencia

Este proyecto se distribuye bajo `Apache License 2.0`.
Consulta el archivo `LICENSE`.
