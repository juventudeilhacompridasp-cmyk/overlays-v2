<?php

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\LegalType;
use App\Modules\Tenancy\Models\LegalAcceptance;
use App\Modules\Tenancy\Models\LegalDocument;
use Illuminate\Support\Collection;

/** Textos legais da organização atual: a última versão publicada ou, enquanto não há, o texto-modelo. */
final class LegalDocuments
{
    public static function current(LegalType $type): ?LegalDocument
    {
        return LegalDocument::query()->where('type', $type->value)->orderByDesc('version')->first();
    }

    /**
     * @return array{type: string, title: string, body: string, version: int|null, published_at: string|null, is_default: bool, document_id: string|null}
     */
    public static function view(LegalType $type): array
    {
        $document = self::current($type);

        return [
            'type' => $type->value,
            'title' => $type->label(),
            'body' => $document?->body ?? self::defaultBody($type),
            'version' => $document?->version,
            'published_at' => $document?->published_at->format('d/m/Y'),
            'is_default' => $document === null,
            'document_id' => $document?->getKey(),
        ];
    }

    public static function defaultBody(LegalType $type): string
    {
        $organization = CurrentOrganization::get();
        $settings = self::settings();

        return LegalDefaults::body($type, $organization?->name ?? (string) config('app.name'), $settings['controller_name'], $settings['contact_email']);
    }

    /**
     * @return array{controller_name: string|null, contact_email: string|null}
     */
    public static function settings(): array
    {
        $legal = CurrentOrganization::get()?->settings['legal'] ?? [];

        return [
            'controller_name' => isset($legal['controller_name']) && is_string($legal['controller_name']) && $legal['controller_name'] !== '' ? $legal['controller_name'] : null,
            'contact_email' => isset($legal['contact_email']) && is_string($legal['contact_email']) && $legal['contact_email'] !== '' ? $legal['contact_email'] : null,
        ];
    }

    /**
     * Versões publicadas (a mais recente de cada tipo) que o usuário ainda não aceitou.
     *
     * @return Collection<int, LegalDocument>
     */
    public static function pendingFor(User $user): Collection
    {
        $accepted = LegalAcceptance::query()->where('user_id', $user->getKey())->pluck('legal_document_id')->all();

        return collect(LegalType::cases())
            ->map(fn (LegalType $type) => self::current($type))
            ->filter(fn (?LegalDocument $document) => $document !== null && ! in_array($document->getKey(), $accepted, true))
            ->values();
    }
}
