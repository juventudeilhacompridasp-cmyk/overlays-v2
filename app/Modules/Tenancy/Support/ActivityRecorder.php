<?php

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\ApiDocs\Support\OpenApiBuilder;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Grava o registro de atividades (auditoria no banco). Duas origens: a REQUISIÇÃO de escrita de um usuário logado
 * (`request`: rota, assunto, resultado e só os NOMES dos campos enviados) e a MUDANÇA de um jogo ou o lançamento de um lance
 * (`change`: antes/depois). Nunca guarda IP, CPF, senha nem valores digitados. Falha de gravação só vai ao log: auditar nunca
 * impede o trabalho de quem opera o jogo.
 */
final class ActivityRecorder
{
    /** Campos do jogo cuja mudança é registrada com antes/depois. */
    public const GAME_FIELDS = ['home_score', 'away_score', 'home_penalties', 'away_penalties', 'status', 'scheduled_at', 'venue_id', 'started_at', 'finished_at'];

    /** Nomes de campo que nunca aparecem no registro (nem o nome). */
    private const HIDDEN_FIELDS = '/password|passwd|token|secret|_method|^code$|recovery/i';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $catalog = null;

    public static function request(Request $request, Response $response): void
    {
        $user = $request->user();
        $route = $request->route();

        if (! ActivitySettings::enabled() || ! $user instanceof User || $route === null || $request->isMethodSafe() || ! is_string($route->getName())) {
            return;
        }

        $outcome = self::outcome($request, $response);

        if ($outcome !== 'ok' && ! ActivitySettings::logRefused()) {
            return;
        }

        $name = $route->getName();
        [$type, $id, $label, $gameId] = self::subject($route->parameters());

        self::write($user, [
            'area' => self::area($name),
            'kind' => 'request',
            'action' => $name,
            'method' => $request->method(),
            'summary' => self::summary($name),
            'subject_type' => $type,
            'subject_id' => $id,
            'subject_label' => $label,
            'game_id' => $gameId,
            'outcome' => $outcome,
            'status' => $response->getStatusCode(),
            'fields' => self::fields($request, $name),
        ]);
    }

    /** Mudança de jogo feita por quem está logado (placar, situação, horário, local); chamada de `Game::updated`. */
    public static function gameChanged(Game $game): void
    {
        $user = Auth::user();

        if (! ActivitySettings::enabled() || ! $user instanceof User || ! $game->wasChanged(self::GAME_FIELDS)) {
            return;
        }

        $changes = [];

        foreach (self::GAME_FIELDS as $field) {
            if ($game->wasChanged($field)) {
                $changes[$field] = [self::plain($game->getOriginal($field)), self::plain($game->getAttribute($field))];
            }
        }

        self::write($user, [
            'area' => 'panel',
            'kind' => 'change',
            'action' => 'game.updated',
            'summary' => 'Jogo alterado: '.implode(', ', array_map(self::fieldLabel(...), array_keys($changes))),
            'subject_type' => 'game',
            'subject_id' => (string) $game->getKey(),
            'subject_label' => self::gameLabel($game),
            'game_id' => (string) $game->getKey(),
            'outcome' => 'ok',
            'changes' => $changes,
        ]);
    }

    /** Lance lançado na súmula por quem está logado; chamada de `GameEvent::created`. */
    public static function eventRecorded(GameEvent $event): void
    {
        $user = Auth::user();

        if (! ActivitySettings::enabled() || ! $user instanceof User) {
            return;
        }

        $game = $event->game;
        $minute = $event->minute !== null ? " aos {$event->minute}'" : '';

        self::write($user, [
            'area' => 'panel',
            'kind' => 'change',
            'action' => 'game-event.created',
            'summary' => 'Lance lançado: '.$event->type->label().$minute,
            'subject_type' => 'game',
            'subject_id' => (string) $event->game_id,
            'subject_label' => $game instanceof Game ? self::gameLabel($game) : null,
            'game_id' => (string) $event->game_id,
            'outcome' => 'ok',
            'changes' => ['type' => [null, $event->type->value], 'period' => [null, $event->period], 'minute' => [null, $event->minute]],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private static function write(User $user, array $data): void
    {
        try {
            $organization = CurrentOrganization::get();

            if ($organization === null) {
                return;
            }

            ActivityLog::query()->create($data + [
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'actor_name' => mb_substr((string) $user->name, 0, 120),
                'actor_role' => $user->accessLabel(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Registro de atividade não gravado', ['action' => $data['action'] ?? null, 'error' => $e->getMessage()]);
        }
    }

    private static function outcome(Request $request, Response $response): string
    {
        $status = $response->getStatusCode();

        if ($status >= 500) {
            return 'failed';
        }

        if ($status >= 400) {
            return 'refused';
        }

        $session = $request->hasSession() ? $request->session() : null;

        return $session !== null && ($session->has('errors') || $session->has('error')) ? 'refused' : 'ok';
    }

    private static function area(string $routeName): string
    {
        return match (true) {
            str_starts_with($routeName, 'panel.') => 'panel',
            str_starts_with($routeName, 'manager.') => 'manager',
            str_starts_with($routeName, 'athlete.') => 'athlete',
            str_starts_with($routeName, 'platform.') => 'platform',
            default => 'account',
        };
    }

    private static function summary(string $routeName): string
    {
        self::$catalog ??= OpenApiBuilder::catalog();
        $summary = self::$catalog[$routeName]['summary'] ?? null;

        return mb_substr(is_string($summary) && $summary !== '' ? $summary : 'Ação '.$routeName, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: string|null}
     */
    private static function subject(array $parameters): array
    {
        foreach ($parameters as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $gameId = match (true) {
                $parameter instanceof Game => (string) $parameter->getKey(),
                is_string($parameter->getAttribute('game_id')) => $parameter->getAttribute('game_id'),
                default => null,
            };

            return [Str::snake(class_basename($parameter)), (string) $parameter->getKey(), self::label($parameter), $gameId];
        }

        return [null, null, null, null];
    }

    private static function label(Model $model): ?string
    {
        if ($model instanceof Game) {
            return self::gameLabel($model);
        }

        foreach (['name', 'title'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 200);
            }
        }

        return null;
    }

    private static function gameLabel(Game $game): ?string
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);
        $home = $game->homeEntry?->team?->name;
        $away = $game->awayEntry?->team?->name;

        return $home !== null && $away !== null ? mb_substr("{$home} x {$away}", 0, 200) : null;
    }

    /** @return list<string>|null */
    private static function fields(Request $request, string $routeName): ?array
    {
        $keys = array_values(array_filter(array_map('strval', array_keys($request->except(['_token']))), fn (string $key): bool => preg_match(self::HIDDEN_FIELDS, $key) !== 1));

        if ($routeName === 'panel.games.sync') {
            $commands = $request->input('commands', $request->input('items'));
            $keys[] = 'itens: '.(is_array($commands) ? count($commands) : 0);
        }

        return $keys === [] ? null : array_slice($keys, 0, 40);
    }

    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('c'),
            is_scalar($value) || $value === null => $value,
            default => null,
        };
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'home_score' => 'placar do mandante',
            'away_score' => 'placar do visitante',
            'home_penalties' => 'pênaltis do mandante',
            'away_penalties' => 'pênaltis do visitante',
            'status' => 'situação',
            'scheduled_at' => 'horário',
            'venue_id' => 'local',
            'started_at' => 'início',
            'finished_at' => 'encerramento',
            default => $field,
        };
    }
}
