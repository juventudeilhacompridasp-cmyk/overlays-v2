<?php

namespace App\Modules\Status\Actions;

use App\Modules\Status\Mail\SubscriptionConfirm;
use App\Modules\Status\Models\Component;
use App\Modules\Status\Models\Subscriber;
use App\Modules\Status\Support\StatusLinks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Inscrição nos avisos da status page: pedir (dupla confirmação), confirmar e cancelar. Nunca revela se um e-mail já existe. */
class SubscriberActions
{
    /** Minutos mínimos entre dois reenvios do pedido de confirmação ao mesmo e-mail. */
    private const RESEND_MINUTES = 5;

    public function request(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $subscriber = Subscriber::query()->where('email', $email)->first();

        // Já confirmado: silêncio (não confirmamos nem negamos que o e-mail está na lista).
        if ($subscriber?->isConfirmed()) {
            return;
        }

        if ($subscriber !== null && $subscriber->updated_at !== null && $subscriber->updated_at->gt(now()->subMinutes(self::RESEND_MINUTES))) {
            return;
        }

        if ($subscriber === null) {
            $subscriber = Subscriber::query()->create(['email' => $email, 'token' => Str::random(40)]);
        } else {
            $subscriber->touch();
        }

        try {
            Mail::to($email)->send(new SubscriptionConfirm(StatusLinks::confirm($subscriber->token), StatusLinks::unsubscribe($subscriber->token)));
        } catch (Throwable $e) {
            Log::warning('Status: não foi possível enviar a confirmação de inscrição.', ['erro' => class_basename($e)]);
        }
    }

    public function confirm(string $token): bool
    {
        $subscriber = Subscriber::query()->where('token', $token)->first();

        if ($subscriber === null) {
            return false;
        }

        if ($subscriber->isConfirmed()) {
            return true;
        }

        if ($subscriber->created_at !== null && $subscriber->created_at->lt(now()->subDays(Subscriber::PENDING_DAYS))) {
            return false;
        }

        $subscriber->update(['confirmed_at' => now()]);

        return true;
    }

    public function cancel(string $token): bool
    {
        return Subscriber::query()->where('token', $token)->delete() > 0;
    }

    /**
     * Componentes e a escolha atual de quem já confirmou (null se o token não vale ou ainda não foi confirmado).
     *
     * @return list<array{key: string, name: string, selected: bool}>|null
     */
    public function preferences(string $token): ?array
    {
        $subscriber = Subscriber::query()->where('token', $token)->first();

        if ($subscriber === null || ! $subscriber->isConfirmed()) {
            return null;
        }

        return Component::query()->where('visible', true)->orderBy('sort')->get()
            ->map(fn (Component $component) => ['key' => $component->key, 'name' => $component->name, 'selected' => $subscriber->wantsAny([$component->key])])
            ->values()->all();
    }

    /**
     * Guarda os componentes escolhidos (só chaves de componentes visíveis; escolher todos volta ao padrão "todos").
     * Devolve false se o token não vale ou não há nenhuma escolha válida.
     *
     * @param  list<string>  $keys
     */
    public function savePreferences(string $token, array $keys): bool
    {
        $subscriber = Subscriber::query()->where('token', $token)->first();

        if ($subscriber === null || ! $subscriber->isConfirmed()) {
            return false;
        }

        $all = Component::query()->where('visible', true)->pluck('key')->all();
        $valid = array_values(array_intersect($all, $keys));

        if ($valid === []) {
            return false;
        }

        $subscriber->update(['components' => count($valid) === count($all) ? null : $valid]);

        return true;
    }

    public function exists(string $token): bool
    {
        return Subscriber::query()->where('token', $token)->exists();
    }
}
