<?php

namespace App\Modules\Registration\Actions;

use App\Modules\Registration\Enums\DocumentType;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationDocument;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Registration\Support\EnsureEditable;
use App\Support\PlatformLimits;
use App\Support\Uploads\UploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Documentos do pedido (RG, atestado, autorização do responsável). Dado pessoal: disco PRIVADO, nunca público;
 * o painel os abre por URL temporária assinada. Os arquivos ficam como enviados (PDF ou imagem).
 */
final class ManageRegistrationDocuments
{
    public const DISK = 's3-private';

    public const MAX_PER_PERSON = 6;

    private const EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function attach(RegistrationPerson $person, UploadedFile $file, DocumentType $type): RegistrationDocument
    {
        $registration = $person->registration;
        EnsureEditable::check($registration);

        if ($person->documents()->count() >= self::MAX_PER_PERSON) {
            throw new InvalidArgumentException('No máximo '.self::MAX_PER_PERSON.' documentos por pessoa.');
        }

        $mime = (string) $file->getMimeType();

        if (! isset(self::EXTENSIONS[$mime])) {
            throw new InvalidArgumentException('Envie um PDF ou uma imagem (JPG, PNG ou WebP).');
        }

        $content = (string) file_get_contents($file->getRealPath());
        UploadScanner::check($content, $mime);

        $path = "organizations/{$registration->organization_id}/registrations/{$registration->getKey()}/{$person->getKey()}/".bin2hex(random_bytes(8)).'.'.self::EXTENSIONS[$mime];

        Storage::disk(self::DISK)->put($path, $content, ['ContentType' => $mime]);

        return RegistrationDocument::query()->create([
            'organization_id' => $registration->organization_id,
            'registration_person_id' => $person->getKey(),
            'type' => $type,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 150),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ]);
    }

    public function remove(RegistrationDocument $document): void
    {
        EnsureEditable::check($document->person->registration);

        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();
    }

    /** Apaga os arquivos de uma pessoa (ao removê-la) ou do pedido inteiro (ao excluí-lo). */
    public function purge(Registration|RegistrationPerson $subject): void
    {
        $documents = $subject instanceof Registration
            ? RegistrationDocument::query()->whereIn('registration_person_id', $subject->people()->select('id'))->get()
            : $subject->documents;

        Storage::disk(self::DISK)->delete($documents->pluck('path')->all());
    }

    /** URL temporária assinada (validade dos documentos da plataforma, 10 min por padrão) para o organizador abrir o documento. */
    public function signedUrl(RegistrationDocument $document): ?string
    {
        try {
            return Storage::disk(self::DISK)->temporaryUrl($document->path, now()->addMinutes(PlatformLimits::signedMinutes('documents')), [
                'ResponseContentDisposition' => 'attachment; filename="'.str_replace('"', '', $document->original_name).'"',
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
