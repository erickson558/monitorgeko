param(
  [Parameter(Mandatory = $true)]
  [string]$Endpoint,

  [Parameter(Mandatory = $true)]
  [string]$DeviceId,

  [Parameter(Mandatory = $true)]
  [string]$Token,

  [int]$IntervalSeconds = 5,
  [string]$Drive = 'C:'
)

Write-Host "MonitorGEKO Windows agent iniciado para DeviceId=$DeviceId" -ForegroundColor Cyan

while ($true) {
  try {
    $cpu = (Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples[0].CookedValue

    $os = Get-CimInstance Win32_OperatingSystem
    $ramUsedPct = (($os.TotalVisibleMemorySize - $os.FreePhysicalMemory) / $os.TotalVisibleMemorySize) * 100

    $diskObj = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='$Drive'"
    $diskUsedPct = 0
    if ($diskObj -and $diskObj.Size -gt 0) {
      $diskUsedPct = (($diskObj.Size - $diskObj.FreeSpace) / $diskObj.Size) * 100
    }

    $metrics = @{
      cpu  = [Math]::Round($cpu, 2)
      ram  = [Math]::Round($ramUsedPct, 2)
      disk = [Math]::Round($diskUsedPct, 2)
    }

    $payload = @{
      device_id = $DeviceId
      token     = $Token
      metrics   = $metrics
      host      = $env:COMPUTERNAME
    } | ConvertTo-Json -Depth 6

    Invoke-RestMethod -Method Post -Uri $Endpoint -ContentType 'application/json' -Body $payload -TimeoutSec 8 | Out-Null
    Write-Host ("[{0}] OK CPU={1}% RAM={2}% DISK={3}%" -f (Get-Date -Format 'HH:mm:ss'), $metrics.cpu, $metrics.ram, $metrics.disk)
  }
  catch {
    Write-Warning ("[{0}] Error enviando metricas: {1}" -f (Get-Date -Format 'HH:mm:ss'), $_.Exception.Message)
  }

  Start-Sleep -Seconds $IntervalSeconds
}
