<#
Coleta o que aconteceu na plataforma nas últimas N horas e grava um resumo em monitoramento\relatorios\coleta-<data>.txt.
Somente leitura: não altera containers nem dados. Uso:  .\monitoramento\coletar.ps1 [-Horas 1]
#>
param([double]$Horas = 1)

$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
$raiz = Split-Path $PSScriptRoot -Parent
Set-Location $raiz
$saidaDir = Join-Path $PSScriptRoot 'relatorios'
New-Item -ItemType Directory -Force $saidaDir | Out-Null
$arquivo = Join-Path $saidaDir ("coleta-{0}.txt" -f (Get-Date -Format 'yyyy-MM-dd_HH-mm'))
$since = "{0}m" -f [int]($Horas * 60)
$out = New-Object System.Collections.Generic.List[string]
function Add-Line($t) { $out.Add([string]$t) }

Add-Line "=== COLETA $(Get-Date -Format 'yyyy-MM-dd HH:mm') (janela: últimos $since) ==="

# 1) Estado dos containers
Add-Line "`n--- CONTAINERS ---"
docker stack services overlays --format '{{.Name}} | réplicas {{.Replicas}} | {{.Image}}' 2>&1 | ForEach-Object { Add-Line $_ }
docker stack ps overlays --filter "desired-state=running" --format '{{.Name}} | {{.CurrentState}}' 2>&1 | ForEach-Object { Add-Line $_ }
Add-Line "Tarefas que falharam/reiniciaram na janela:"
docker stack ps overlays --no-trunc --format '{{.Name}} | {{.CurrentState}} | {{.Error}}' 2>&1 | Where-Object { $_ -match 'Failed|Rejected|Shutdown' -and $_ -notmatch 'migrate' } | Select-Object -First 15 | ForEach-Object { Add-Line "  $_" }
docker stats --no-stream --format '{{.Name}} cpu={{.CPUPerc}} mem={{.MemUsage}} ({{.MemPerc}})' 2>&1 | Where-Object { $_ -match '^overlays_' } | ForEach-Object { Add-Line "  $_" }

# 2) Acessos (nginx)
Add-Line "`n--- ACESSOS (nginx) ---"
$linhas = docker service logs --raw --since $since overlays_nginx 2>$null |
    Where-Object { $_ -match '^\S+ - \S* \[' }
$rx = '^(?<ip>\S+) - \S* \[(?<ts>[^\]]+)\] "(?<m>\S+) (?<p>\S+) [^"]*" (?<s>\d{3}) (?<b>\d+) "(?<ref>[^"]*)" "(?<ua>[^"]*)"(?: "(?<xff>[^"]*)")?'
$reg = foreach ($l in $linhas) {
    if ($l -match $rx) {
        [pscustomobject]@{ M = $Matches.m; P = ($Matches.p -split '\?')[0]; S = [int]$Matches.s; Ip = $(if ($Matches.xff) { ($Matches.xff -split ',')[0].Trim() } else { $Matches.ip }); Ua = $Matches.ua; Full = $Matches.p }
    }
}
$reg = @($reg | Where-Object { $_.P -ne '/up' })
Add-Line "Total de requisições (sem /up): $($reg.Count)"
Add-Line "IPs distintos: $(@($reg | Select-Object -ExpandProperty Ip -Unique).Count)"
Add-Line "Por status:"
$reg | Group-Object { [math]::Floor($_.S / 100) * 100 } | Sort-Object Name | ForEach-Object { Add-Line "  $($_.Name)xx: $($_.Count)" }
Add-Line "Top 10 caminhos:"
$reg | Group-Object P | Sort-Object Count -Descending | Select-Object -First 10 | ForEach-Object { Add-Line "  $($_.Count)  $($_.Name)" }
Add-Line "Top 5 IPs:"
$reg | Group-Object Ip | Sort-Object Count -Descending | Select-Object -First 5 | ForEach-Object { Add-Line "  $($_.Count)  $($_.Name)" }
Add-Line "Erros 5xx (até 30):"
$reg | Where-Object { $_.S -ge 500 } | Select-Object -First 30 | ForEach-Object { Add-Line "  $($_.S) $($_.M) $($_.Full) ip=$($_.Ip)" }
Add-Line "Erros 4xx exceto 404 de assets/favicon (até 30):"
$reg | Where-Object { $_.S -ge 400 -and $_.S -lt 500 -and $_.P -notmatch '\.(map|ico|png|jpg|svg|webp)$' } |
    Select-Object -First 30 | ForEach-Object { Add-Line "  $($_.S) $($_.M) $($_.Full) ip=$($_.Ip)" }
Add-Line "Suspeitos (tentativas em .env, .git, wp-, phpmyadmin, vendor, etc.):"
$reg | Where-Object { $_.P -match '(\.env|\.git|wp-|phpmyadmin|phpinfo|/vendor/|\.php$|xmlrpc|/admin\.)' -and $_.P -ne '/index.php' } |
    Select-Object -First 20 | ForEach-Object { Add-Line "  $($_.S) $($_.M) $($_.Full) ip=$($_.Ip)" }

# 3) Erros nos serviços da aplicação
foreach ($svc in 'app', 'horizon', 'reverb', 'scheduler', 'nginx') {
    Add-Line "`n--- ERROS EM LOG: $svc ---"
    $e = docker service logs --raw --since $since "overlays_$svc" 2>$null |
        Where-Object { $_ -match '(ERROR|CRITICAL|EMERGENCY|Exception|Fatal|SQLSTATE|failed|Failed|\[error\]|\[crit\]|upstream)' -and $_ -notmatch '"(GET|POST|PUT|PATCH|DELETE|HEAD) ' }
    if ($e) { $e | Select-Object -First 40 | ForEach-Object { Add-Line ("  " + $_.Substring(0, [Math]::Min($_.Length, 400))) } } else { Add-Line "  (nenhum)" }
}

# 4) Log do Laravel (storage\logs)
Add-Line "`n--- LARAVEL (storage\logs) ---"
$corte = (Get-Date).AddHours(-$Horas)
Get-ChildItem (Join-Path $raiz 'storage\logs') -Filter *.log -ErrorAction SilentlyContinue | ForEach-Object {
    $conteudo = Get-Content $_.FullName -Tail 2000
    $achou = $conteudo | Where-Object { $_ -match '^\[(?<d>\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \S+\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING)' }
    foreach ($a in $achou) {
        if ($a -match '^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\]') { if ([datetime]$Matches[1] -ge $corte.ToUniversalTime().AddHours(-3)) { Add-Line ("  " + $a.Substring(0, [Math]::Min($a.Length, 500))) } }
    }
}

# 5) Filas com falha
Add-Line "`n--- FILAS (jobs com falha) ---"
$appCt = docker ps -q -f "name=overlays_app." | Select-Object -First 1
docker exec $appCt php artisan queue:failed 2>&1 | Select-Object -First 15 | ForEach-Object { Add-Line "  $_" }

$out | Set-Content -Path $arquivo -Encoding utf8
Write-Output $arquivo
