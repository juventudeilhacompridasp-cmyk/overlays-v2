<?php

namespace App\Support\Storage;

/**
 * Definição dos DOIS discos de envio do sistema: `s3` (público: escudos, logotipos, banners) e `s3-private` (privado: fotos
 * de pessoas, documentos e súmulas, só por URL assinada). Os NOMES são fixos porque o código e os caminhos salvos dependem
 * deles; o que muda é o motor, escolhido por `STORAGE_DRIVER`:
 *
 * - `s3` (padrão): bucket S3-compatível (R2 em produção, S3Mock no desenvolvimento);
 * - `local`: o disco do próprio servidor. O público sai em `FILES_PUBLIC_URL` (o nginx serve a pasta, cache imutável) e o
 *   privado sai por rota assinada da aplicação (`FILES_PRIVATE_URL`), sem nunca ficar exposto no nginx.
 *
 * Trocar de motor NÃO migra arquivos: copie `storage/app/uploads/{public,private}` para os buckets (ou o contrário) antes.
 */
final class UploadDisks
{
    public static function isLocal(?string $driver = null): bool
    {
        return ($driver ?? (string) env('STORAGE_DRIVER', 's3')) === 'local';
    }

    /** @return array<string, mixed> */
    public static function publicDisk(?string $driver = null): array
    {
        if (self::isLocal($driver)) {
            return [
                'driver' => 'local',
                'root' => storage_path('app/uploads/public'),
                'url' => rtrim((string) env('FILES_PUBLIC_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/files'), '/'),
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ];
        }

        return [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ];
    }

    /** @return array<string, mixed> */
    public static function privateDisk(?string $driver = null): array
    {
        if (self::isLocal($driver)) {
            return [
                'driver' => 'local',
                'root' => storage_path('app/uploads/private'),
                // URL própria (a rota assinada `storage.s3-private`): precisa ser diferente da do disco `local` (/storage).
                'url' => rtrim((string) env('FILES_PRIVATE_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/private-files'), '/'),
                'serve' => true,
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ];
        }

        return [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_PRIVATE_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ];
    }
}
