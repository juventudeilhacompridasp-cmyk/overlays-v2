<#
Equivalente do Makefile para Windows (PowerShell). Uso:  .\dev.ps1 <comando>
Se o PowerShell bloquear scripts:  Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
Comandos: setup | up | down | restart | logs [servico] | shell | fresh | migrate | test | lint | fix | stan | types | help
#>
param(
    [Parameter(Position = 0)][string]$Command = "help",
    [Parameter(Position = 1, ValueFromRemainingArguments = $true)][string[]]$Extra
)

Set-Location $PSScriptRoot

function Dc {
    docker compose @args
    if ($LASTEXITCODE -ne 0) { throw "Falhou: docker compose $($args -join ' ')" }
}

function Ensure-Env {
    if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
}

switch ($Command) {
    "setup" {
        Ensure-Env
        Dc up -d --build
        Dc exec app composer install
        Dc exec app php artisan key:generate
        Dc exec app php artisan migrate --seed --force
        Write-Host ""
        Write-Host "Pronto! App: http://localhost:8080  |  Login: admin@plataforma.test / password"
        Write-Host "Mailpit: http://localhost:8025  |  MinIO: http://localhost:9001 (minio / minio-secret)"
    }
    "up"      { Ensure-Env; Dc up -d --build }
    "down"    { Dc down }
    "restart" { Dc restart }
    "logs"    { Dc logs -f --tail=100 @Extra }
    "shell"   { Dc exec app sh }
    "fresh"   { Dc exec app php artisan migrate:fresh --seed --force }
    "migrate" { Dc exec app php artisan migrate }
    "test"    { Dc exec app php artisan test }
    "lint"    { Dc exec app vendor/bin/pint --test }
    "fix"     { Dc exec app vendor/bin/pint }
    "stan"    { Dc exec app vendor/bin/phpstan analyse --memory-limit=1G }
    "types"   { Dc exec vite npx tsc --noEmit }
    default   { Get-Content $PSCommandPath -TotalCount 6 | Select-Object -Skip 1 | Write-Host }
}
