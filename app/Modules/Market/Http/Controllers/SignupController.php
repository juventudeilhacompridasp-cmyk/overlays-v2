<?php

namespace App\Modules\Market\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Market\Actions\RegisterAthlete;
use App\Modules\Market\Support\MarketOptions;
use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Cadastro público do atleta no Mercado da organização (ADR 0008): maiores de 18 anos, e-mail a confirmar. */
class SignupController extends Controller
{
    public function create(): Response
    {
        $theme = PublicTheme::current();

        return Inertia::render('Market/Register', [
            'theme' => $theme,
            'seo' => [
                'title' => "Mercado de jogadores · {$theme['name']}",
                'description' => 'Cadastre-se como jogador livre e seja encontrado pelas equipes.',
                'url' => route('market.signup'),
                'image' => null,
            ],
            'min_age' => MarketOptions::MIN_AGE,
        ]);
    }

    public function store(Request $request, RegisterAthlete $register): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'birth_date' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears(MarketOptions::MIN_AGE)->toDateString()],
            'accept' => ['accepted'],
        ], [
            'email.unique' => 'Este e-mail já tem cadastro. Entre com a sua conta ou use outro e-mail.',
            'birth_date.before_or_equal' => 'O Mercado é só para maiores de '.MarketOptions::MIN_AGE.' anos.',
            'accept.accepted' => 'É preciso aceitar os termos e a política de privacidade.',
        ], ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'birth_date' => 'data de nascimento']);

        try {
            $user = $register->handle($data['name'], $data['email'], $data['password'], Carbon::parse($data['birth_date']));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['birth_date' => $exception->getMessage()]);
        }

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('athlete.dashboard')->with('success', 'Cadastro feito! Confirme o seu e-mail para poder publicar o perfil.');
    }
}
