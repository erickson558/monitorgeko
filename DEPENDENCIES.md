# Dependencies

## Runtime

- PHP 7.4+ (recommended: PHP 8.1+)
- Web server with PHP support (Apache/Nginx/IIS + FastCGI)

## Required PHP extensions

- `json`
- `openssl`
- `curl`

## Optional PHP extensions

- `ssh2` (used for native SSH execution path; if missing, the app uses CLI SSH clients)

## External tools for SSH pull mode

- One of the following must be available on the server running MonitorApp:
- `plink` (PuTTY)
- `ssh` (OpenSSH client)

## Agent-side dependencies

### Linux agent (`agents/linux-agent.sh`)
- `bash`
- `curl`
- `awk`
- `free`
- `df`

### Windows agent (`agents/windows-agent.ps1`)
- PowerShell 5.1+
- `Get-CimInstance` access
- `Get-Counter` access (fallback implemented when unavailable)

## Data files

- `data/devices.json`
- `data/metrics.json`
- `data/events.json`
- `data/history.json`
- `data/settings.json`

These files are managed by the application and must be writable by the web server user.
