<#
Gerencia a stack local em Docker Swarm (docker-stack.yml). O código do projeto é MONTADO nos containers: não há rebuild de imagem
a cada alteração. Depois de um `git pull`, basta:   .\swarm.ps1 update
Uso:  .\swarm.ps1 <comando> [-Full] [-Front] [-Composer] [-Image]
Comandos: init | update | deploy | build | status | logs <servico> | stats | artisan <args...> | composer <args...> | shell | rm | help
Se o PowerShell bloquear scripts:  Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#>
param(
    [Parameter(Position = 0)][string]$Command = "help",
    [Parameter(Position = 1, ValueFromRemainingArguments = $true)][string[]]$Extra,
    [switch]$Full, [switch]$Front, [switch]$Composer, [switch]$Image
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
$Stack = 'overlays'
$StateFile = Join-Path $PSScriptRoot '.swarm-last'
$ProjectDir = ($PSScriptRoot -replace '\\', '/')

function Load-DotEnv {
    # Lê o .env (KEY=VALUE, aspas e ${VAR}) para o ambiente do processo, de onde o `docker stack deploy` substitui variáveis.
    $vars = @{}
    foreach ($line in Get-Content .env) {
        if ($line -match '^\s*#' -or $line -notmatch '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$') { continue }
        $k = $Matches[1]; $v = $Matches[2].Trim()
        if ($v -match '^"(.*)"$' -or $v -match "^'(.*)'$") { $v = $Matches[1] } else { $v = ($v -replace '\s+#.*$', '') }
        $v = [regex]::Replace($v, '\$\{([A-Za-z_][A-Za-z0-9_]*)\}', { param($m) if ($vars.ContainsKey($m.Groups[1].Value)) { $vars[$m.Groups[1].Value] } else { '' } })
        $vars[$k] = $v
        Set-Item -Path "Env:$k" -Value $v
    }
    $env:PROJECT_DIR = $ProjectDir
}

function Dk {
    # No Windows PowerShell 5.1 o stderr do docker (progresso) viraria erro com 'Stop'; só o código de saída vale.
    $prev = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    & docker @args
    $code = $LASTEXITCODE
    $ErrorActionPreference = $prev
    if ($code -ne 0) { throw "Falhou: docker $($args -join ' ')" }
}
function Task-Container($svc) { (& docker ps -q -f "name=${Stack}_$svc." | Select-Object -First 1) }
function Require-Swarm { if ((docker info --format '{{.Swarm.LocalNodeState}}') -ne 'active') { throw "Swarm inativo: rode .\swarm.ps1 init" } }

function Build-Images {
    Dk build -t overlays-app:runtime docker/local
    if ($Full -or -not (docker image ls -q overlays-backup:local)) { Dk build -t overlays-backup:local docker/backup }
}

function Ensure-Volumes {
    foreach ($v in 'overlays_vendor', 'overlays_node_modules', 'overlays_storage') { Dk volume create $v | Out-Null }
    # vendor e storage são usados pelo www-data (uid 82) dos containers da aplicação.
    Dk run --rm -v overlays_vendor:/v -v overlays_storage:/s alpine sh -c 'for d in /v /s; do [ $(stat -c %u $d) = 82 ] || chown -R 82:82 $d; done'
}

function Deploy-Stack {
    Require-Swarm; Load-DotEnv
    Dk stack deploy -c docker-stack.yml --resolve-image never --prune --detach=true $Stack
}

function Artisan { $c = Task-Container 'app'; if (-not $c) { throw "Sem container app" }; Dk exec -i $c php artisan @args }

switch ($Command) {
    "init" {
        if ((docker info --format '{{.Swarm.LocalNodeState}}') -ne 'active') { Dk swarm init }
        Dk node ls
    }
    "build" { Build-Images }
    "deploy" { Deploy-Stack }
    "update" {
        Require-Swarm
        if (-not (Test-Path .env)) { throw ".env não encontrado" }

        # O que mudou desde a última atualização (commits + alterações locais não commitadas)?
        $head = (git rev-parse HEAD).Trim()
        $last = if (Test-Path $StateFile) { (Get-Content $StateFile -Raw).Trim() } else { '' }
        $files = @()
        $firstRun = $false
        if ($last -and -not $Full) {
            git cat-file -e "$last^{commit}" 2>$null
            if ($LASTEXITCODE -eq 0) { $files = @(git diff --name-only $last $head) } else { $firstRun = $true }
            $files += @(git diff --name-only HEAD)
        } else { $firstRun = $true }
        $full = $firstRun -or $Full
        $any = { param($rx) $full -or @($files | Where-Object { $_ -match $rx }).Count -gt 0 }

        # A imagem só é refeita se ainda não existe, se docker/local mudou ou se pedido com -Image (PowerShell não tem precedência entre -and/-or).
        $needImage = [bool]$Image
        if (-not (docker image ls -q overlays-app:runtime)) { $needImage = $true }
        if (@($files | Where-Object { $_ -match '^docker/local/' }).Count -gt 0) { $needImage = $true }
        $needComposer = $Composer -or (& $any '^composer\.(json|lock)$')
        $needFront = [bool]$Front -or [bool](& $any '^(resources/(js|css)/|package(-lock)?\.json$|vite\.config|tailwind\.config|postcss\.config|tsconfig)') -or -not (Test-Path public/build/manifest.json)
        $needComposer = [bool]$needComposer
        $anyChange = [bool]($full -or $files.Count -gt 0 -or $needComposer -or $needFront -or $needImage)

        Write-Host ("Alterações detectadas: {0} arquivo(s). Imagem: {1} | composer: {2} | front: {3}" -f $files.Count, $needImage, $needComposer, $needFront)

        if ($needImage) { Write-Host "==> Imagem de execução"; Build-Images }
        Write-Host "==> Volumes"; Ensure-Volumes

        if ($needComposer) {
            Write-Host "==> composer install"
            Dk run --rm -v "${PSScriptRoot}:/var/www/html" -v overlays_vendor:/var/www/html/vendor -v overlays_storage:/var/www/html/storage --entrypoint composer overlays-app:runtime install --no-dev --optimize-autoloader --no-interaction
        }
        if ($needFront) {
            Write-Host "==> build do front (vite; sem rebuild de imagem)"
            $npm = if ($full -or (& $any '^package(-lock)?\.json$')) { 'npm ci --no-audit --no-fund && npm run build' } else { 'npm run build' }
            Dk run --rm -e HOME=/tmp -w /var/www/html -v "${PSScriptRoot}:/var/www/html" -v overlays_node_modules:/var/www/html/node_modules -v overlays_vendor:/var/www/html/vendor node:22-alpine sh -c $npm
        }

        Write-Host "==> Stack"; Deploy-Stack
        Write-Host "Aguardando o app subir..."
        for ($i = 0; $i -lt 40 -and -not (Task-Container 'app'); $i++) { Start-Sleep 3 }
        for ($i = 0; $i -lt 30; $i++) { try { if ((Invoke-WebRequest http://localhost:8080/up -UseBasicParsing -TimeoutSec 5).StatusCode -eq 200) { break } } catch {}; Start-Sleep 3 }

        if ($anyChange) {
            Write-Host "==> migrations e caches"
            Artisan migrate --force
            Artisan optimize
            Write-Host "==> reiniciando processos de longa duração (horizon, reverb, scheduler$(if ($needFront) { ', ssr' }))"
            Artisan horizon:terminate | Out-Null
            Artisan reverb:restart | Out-Null
            Dk service update --force --detach "${Stack}_scheduler" | Out-Null
            if ($needFront) { Dk service update --force --detach "${Stack}_ssr" | Out-Null }
        }
        Set-Content -Path $StateFile -Value $head
        Write-Host "Pronto (commit $($head.Substring(0,7)))."
    }
    "status"  { Dk stack services $Stack; Dk stack ps $Stack --no-trunc --filter "desired-state=running" --format "table {{.Name}}\t{{.CurrentState}}\t{{.Error}}" }
    "logs"    { Dk service logs -f --tail 100 "${Stack}_$($Extra[0])" }
    "stats"   { Dk stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}" }
    "artisan" { Artisan @Extra }
    "composer" { Dk run --rm -v "${PSScriptRoot}:/var/www/html" -v overlays_vendor:/var/www/html/vendor --entrypoint composer overlays-app:runtime @Extra }
    "shell"   { $c = Task-Container 'app'; if (-not $c) { throw "Sem container app" }; Dk exec -it $c sh }
    "rm"      { Dk stack rm $Stack }
    default   { Get-Content $PSCommandPath -TotalCount 8 | Select-Object -Skip 1 | Write-Host }
}
