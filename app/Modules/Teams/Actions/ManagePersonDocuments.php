<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Enums\PersonDocumentType;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\PersonDocument;
use App\Support\PlatformLimits;
use App\Support\Uploads\UploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Documentos com validade de uma pessoa (atestado médico, seguro...). Dado pessoal: disco PRIVADO, nunca público; o painel
 * abre por URL temporária assinada. Os arquivos ficam como enviados (PDF ou imagem).
 */
final class ManagePersonDocuments
{
    public const DISK = 's3-private';

    public const MAX_PER_PERSON = 10;

    private const EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function attach(Person $person, UploadedFile $file, PersonDocumentType $type, Carbon $expiresOn): PersonDocument
    {
        if ($person->documents()->count() >= self::MAX_PER_PERSON) {
            throw new InvalidArgumentException('No máximo '.self::MAX_PER_PERSON.' documentos por pessoa. Exclua os antigos.');
        }

        $mime = (string) $file->getMimeType();

        if (! isset(self::EXTENSIONS[$mime])) {
            throw new InvalidArgumentException('Envie um PDF ou uma imagem (JPG, PNG ou WebP).');
        }

        $content = (string) file_get_contents($file->getRealPath());
        UploadScanner::check($content, $mime);

        $path = "organizations/{$person->organization_id}/people/{$person->getKey()}/documents/".bin2hex(random_bytes(8)).'.'.self::EXTENSIONS[$mime];

        Storage::disk(self::DISK)->put($path, $content, ['ContentType' => $mime]);

        return PersonDocument::query()->create([
            'organization_id' => $person->organization_id,
            'person_id' => $person->getKey(),
            'type' => $type,
            'expires_on' => $expiresOn->toDateString(),
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 150),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ]);
    }

    public function remove(PersonDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();
    }

    /** Apaga os arquivos (e os registros) de uma pessoa, ao excluí-la. */
    public function purge(Person $person): void
    {
        Storage::disk(self::DISK)->delete($person->documents()->pluck('path')->all());
        $person->documents()->delete();
    }

    /** URL temporária assinada (validade dos documentos da plataforma) para abrir o arquivo no painel. */
    public function signedUrl(PersonDocument $document): ?string
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
