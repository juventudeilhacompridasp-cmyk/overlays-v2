<?php

namespace App\Modules\Market\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Market\Actions\ChangeMarketPhoto;
use App\Modules\Market\Actions\DeleteAthleteAccount;
use App\Modules\Market\Actions\PauseMarketProfile;
use App\Modules\Market\Actions\PublishMarketProfile;
use App\Modules\Market\Actions\SaveMarketProfile;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Painel do atleta (`/atleta`): o próprio perfil no Mercado, a foto e a situação (publicar, pausar, apagar). */
class AthleteController extends Controller
{
    public function show(Request $request): Response
    {
        $profile = $this->profile($request);

        return Inertia::render('Athlete/Dashboard', [
            'profile' => [
                'status' => $profile->status->value,
                'status_label' => $profile->status->label(),
                'sport' => $profile->sport,
                'positions' => $profile->positions ?? [],
                'dominant_foot' => $profile->dominant_foot,
                'height_cm' => $profile->height_cm,
                'level' => $profile->level,
                'bio' => $profile->bio,
                'availability' => $profile->availability ?? [],
                'city' => $profile->city,
                'neighborhood' => $profile->neighborhood,
                'display_name' => $profile->display_name,
                'contact_channel' => $profile->contact_channel,
                'contact_value' => $profile->contact_value,
                'photo_url' => $profile->photoUrl(400),
                'published_at' => $profile->published_at?->format('d/m/Y'),
                'expires_at' => $profile->expires_at?->format('d/m/Y'),
            ],
            'email_verified' => $request->user()->hasVerifiedEmail(),
            'options' => [
                'sports' => collect(Sport::cases())->map(fn (Sport $sport) => [
                    'value' => $sport->value,
                    'label' => $sport->label(),
                    'positions' => MarketOptions::list($sport->profile()->positions),
                ])->values(),
                'days' => MarketOptions::list(MarketOptions::DAYS),
                'periods' => MarketOptions::list(MarketOptions::PERIODS),
                'levels' => MarketOptions::list(MarketOptions::LEVELS),
                'feet' => MarketOptions::list(MarketOptions::FEET),
                'channels' => MarketOptions::list(MarketOptions::CHANNELS),
            ],
            'revalidate_days' => MarketOptions::REVALIDATE_DAYS,
        ]);
    }

    public function update(Request $request, SaveMarketProfile $save): RedirectResponse
    {
        $profile = $this->profile($request);

        $sport = Sport::tryFrom((string) $request->input('sport'));
        $positions = $sport !== null ? array_keys($sport->profile()->positions) : [];

        $data = $request->validate([
            'sport' => ['nullable', Rule::enum(Sport::class)],
            'positions' => ['nullable', 'array', 'max:3'],
            'positions.*' => ['string', Rule::in($positions)],
            'dominant_foot' => ['nullable', Rule::in(array_keys(MarketOptions::FEET))],
            'height_cm' => ['nullable', 'integer', 'between:100,250'],
            'level' => ['nullable', Rule::in(array_keys(MarketOptions::LEVELS))],
            'bio' => ['nullable', 'string', 'max:600'],
            'availability' => ['nullable', 'array'],
            'availability.*' => ['array'],
            'availability.*.*' => ['string', Rule::in(array_keys(MarketOptions::PERIODS))],
            'city' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:60'],
            'contact_channel' => ['nullable', Rule::in(array_keys(MarketOptions::CHANNELS))],
            'contact_value' => ['nullable', 'string', 'max:120'],
        ], [], [
            'sport' => 'esporte', 'positions' => 'posições', 'dominant_foot' => 'pé dominante', 'height_cm' => 'altura', 'level' => 'nível',
            'bio' => 'apresentação', 'availability' => 'disponibilidade', 'city' => 'cidade', 'neighborhood' => 'bairro',
            'display_name' => 'apelido', 'contact_channel' => 'canal de contato', 'contact_value' => 'contato',
        ]);

        $data['availability'] = $this->cleanAvailability($data['availability'] ?? []);
        $data['contact_value'] = $this->cleanContact($data['contact_channel'] ?? null, $data['contact_value'] ?? null);
        $data['positions'] = array_values(array_unique($data['positions'] ?? []));

        $save->handle($profile, $data);

        return back()->with('success', 'Perfil salvo.');
    }

    public function photo(Request $request, ChangeMarketPhoto $change): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64']], [], ['photo' => 'foto']);

        try {
            $change->handle($this->profile($request), $request->file('photo'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['photo' => $exception->getMessage()]);
        }

        return back()->with('success', 'Foto atualizada.');
    }

    public function removePhoto(Request $request, ChangeMarketPhoto $change): RedirectResponse
    {
        $change->handle($this->profile($request), null);

        return back()->with('success', 'Foto removida.');
    }

    public function publish(Request $request, PublishMarketProfile $publish): RedirectResponse
    {
        try {
            $publish->handle($this->profile($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Perfil publicado no Mercado.');
    }

    public function pause(Request $request, PauseMarketProfile $pause): RedirectResponse
    {
        $pause->handle($this->profile($request));

        return back()->with('success', 'Perfil pausado: os gestores não o veem mais.');
    }

    public function destroy(Request $request, DeleteAthleteAccount $delete): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [], ['password' => 'senha']);

        $user = $request->user();
        $delete->handle($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function profile(Request $request): MarketProfile
    {
        return MarketProfile::query()->where('user_id', $request->user()->getKey())->firstOrFail();
    }

    /**
     * Só dias e períodos conhecidos, sem repetir nem deixar dia vazio.
     *
     * @param  array<string, mixed>  $availability
     * @return array<string, list<string>>
     */
    private function cleanAvailability(array $availability): array
    {
        $clean = [];

        foreach (array_keys(MarketOptions::DAYS) as $day) {
            $periods = array_values(array_intersect(array_keys(MarketOptions::PERIODS), (array) ($availability[$day] ?? [])));

            if ($periods !== []) {
                $clean[$day] = $periods;
            }
        }

        return $clean;
    }

    /** E-mail em minúsculas; WhatsApp só com dígitos (10 a 13). Inválido vira erro de validação. */
    private function cleanContact(?string $channel, ?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if ($channel === 'whatsapp') {
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if (strlen($digits) < 10 || strlen($digits) > 13) {
                throw ValidationException::withMessages(['contact_value' => 'Informe o WhatsApp com DDD.']);
            }

            return $digits;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['contact_value' => 'Informe um e-mail de contato válido.']);
        }

        return strtolower($value);
    }
}
