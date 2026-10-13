<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketOptions;
use App\Modules\Tenancy\Actions\AcceptLegalDocuments;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cadastro público do atleta (ADR 0008): usuário novo com papel `athlete` na organização atual e perfil em rascunho.
 * Menores de 18 anos não entram. O e-mail fica sem verificação (a confirmação é exigida só para publicar) e o aceite dos
 * termos vigentes é gravado no ato.
 */
final class RegisterAthlete
{
    public function __construct(private readonly AcceptLegalDocuments $accept) {}

    public function handle(string $name, string $email, string $password, Carbon $birthDate): User
    {
        if ($birthDate->diffInYears(now(), true) < MarketOptions::MIN_AGE) {
            throw new InvalidArgumentException('O Mercado é só para maiores de '.MarketOptions::MIN_AGE.' anos.');
        }

        $user = DB::transaction(function () use ($name, $email, $password, $birthDate): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);

            Membership::query()->create(['user_id' => $user->getKey(), 'role' => Role::Athlete]);
            MarketProfile::query()->create([
                'user_id' => $user->getKey(),
                'status' => ProfileStatus::Draft,
                'birth_date' => $birthDate->toDateString(),
            ]);

            return $user;
        });

        $this->accept->handle($user);

        return $user;
    }
}
