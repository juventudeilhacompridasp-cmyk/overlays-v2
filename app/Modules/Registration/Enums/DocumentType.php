<?php

namespace App\Modules\Registration\Enums;

enum DocumentType: string
{
    case IdDocument = 'id_document';
    case Medical = 'medical';
    case GuardianAuthorization = 'guardian_authorization';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IdDocument => 'Documento de identidade',
            self::Medical => 'Atestado médico',
            self::GuardianAuthorization => 'Autorização do responsável',
            self::Other => 'Outro documento',
        };
    }
}
