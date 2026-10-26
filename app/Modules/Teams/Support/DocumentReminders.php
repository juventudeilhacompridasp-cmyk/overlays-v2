<?php

namespace App\Modules\Teams\Support;

use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;

/**
 * Aviso por e-mail ao gestor da equipe sobre documentos de atletas que vencem em breve (janela do regulamento da edição,
 * `documents.warn_days`) ou venceram nos últimos 30 dias. Um e-mail por equipe e situação; o mesmo conjunto de documentos
 * não é avisado duas vezes (chave pela lista), e mudar a lista (novo vencimento, renovação) gera aviso novo.
 */
final class DocumentReminders
{
    /** Quantos dias depois de vencido o documento ainda entra no aviso (depois disso, é caso para o organizador). */
    public const EXPIRED_DAYS = 30;

    /** @return int quantos e-mails foram enfileirados */
    public static function run(): int
    {
        $sent = 0;

        foreach (Organization::query()->get() as $organization) {
            if (! $organization->hasFeature(Feature::TeamPortal)) {
                continue;
            }

            $sent += (int) CurrentOrganization::using($organization, fn (): int => self::forOrganization($organization));
        }

        return $sent;
    }

    private static function forOrganization(Organization $organization): int
    {
        if (! CommunicationSettings::enabled('manager_documents', $organization)) {
            return 0;
        }

        $today = now()->startOfDay();
        /** @var array<string, array{team: Team, expiring: list<array{0: string, 1: string}>, expired: list<array{0: string, 1: string}>}> $byTeam */
        $byTeam = [];

        $rows = RosterEntry::query()
            ->where('status', RosterStatus::Active)
            ->where('role', RosterRole::Athlete)
            ->whereHas('person.documents')
            ->with(['person.documents', 'entry.team', 'entry.division.season'])
            ->get();

        foreach ($rows as $row) {
            $warnDays = $row->entry->division->ruleSet()->documentWarnDays;

            foreach ($row->person->documents as $document) {
                $days = DocumentValidity::daysLeft($document->expires_on, $today);
                $kind = $days < 0 && $days >= -self::EXPIRED_DAYS ? 'expired' : ($days >= 0 && $days <= $warnDays ? 'expiring' : null);

                if ($kind === null) {
                    continue;
                }

                $teamId = $row->entry->team_id;
                $byTeam[$teamId] ??= ['team' => $row->entry->team, 'expiring' => [], 'expired' => []];
                $when = $document->expires_on->format('d/m/Y');
                $byTeam[$teamId][$kind][] = [
                    $document->getKey(),
                    $row->person->name.' — '.$document->type->label().($kind === 'expired' ? ", venceu em {$when}" : ", vence em {$when}"),
                ];
            }
        }

        $sent = 0;

        foreach ($byTeam as $teamId => $data) {
            foreach (['expired' => ['Documentos vencidos', 'Há documentos de atletas vencidos. Peça a renovação para poder escalá-los.'], 'expiring' => ['Documentos perto de vencer', 'Há documentos de atletas que vencem em breve.']] as $kind => [$subject, $headline]) {
                if ($data[$kind] === []) {
                    continue;
                }

                $ids = array_column($data[$kind], 0);
                sort($ids);

                $sent += ManagerNotifier::send(
                    $organization,
                    $data['team'],
                    'documents',
                    "documents:{$teamId}:{$kind}:".md5(implode(',', $ids)),
                    "{$subject}: {$data['team']->name}",
                    $headline,
                    array_column($data[$kind], 1),
                    '/gestor',
                );
            }
        }

        return $sent;
    }
}
