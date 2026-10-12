<?php

namespace App\Modules\Registration\Actions;

use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Registration\Support\EnsureEditable;
use App\Modules\Teams\Enums\RosterRole;
use InvalidArgumentException;

/**
 * Envia o pedido para análise da organização. Só segue se estiver completo; `problems()` lista o que falta
 * (também usada na tela da equipe como lista de pendências).
 */
final class SubmitRegistration
{
    /** @return list<string> */
    public function problems(Registration $registration): array
    {
        $settings = $registration->season->registrationSettings();
        $people = $registration->people()->with('documents')->get();
        $athletes = $people->where('role', RosterRole::Athlete);
        $problems = [];

        if ($athletes->count() < $settings['min_athletes']) {
            $problems[] = "Inscreva pelo menos {$settings['min_athletes']} atletas (hoje: {$athletes->count()}).";
        }

        if ($people->where('role', '!=', RosterRole::Athlete)->isEmpty()) {
            $problems[] = 'Informe pelo menos um membro da comissão (técnico, auxiliar, massagista ou dirigente).';
        }

        if ($settings['require_documents']) {
            $missing = $athletes->filter(fn (RegistrationPerson $person) => $person->documents->isEmpty())->pluck('name');

            if ($missing->isNotEmpty()) {
                $problems[] = 'Faltam documentos de: '.$missing->implode(', ').'.';
            }
        }

        $unauthorized = $people->filter(fn (RegistrationPerson $person) => $person->isMinor() && $person->guardian_consent_at === null)->pluck('name');

        if ($unauthorized->isNotEmpty()) {
            $problems[] = 'Falta a autorização do responsável de: '.$unauthorized->implode(', ').'.';
        }

        return $problems;
    }

    public function handle(Registration $registration): Registration
    {
        EnsureEditable::check($registration);

        $problems = $this->problems($registration);

        if ($problems !== []) {
            throw new InvalidArgumentException('Ainda há pendências: '.implode(' ', $problems));
        }

        // Cada envio passa por revisão completa: o que foi aprovado/recusado antes volta a pendente (as notas ficam).
        $registration->people()->update(['review_status' => ReviewStatus::Pending]);
        $registration->update(['status' => RegistrationStatus::Submitted, 'submitted_at' => now()]);

        return $registration;
    }
}
