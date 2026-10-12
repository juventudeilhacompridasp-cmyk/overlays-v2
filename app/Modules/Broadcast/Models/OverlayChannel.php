<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property array<string, bool>|null $layers
 */
#[Unguarded]
class OverlayChannel extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Camadas que o operador liga e desliga (as demais telas ficam sempre ativas). */
    public const LAYERS = ['lineup', 'standings'];

    /** Telas disponíveis para colocar no OBS: slug da URL => rótulo. */
    public const OVERLAYS = [
        'placar' => 'Placar e cronômetro',
        'eventos' => 'Lances (gol, cartão, substituição)',
        'escalacao' => 'Escalação (sob demanda)',
        'tabela' => 'Tabela de classificação (sob demanda)',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['layers' => 'array'];
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function layerOn(string $layer): bool
    {
        return (bool) ($this->layers[$layer] ?? false);
    }

    public function overlayUrl(string $overlay): string
    {
        return route('overlay.show', ['channel' => $this->token, 'overlay' => $overlay]);
    }
}
