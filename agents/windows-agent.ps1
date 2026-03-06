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
    $cpu = $null
    try {
      $perfCpu = Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -ErrorAction Stop |
        Where-Object { $_.Name -eq '_Total' } |
        Select-Object -First 1
      if ($perfCpu -and $null -ne $perfCpu.PercentProcessorTime) {
        $cpu = [double]$perfCpu.PercentProcessorTime
      }
    }
    catch {
      $cpu = $null
    }

    if ($null -eq $cpu) {
      try {
        $samples = (Get-Counter '\Processor(_Total)\% Processor Time' -MaxSamples 1 -ErrorAction Stop).CounterSamples
        if ($samples -and $samples.Count -gt 0) {
          $cpu = [double]$samples[$samples.Count - 1].CookedValue
        }
      }
      catch {
        $cpu = $null
      }
    }

    $cpuUtility = $null
    try {
      $perfUtil = Get-CimInstance Win32_PerfFormattedData_Counters_ProcessorInformation -ErrorAction Stop |
        Where-Object { $_.Name -eq '_Total' } |
        Select-Object -First 1
      if ($perfUtil -and $null -ne $perfUtil.PercentProcessorUtility) {
        $cpuUtility = [double]$perfUtil.PercentProcessorUtility
      }
    }
    catch {
      $cpuUtility = $null
    }

    if ($null -ne $cpuUtility) {
      if ($null -eq $cpu -or $cpuUtility -gt $cpu) {
        $cpu = $cpuUtility
      }
    }

    if ($null -eq $cpu) {
      try {
        $cpuLoad = Get-CimInstance Win32_Processor -ErrorAction Stop | Measure-Object -Property LoadPercentage -Average
        if ($cpuLoad -and $null -ne $cpuLoad.Average) {
          $cpu = [double]$cpuLoad.Average
        }
      }
      catch {
        $cpu = 0
      }
    }

    if ($null -eq $cpu -or [double]::IsNaN([double]$cpu) -or [double]::IsInfinity([double]$cpu)) {
      $cpu = 0
    }
    if ($cpu -lt 0) {
      $cpu = 0
    }
    if ($cpu -gt 100) {
      $cpu = 100
    }

    $os = Get-CimInstance Win32_OperatingSystem
    $ramUsedPct = (($os.TotalVisibleMemorySize - $os.FreePhysicalMemory) / $os.TotalVisibleMemorySize) * 100

    $diskObj = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='$Drive'"
    $diskUsedPct = 0
    if ($diskObj -and $diskObj.Size -gt 0) {
      $diskUsedPct = (($diskObj.Size - $diskObj.FreeSpace) / $diskObj.Size) * 100
    }

    $networkMbps = $null
    try {
      $netSamples = (Get-Counter '\Network Interface(*)\Bytes Total/sec' -ErrorAction Stop).CounterSamples
      if ($netSamples) {
        $bytesPerSec = 0.0
        foreach ($sample in $netSamples) {
          if ($sample.InstanceName -notmatch 'Loopback|isatap|Teredo') {
            $bytesPerSec += [double]$sample.CookedValue
          }
        }
        if ($bytesPerSec -gt 0) {
          $networkMbps = ($bytesPerSec * 8) / 1000000
        }
      }
    }
    catch {
      $networkMbps = $null
    }

    if ($null -eq $networkMbps) {
      try {
        $a1 = Get-NetAdapterStatistics -ErrorAction Stop | Where-Object { $_.Name -notmatch 'Loopback|isatap|Teredo' }
        Start-Sleep -Milliseconds 900
        $a2 = Get-NetAdapterStatistics -ErrorAction Stop | Where-Object { $_.Name -notmatch 'Loopback|isatap|Teredo' }
        $b1 = 0.0
        $b2 = 0.0
        foreach ($it in $a1) {
          $b1 += [double]$it.ReceivedBytes + [double]$it.SentBytes
        }
        foreach ($it in $a2) {
          $b2 += [double]$it.ReceivedBytes + [double]$it.SentBytes
        }
        if ($b2 -ge $b1) {
          $networkMbps = (($b2 - $b1) * 8) / 1000000
        }
      }
      catch {
        $networkMbps = 0
      }
    }

    if ($null -eq $networkMbps) {
      $networkMbps = 0
    }
    if ($networkMbps -lt 0) {
      $networkMbps = 0
    }
    if ($networkMbps -gt 100) {
      $networkMbps = 100
    }

    $metrics = @{
      cpu  = [Math]::Round($cpu, 2)
      ram  = [Math]::Round($ramUsedPct, 2)
      disk = [Math]::Round($diskUsedPct, 2)
      network = [Math]::Round($networkMbps, 2)
    }

    $payload = @{
      device_id = $DeviceId
      token     = $Token
      metrics   = $metrics
      host      = $env:COMPUTERNAME
    } | ConvertTo-Json -Depth 6

    Invoke-RestMethod -Method Post -Uri $Endpoint -ContentType 'application/json' -Body $payload -TimeoutSec 8 | Out-Null
    Write-Host ("[{0}] OK CPU={1}% RAM={2}% DISK={3}% RED={4}%" -f (Get-Date -Format 'HH:mm:ss'), $metrics.cpu, $metrics.ram, $metrics.disk, $metrics.network)
  }
  catch {
    Write-Warning ("[{0}] Error enviando metricas: {1}" -f (Get-Date -Format 'HH:mm:ss'), $_.Exception.Message)
  }

  Start-Sleep -Seconds $IntervalSeconds
}
