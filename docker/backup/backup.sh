#!/bin/bash
# Dump diário do Postgres + cópia dos arquivos enviados (escudos, fotos, documentos), para uma pasta local ou S3-compatível (R2/B2/S3).
# Variáveis: PGHOST PGUSER PGPASSWORD PGDATABASE, BACKUP_REMOTE (pasta ou remoto, ex.: /backups/pg ou r2:meu-bucket/pg),
# BACKUP_FILES_REMOTE (destino dos arquivos; vazio = não copia) e BACKUP_FILES_SOURCE (origem, storage/app/uploads),
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
    backup_files
}

# Cópia ADITIVA dos arquivos enviados (nunca apaga no destino: um engano de exclusão não se propaga ao backup).
backup_files() {
    local source="${BACKUP_FILES_SOURCE:-/data/storage/app/uploads}"
    [ -n "${BACKUP_FILES_REMOTE:-}" ] || return 0
    [ -d "$source" ] || { echo "[backup] sem arquivos enviados ainda (${source})"; return 0; }
    echo "[backup] copiando arquivos enviados"
    rclone copy "$source" "${BACKUP_FILES_REMOTE}" --transfers 4 && echo "[backup] arquivos copiados" || echo "[backup] FALHOU a cópia dos arquivos"
}

[ "${BACKUP_ON_START:-false}" = "true" ] && run_backup

while true; do
    now=$(date -u +%s)
    next=$(date -u -d "today ${HOUR}:00" +%s)
    [ "$next" -le "$now" ] && next=$(date -u -d "tomorrow ${HOUR}:00" +%s)
    sleep $((next - now))
    run_backup || echo "[backup] FALHOU"
done
