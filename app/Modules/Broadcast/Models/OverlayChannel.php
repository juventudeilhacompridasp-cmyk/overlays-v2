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
 * @property array<string, bool|array<string, bool>>|null $layers
 * @property array<string, mixed>|null $appearance
 * @property array<string, mixed>|null $lineup_settings
 * @property array<string, mixed>|null $sponsor_settings
 * @property array<string, mixed>|null $lower_third_settings
 * @property array<string, mixed>|null $screen_settings
 * @property array<string, mixed>|null $automation_settings
 * @property array<string, mixed>|null $stats_settings
 * @property string|null $control_token_hash
 */
#[Unguarded]
class OverlayChannel extends Model
{
    use BelongsToOrganization, HasUlids;

    /** @var list<string> */
    protected $hidden = ['control_token_hash'];

    /**
     * Camadas que o operador liga e desliga ao vivo. Escalação e tabela começam ocultas; placar e lances começam no ar (o
     * operador pode tirá-los da tela na hora). Telas sem camada (patrocinadores) têm a própria chave de ligar/desligar.
     */
    public const LAYERS = ['lineup', 'standings', 'scoreboard', 'lower_third'];

    /** Telas de campeonato que o operador pode mostrar/ocultar ao vivo (URLs com `live=1`); começam ocultas. */
    public const CHAMPIONSHIP_VIEWS = ['standings', 'fixtures', 'results', 'scorers', 'discipline', 'matchcard', 'ticker', 'champion'];

    /** Telas extras sob demanda (começam ocultas): cartela pré-jogo, comparativo, cartela de transmissão, resumo do jogo e chaveamento. */
    public const SCREENS = ['pregame', 'compare', 'cartela', 'summary', 'bracket', 'social', 'sponsor_bar', 'stats', 'shootout'];

    /** Camadas e telas que o PVW do Modo operador pode mostrar antes de irem ao ar (`withPreview`). */
    public const PREVIEWABLE = ['lineup', 'standings', 'sponsors', 'pregame', 'compare', 'cartela', 'summary', 'bracket', 'social', 'sponsor_bar', 'stats', 'shootout'];

    /** Camadas que valem como ligadas quando o operador nunca mexeu nelas. */
    public const DEFAULT_ON = ['scoreboard', 'lower_third'];

    /** Telas disponíveis para colocar no OBS: slug da URL => rótulo. */
    public const OVERLAYS = [
        'placar' => 'Placar e cronômetro',
        'eventos' => 'Lances (gol, cartão, substituição)',
        'escalacao' => 'Escalação (sob demanda)',
        'tabela' => 'Tabela de classificação (sob demanda)',
        'patrocinadores' => 'Patrocinadores (rotação de logotipos)',
        'prejogo' => 'Cartela pré-jogo com contagem regressiva (sob demanda)',
        'comparativo' => 'Comparativo de equipes (sob demanda)',
        'cartela' => 'Cartela de transmissão (sob demanda)',
        'resumo' => 'Resumo do jogo: intervalo e fim (sob demanda)',
        'chaveamento' => 'Chaveamento do mata-mata (sob demanda)',
        'social' => 'Chamada para redes sociais com QR code (sob demanda)',
        'barra-patrocinio' => 'Barra de patrocinadores 1500 x 200 (sob demanda)',
        'estatisticas' => 'Estatísticas da partida (sob demanda)',
        'penaltis' => 'Disputa de pênaltis (cobrança a cobrança)',
        'todos' => 'Todas as camadas numa só fonte (placar, lances, escalação, tabela, patrocinadores, estatísticas)',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['layers' => 'array', 'appearance' => 'array', 'lineup_settings' => 'array', 'sponsor_settings' => 'array', 'lower_third_settings' => 'array', 'screen_settings' => 'array', 'automation_settings' => 'array', 'stats_settings' => 'array'];
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public static function newControlToken(): string
    {
        return Str::random(64);
    }

    public static function controlTokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function acceptsControlToken(string $token): bool
    {
        return $this->control_token_hash !== null
            && hash_equals($this->control_token_hash, self::controlTokenHash($token));
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
        return (bool) ($this->layers[$layer] ?? in_array($layer, self::DEFAULT_ON, true));
    }

    /** @return array<string, bool> todas as camadas, com o padrão de cada uma onde o operador não mexeu */
    public function layerStates(): array
    {
        return array_combine(self::LAYERS, array_map(fn (string $layer) => $this->layerOn($layer), self::LAYERS));
    }

    /** @return array<string, bool> cada tela extra: no ar (true) ou oculta (padrão) */
    public function screensLive(): array
    {
        $saved = is_array($this->layers['screens'] ?? null) ? $this->layers['screens'] : [];

        return array_combine(self::SCREENS, array_map(fn (string $screen) => ($saved[$screen] ?? false) === true, self::SCREENS));
    }

    /** @return array<string, bool> cada tela de campeonato: no ar (true) ou oculta (padrão) */
    public function championshipLive(): array
    {
        $saved = is_array($this->layers['championship'] ?? null) ? $this->layers['championship'] : [];

        return array_combine(self::CHAMPIONSHIP_VIEWS, array_map(fn (string $view) => ($saved[$view] ?? false) === true, self::CHAMPIONSHIP_VIEWS));
    }

    /** @return array<string, bool> overlays livres (Builder) no ar neste canal: ID => true */
    public function customLive(): array
    {
        $saved = is_array($this->layers['custom'] ?? null) ? $this->layers['custom'] : [];

        return array_filter(array_map('boolval', $saved));
    }

    /**
     * Cópia SÓ EM MEMÓRIA do canal com uma camada ou tela ligada, para o PVW montar o estado "como ficaria no ar".
     * Nunca é salva; chave desconhecida devolve o canal como está.
     */
    public function withPreview(string $key): static
    {
        if (! in_array($key, self::PREVIEWABLE, true)) {
            return $this;
        }

        $copy = clone $this;
        $layers = is_array($copy->layers) ? $copy->layers : [];

        if ($key === 'sponsors') {
            $copy->sponsor_settings = [...(is_array($copy->sponsor_settings) ? $copy->sponsor_settings : []), 'enabled' => true];
        } elseif (in_array($key, self::LAYERS, true)) {
            $layers[$key] = true;
        } else {
            $layers['screens'] = [...(is_array($layers['screens'] ?? null) ? $layers['screens'] : []), $key => true];
        }

        $copy->layers = $layers;

        return $copy;
    }

    public function customUrl(string $item): string
    {
        return route('overlay.custom', ['channel' => $this->token, 'item' => $item]);
    }

    public function overlayUrl(string $overlay): string
    {
        return route('overlay.show', ['channel' => $this->token, 'overlay' => $overlay]);
    }
}
