#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 3 ]]; then
  echo "Uso: $0 <ENDPOINT> <DEVICE_ID> <TOKEN> [INTERVAL_SECONDS]"
  exit 1
fi

ENDPOINT="$1"
DEVICE_ID="$2"
TOKEN="$3"
INTERVAL="${4:-5}"

get_cpu() {
  local idle
  idle=$(top -bn1 | awk -F'id,' '/Cpu/ { split($1, a, ","); gsub(/[^0-9.]/, "", a[length(a)]); print a[length(a)]; exit }')
  if [[ -z "$idle" ]]; then
    echo "0"
    return
  fi
  awk -v idle="$idle" 'BEGIN { printf "%.2f", 100 - idle }'
}

get_ram() {
  free | awk '/Mem:/ { if ($2 > 0) printf "%.2f", ($3/$2)*100; else print "0" }'
}

get_disk() {
  df -P / | awk 'NR==2 { gsub(/%/,"",$5); print $5 }'
}

echo "MonitorGEKO Linux agent iniciado para DEVICE_ID=${DEVICE_ID}"

while true; do
  cpu="$(get_cpu)"
  ram="$(get_ram)"
  disk="$(get_disk)"
  host="$(hostname)"

  payload=$(printf '{"device_id":"%s","token":"%s","host":"%s","metrics":{"cpu":%s,"ram":%s,"disk":%s}}' "$DEVICE_ID" "$TOKEN" "$host" "$cpu" "$ram" "$disk")

  if curl -sS -X POST "$ENDPOINT" -H 'Content-Type: application/json' -d "$payload" >/dev/null; then
    echo "[$(date +%H:%M:%S)] OK CPU=${cpu}% RAM=${ram}% DISK=${disk}%"
  else
    echo "[$(date +%H:%M:%S)] Error enviando metricas" >&2
  fi

  sleep "$INTERVAL"
done
