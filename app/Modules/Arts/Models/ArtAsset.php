<?php

namespace App\Modules\Arts\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Imagem da organização usada nas artes: fundo, logotipo ou selo. */
#[Unguarded]
class ArtAsset extends Model
{
    use BelongsToOrganization, HasUlids;

    public const BACKGROUND = 'background';

    public const LOGO = 'logo';

    public const BADGE = 'badge';

    public const KINDS = [self::BACKGROUND => 'Fundo', self::LOGO => 'Logotipo', self::BADGE => 'Selo'];

    /** Tamanho máximo (lado maior, px) guardado de cada tipo. */
    public const SIZES = [self::BACKGROUND => 2400, self::LOGO => 800, self::BADGE => 800];

    /** Imagens por organização (evita encher o armazenamento). */
    public const LIMIT = 30;

    /** Endereço da imagem para o navegador (rota do painel, mesma origem). Só dentro da requisição. */
    public function url(): string
    {
        return route('panel.arts.assets.file', $this->getKey());
    }

    /** @return array{id: string, kind: string, name: string, url: string, width: int, height: int} */
    public function toCard(): array
    {
        return ['id' => $this->getKey(), 'kind' => $this->kind, 'name' => $this->name, 'url' => $this->url(), 'width' => $this->width, 'height' => $this->height];
    }

    public function contents(): ?string
    {
        return ImageStore::publicContents($this->path, $this->variant_size);
    }
}
