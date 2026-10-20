<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Cadastro público fechado por padrão: usuários são criados por um administrador (Painel > Usuários).
     * Só abre com `OPEN_REGISTRATION=true` ou, enquanto a instalação não tem nenhum usuário, com `ALLOW_FIRST_USER_SETUP`
     * (o primeiro vira administrador). Em produção o padrão é desligado: o primeiro administrador nasce por
     * `php artisan platform:create-admin`, para ninguém tomar a instalação chegando primeiro em /register.
     */
    public static function isOpen(): bool
    {
        return (bool) config('platform.open_registration') || ((bool) config('platform.first_user_setup') && ! User::query()->exists());
    }

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        abort_unless(self::isOpen(), 403, 'O cadastro está fechado. Peça a um administrador para criar seu acesso.');

        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::isOpen(), 403, 'O cadastro está fechado. Peça a um administrador para criar seu acesso.');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
