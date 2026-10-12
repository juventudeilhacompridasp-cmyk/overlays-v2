#!/bin/bash
# Dump diário do Postgres enviado ao armazenamento S3-compatível (R2/B2/S3).
# Variáveis: PGHOST PGUSER PGPASSWORD PGDATABASE, BACKUP_REMOTE (ex.: r2:meu-bucket/pg),
# RCLONE_CONFIG_R2_* (type, provider, access_key_id, secret_access_key, endpoint), BACKUP_HOUR (UTC, padrão 3), RETENTION_DAYS (padrão 14)
set -euo pipefail

HOUR="${BACKUP_HOUR:-3}"
RETENTION="${RETENTION_DAYS:-14}"

run_backup() {
    local name="db-$(date -u +%Y%m%d-%H%M%S).dump"
    echo "[backup] iniciando ${name}"
    pg_dump -Fc | rclone rcat "${BACKUP_REMOTE}/${name}"
    rclone delete "${BACKUP_REMOTE}" --min-age "${RETENTION}d" || true
    echo "[backup] concluído ${name}"
}

[ "${BACKUP_ON_START:-false}" = "true" ] && run_backup

while true; do
    now=$(date -u +%s)
    next=$(date -u -d "today ${HOUR}:00" +%s)
    [ "$next" -le "$now" ] && next=$(date -u -d "tomorrow ${HOUR}:00" +%s)
    sleep $((next - now))
    run_backup || echo "[backup] FALHOU"
done
