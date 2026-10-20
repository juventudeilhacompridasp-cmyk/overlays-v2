<?php

namespace App\Support\Storage;

/**
 * Formato dos DOIS discos de envio quando o motor é o disco do servidor (`STORAGE_DRIVER=local`). Os discos se chamam `s3`
 * (público: escudos, logotipos, banners) e `s3-private` (privado: fotos de pessoas, documentos e súmulas, só por URL
 * assinada); os NOMES são fixos porque o código e os caminhos salvos dependem deles, e o motor é escolhido em
 * `config/filesystems.php` (S3/R2 por padrão, `local` em servidor único). Sem `env()` aqui: as URLs chegam da configuração.
 *
 * - público: o nginx serve a pasta em `$url` (cache imutável);
 * - privado: sai só por rota assinada da aplicação em `$url`, que NÃO pode repetir a do disco `local` (`/storage`).
 *
 * Trocar de motor NÃO migra arquivos: copie `storage/app/uploads/{public,private}` para os buckets (ou o contrário) antes.
 */
final class UploadDisks
{
    /** @return array<string, mixed> */
    public static function publicLocal(string $url): array
    {
        return [
            'driver' => 'local',
            'root' => storage_path('app/uploads/public'),
            'url' => rtrim($url, '/'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ];
    }

    /** @return array<string, mixed> */
    public static function privateLocal(string $url): array
    {
        return [
            'driver' => 'local',
            'root' => storage_path('app/uploads/private'),
            'url' => rtrim($url, '/'),
            'serve' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ];
    }
}
