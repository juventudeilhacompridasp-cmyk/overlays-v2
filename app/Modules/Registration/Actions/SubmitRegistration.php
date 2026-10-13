<?php

namespace App\Modules\Registration\Actions;

use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Registration\Support\EnsureEditable;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
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

        if ($registration->isSupplement()) {
            // Reforço: só quem está sendo incluído; os mínimos valem para a inscrição inicial. O teto vale para o elenco inteiro.
            if ($people->isEmpty()) {
                $problems[] = 'Cadastre pelo menos uma pessoa para incluir no elenco.';
            }

            $current = RosterEntry::query()->where('entry_id', $registration->entry_id)->where('role', RosterRole::Athlete)->where('status', RosterStatus::Active)->count();

            if ($current + $athletes->count() > $settings['max_athletes']) {
                $problems[] = "O elenco já tem {$current} atletas e o máximo da edição é {$settings['max_athletes']}.";
            }
        } else {
            if ($athletes->count() < $settings['min_athletes']) {
                $problems[] = "Inscreva pelo menos {$settings['min_athletes']} atletas (hoje: {$athletes->count()}).";
            }

            if ($people->where('role', '!=', RosterRole::Athlete)->isEmpty()) {
                $problems[] = 'Informe pelo menos um membro da comissão (técnico, auxiliar, massagista ou dirigente).';
            }
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

        if ($registration->isSupplement()) {
            $reason = $registration->season->rosterChangesClosedReason();

            if ($reason !== null) {
                throw new InvalidArgumentException($reason);
            }
        }

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
