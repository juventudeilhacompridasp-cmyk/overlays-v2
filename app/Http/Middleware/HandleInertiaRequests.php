<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\Enums\Ability;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->role()?->value,
                'role_label' => $request->user()?->role()?->label(),
                // Só para esconder botões; quem decide de verdade é o servidor (middleware `panel.can`).
                'can' => collect(Ability::cases())->mapWithKeys(fn (Ability $ability) => [$ability->value => $request->user()?->role()?->can($ability) ?? false])->all(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
