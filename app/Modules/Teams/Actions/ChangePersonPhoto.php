<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\Person;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

/**
 * Troca (ou remove, com null) a foto da pessoa. Foto é dado pessoal (e pode ser de menor): vai para o disco
 * PRIVADO `s3-private` e só é exibida no painel por URL temporária assinada. WebP quadrado de 400 px e 96 px.
 */
final class ChangePersonPhoto
{
    public const DISK = 's3-private';

    public const SIZES = ['full' => 400, 'thumb' => 96];

    public function handle(Person $person, ?UploadedFile $file): Person
    {
        $old = $person->photo_path;
        $new = null;

        if ($file !== null) {
            $new = ImageStore::save(self::DISK, "organizations/{$person->organization_id}/people/{$person->getKey()}", 'photo', $file, [
                'full' => ['size' => self::SIZES['full'], 'fit' => 'cover'],
                'thumb' => ['size' => self::SIZES['thumb'], 'fit' => 'cover'],
            ]);
        }

        $person->forceFill(['photo_path' => $new])->save();
        ImageStore::delete(self::DISK, $old, array_values(self::SIZES));

        return $person;
    }
}
