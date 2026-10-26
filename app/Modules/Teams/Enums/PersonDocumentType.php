<?php

namespace App\Modules\Teams\Enums;

enum PersonDocumentType: string
{
    case MedicalCertificate = 'medical_certificate';
    case Insurance = 'insurance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MedicalCertificate => 'Atestado médico',
            self::Insurance => 'Seguro',
            self::Other => 'Outro documento',
        };
    }
}
