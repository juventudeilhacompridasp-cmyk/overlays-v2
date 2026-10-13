<?php

namespace App\Modules\Arts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Arts\Enums\ArtKind;
use App\Modules\Arts\Models\ArtAsset;
use App\Modules\Arts\Models\ArtTemplate;
use App\Modules\Arts\Support\ArtData;
use App\Modules\Arts\Support\ArtFormats;
use App\Modules\Arts\Support\ArtPresets;
use App\Modules\Arts\Support\ArtStyle;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\Enums\Ability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel > Artes (ADR 0009): biblioteca de modelos, escolha da partida e editor com prévia ao vivo. A arte é desenhada no
 * navegador (canvas) com dados da partida montados por `ArtData`; o servidor só entrega dados e guarda os modelos.
 */
class ArtController extends Controller
{
    private const GAME_RELATIONS = ['homeEntry.team', 'awayEntry.team', 'division.season.competition', 'stage'];

    public function index(): Response
    {
        $upcoming = Game::query()->with(self::GAME_RELATIONS)
            ->whereIn('status', [GameStatus::Scheduled, GameStatus::InProgress, GameStatus::Postponed])
            ->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->limit(12)->get();

        $recent = Game::query()->with(self::GAME_RELATIONS)
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->orderByDesc('finished_at')->orderByDesc('id')->limit(12)->get();

        return Inertia::render('Panel/Arts/Index', [
            'upcoming' => $upcoming->map(fn (Game $game) => $this->gameCard($game))->values(),
            'recent' => $recent->map(fn (Game $game) => $this->gameCard($game))->values(),
            'templates' => ArtTemplate::query()->orderBy('name')->get()->map(fn (ArtTemplate $template) => $template->toCard())->values(),
            'presets' => ArtPresets::all(),
            'formats' => ArtFormats::options(),
            'kinds' => $this->kinds(),
            'assets' => $this->assets(),
            'asset_kinds' => $this->list(ArtAsset::KINDS),
            'can_save' => Gate::allows(Ability::ManageCompetitions->value),
        ]);
    }

    public function editor(Request $request): Response|RedirectResponse
    {
        $game = Game::query()->with(self::GAME_RELATIONS)->find((string) $request->query('game'));

        if ($game === null) {
            return redirect()->route('panel.arts.index')->with('error', 'Escolha uma partida para criar a arte.');
        }

        $kind = ArtKind::tryFrom((string) $request->query('kind')) ?? ($game->status->isDecided() ? ArtKind::Result : ArtKind::Preview);
        $template = ArtTemplate::query()->find((string) $request->query('template'));
        $preset = $template === null ? (ArtPresets::find((string) $request->query('preset')) ?? ArtPresets::all()[0]) : null;

        $base = $template !== null
            ? $template->toCard()
            : [
                'id' => null,
                'name' => $preset['name'] ?? '',
                'format' => $preset['format'] ?? 'square',
                ...ArtFormats::resolve($preset['format'] ?? 'square'),
                'style' => $preset['style'] ?? ArtStyle::defaults(),
            ];

        $format = $request->query('format') !== null
            ? ArtFormats::resolve($request->query('format'), $request->query('w'), $request->query('h'))
            : ArtFormats::resolve($base['format'], $base['width'], $base['height']);

        return Inertia::render('Panel/Arts/Editor', [
            'game' => $this->gameCard($game),
            'kind' => $kind->value,
            'kinds' => $this->kinds(),
            'data' => ArtData::for($game, $kind, (string) $request->query('scope', 'day')),
            'initial' => [
                'template_id' => $template?->getKey(),
                'preset' => $preset['key'] ?? null,
                'name' => $base['name'],
                'format' => $format,
                'style' => $base['style'],
            ],
            'templates' => ArtTemplate::query()->orderBy('name')->get()->map(fn (ArtTemplate $item) => $item->toCard())->values(),
            'presets' => ArtPresets::all(),
            'formats' => ArtFormats::options(),
            'options' => [
                'fonts' => $this->list(ArtStyle::FONTS),
                'variants' => $this->list(ArtStyle::VARIANTS),
                'patterns' => $this->list(ArtStyle::PATTERNS),
                'crest_shapes' => $this->list(ArtStyle::CREST_SHAPES),
                'weights' => $this->list(ArtStyle::WEIGHTS),
                'elements' => $this->list(ArtStyle::ELEMENTS),
                'platforms' => $this->list(ArtStyle::PLATFORMS),
                'limits' => ['min' => ArtFormats::MIN, 'max' => ArtFormats::MAX],
            ],
            'assets' => $this->assets(),
            'asset_kinds' => $this->list(ArtAsset::KINDS),
            'can_save' => Gate::allows(Ability::ManageCompetitions->value),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function assets(): array
    {
        return ArtAsset::query()->orderBy('kind')->orderBy('name')->get()->map(fn (ArtAsset $asset) => $asset->toCard())->values()->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function kinds(): array
    {
        return array_map(fn (ArtKind $kind) => ['value' => $kind->value, 'label' => $kind->label()], ArtKind::cases());
    }

    /**
     * @param  array<string, string>  $items
     * @return list<array{value: string, label: string}>
     */
    private function list(array $items): array
    {
        return array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys($items), array_values($items));
    }

    /** @return array<string, mixed> */
    private function gameCard(Game $game): array
    {
        return [
            'id' => $game->getKey(),
            'home' => $game->homeEntry->team->name ?? 'A definir',
            'away' => $game->awayEntry->team->name ?? 'A definir',
            'home_score' => $game->home_score,
            'away_score' => $game->away_score,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'decided' => $game->status->isDecided(),
            'scheduled_at' => $game->scheduled_at?->format('d/m/Y H:i'),
            'competition' => $game->division->season->competition->name,
            'phase' => $game->stage->name,
        ];
    }
}
