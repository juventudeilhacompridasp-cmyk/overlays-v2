<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayFontFile;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Guarda uma fonte própria da organização (até 4, 2 MB). O formato vem dos bytes do arquivo, nunca da extensão. Excluir uma
 * fonte devolve à fonte padrão os canais que a usavam e atualiza os que estão abertos no OBS.
 */
final class SaveOverlayFont
{
    private const DISK = 's3';

    public function handle(UploadedFile $file, string $name): OverlayFont
    {
        if (OverlayFont::query()->count() >= OverlayFont::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.OverlayFont::LIMIT.' fontes. Exclua alguma para enviar outra.');
        }

        $bytes = (string) file_get_contents($file->getRealPath());

        if (strlen($bytes) > OverlayFont::MAX_BYTES) {
            throw new InvalidArgumentException('A fonte passa de 2 MB.');
        }

        $format = OverlayFontFile::detect($bytes);

        if ($format === null) {
            throw new InvalidArgumentException('Arquivo de fonte inválido. Envie WOFF2, WOFF, TTF ou OTF.');
        }

        $font = new OverlayFont(['name' => $name, 'format' => $format, 'bytes' => strlen($bytes), 'path' => '']);
        $font->id = (string) $font->newUniqueId();
        $font->path = 'organizations/'.CurrentOrganization::id().'/overlay-fonts/'.$font->id.'.'.$format;

        Storage::disk(self::DISK)->put($font->path, $bytes, ['ContentType' => $font->contentType(), 'CacheControl' => 'public, max-age=86400']);
        $font->save();

        return $font;
    }

    public function delete(OverlayFont $font): void
    {
        Storage::disk(self::DISK)->delete($font->path);

        OverlayChannel::query()->get()->each(function (OverlayChannel $channel) use ($font): void {
            if (($channel->appearance['font'] ?? null) === $font->getKey()) {
                $channel->appearance = OverlayAppearance::normalize(array_merge($channel->appearance ?? [], ['font' => 'system']));
                $channel->save();
            }
        });

        $font->delete();

        OverlayChannel::query()->get()->each(fn (OverlayChannel $channel) => event(OverlayUpdated::for($channel)));
    }

    public static function contents(OverlayFont $font): ?string
    {
        $disk = Storage::disk(self::DISK);

        return $disk->exists($font->path) ? $disk->get($font->path) : null;
    }
}
