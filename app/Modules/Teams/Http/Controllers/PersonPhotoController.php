<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\ChangePersonPhoto;
use App\Modules\Teams\Http\Requests\PersonPhotoRequest;
use App\Modules\Teams\Models\Person;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class PersonPhotoController extends Controller
{
    public function store(PersonPhotoRequest $request, Person $person, ChangePersonPhoto $change): RedirectResponse
    {
        try {
            $change->handle($person, $request->file('photo'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['photo' => $exception->getMessage()]);
        }

        return back()->with('success', 'Foto atualizada.');
    }

    public function destroy(Person $person, ChangePersonPhoto $change): RedirectResponse
    {
        $change->handle($person, null);

        return back()->with('success', 'Foto removida.');
    }
}
