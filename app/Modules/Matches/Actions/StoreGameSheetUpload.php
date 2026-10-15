<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Support\PlatformLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Guarda a súmula enviada depois do jogo (ADR 0004). Dado pessoal (nomes de atletas, inclusive menores): disco PRIVADO,
 * aberto no painel só por URL temporária assinada.
 */
final class StoreGameSheetUpload
{
    public const DISK = 's3-private';

    public const MAX_KB = 15360;

    /** @var array<string, array{0: string, 1: string}> mime => [extensão, tipo] */
    private const TYPES = [
        'application/pdf' => ['pdf', 'pdf'],
        'image/jpeg' => ['jpg', 'image'],
        'image/png' => ['png', 'image'],
        'image/webp' => ['webp', 'image'],
        'text/csv' => ['csv', 'structured'],
        'application/csv' => ['csv', 'structured'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx', 'structured'],
    ];

    public function handle(Game $game, UploadedFile $file, ?int $uploadedBy = null): GameSheetUpload
    {
        $mime = (string) $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // CSV costuma ser detectado como texto simples.
        if ($mime === 'text/plain' && $extension === 'csv') {
            $mime = 'text/csv';
        }

        if (! isset(self::TYPES[$mime])) {
            throw new InvalidArgumentException('Envie uma foto (JPG, PNG ou WebP), um PDF ou um arquivo CSV/XLSX.');
        }

        if ((int) $file->getSize() > self::MAX_KB * 1024) {
            throw new InvalidArgumentException('O arquivo passa de 15 MB.');
        }

        $content = (string) file_get_contents($file->getRealPath());
        $hash = hash('sha256', $content);

        if (GameSheetUpload::query()->where('game_id', $game->getKey())->where('sha256', $hash)->exists()) {
            throw new InvalidArgumentException('Este arquivo já foi enviado para este jogo.');
        }

        [$ext, $kind] = self::TYPES[$mime];
        $path = "organizations/{$game->organization_id}/game-sheets/{$game->getKey()}/".bin2hex(random_bytes(8)).'.'.$ext;

        Storage::disk(self::DISK)->put($path, $content, ['ContentType' => $mime]);

        return GameSheetUpload::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'uploaded_by' => $uploadedBy,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 150),
            'mime' => $mime,
            'kind' => $kind,
            'size' => strlen($content),
            'sha256' => $hash,
            'status' => SheetUploadStatus::Uploaded,
        ]);
    }

    public function remove(GameSheetUpload $upload): void
    {
        Storage::disk(self::DISK)->delete($upload->path);
        $upload->delete();
    }

    /** URL temporária assinada (validade dos documentos da plataforma, 10 min por padrão) para abrir o arquivo. */
    public function signedUrl(GameSheetUpload $upload): ?string
    {
        try {
            return Storage::disk(self::DISK)->temporaryUrl($upload->path, now()->addMinutes(PlatformLimits::signedMinutes('documents')), [
                'ResponseContentDisposition' => 'attachment; filename="'.str_replace('"', '', $upload->original_name).'"',
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
