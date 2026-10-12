<?php

namespace App\Modules\Registration\Actions;

use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Registration\Support\EnsureEditable;

final class RemoveRegistrationPerson
{
    public function __construct(private readonly ManageRegistrationDocuments $documents = new ManageRegistrationDocuments) {}

    public function handle(RegistrationPerson $person): void
    {
        EnsureEditable::check($person->registration);

        $this->documents->purge($person);
        $person->delete();
    }
}
