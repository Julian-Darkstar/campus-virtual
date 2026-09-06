<?php

namespace App\Http\Controllers;

use App\Actions\Students\ImportStudents;
use App\Http\Requests\ImportStudentsRequest;
use Illuminate\Http\RedirectResponse;

class StudentImportController extends Controller
{
    public function store(ImportStudentsRequest $request, ImportStudents $action): RedirectResponse
    {
        $result = $action->execute($request->file('file'), $request->user());

        return back()->with('success', "Importación completada: {$result['created']} altas y {$result['updated']} actualizaciones.");
    }
}

