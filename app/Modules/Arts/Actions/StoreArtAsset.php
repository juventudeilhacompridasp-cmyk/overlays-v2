<?php

namespace App\Modules\Arts\Actions;

use App\Models\User;
use App\Modules\Arts\Models\ArtAsset;
use App\Modules\Tenancy\CurrentOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Guarda uma imagem da organização para as artes (fundo, logotipo ou selo). `ImageVariants` reencoda em WebP (descarta
 * EXIF/GPS, só JPG/PNG/WebP, nunca SVG), sem ampliar; o fundo fica até 2400 px no lado maior, logotipos e selos até 800 px.
 */
final class StoreArtAsset
{
    public function handle(UploadedFile $file, string $kind, string $name, ?User $by = null): ArtAsset
    {
        if (! isset(ArtAsset::KINDS[$kind])) {
            throw new InvalidArgumentException('Tipo de imagem desconhecido.');
        }

        if (ArtAsset::query()->count() >= ArtAsset::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.ArtAsset::LIMIT.' imagens de artes. Exclua alguma para enviar outra.');
        }

        $size = ArtAsset::SIZES[$kind];
        $dimensions = @getimagesize($file->getRealPath()) ?: [0, 0];
        $organization = CurrentOrganization::get();

        $path = ImageStore::save('s3', "organizations/{$organization?->getKey()}/arts", $kind, $file, ['full' => ['size' => $size, 'fit' => 'contain']]);

        $scale = min(1, $size / max((int) $dimensions[0], (int) $dimensions[1], 1));

        return ArtAsset::query()->create([
            'kind' => $kind,
            'name' => $name,
            'path' => $path,
            'variant_size' => $size,
            'width' => max(1, (int) round($dimensions[0] * $scale)),
            'height' => max(1, (int) round($dimensions[1] * $scale)),
            'created_by' => $by?->getKey(),
        ]);
    }

    public function delete(ArtAsset $asset): void
    {
        ImageStore::delete('s3', $asset->path, [$asset->variant_size]);
        $asset->delete();
    }
}
