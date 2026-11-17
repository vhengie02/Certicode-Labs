<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Support\ClassGradebook;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A class's gradebook: every enrolled student's grade on every lab, as a page and as an Excel
 * file. Only the class's instructor and admins may see it.
 */
class GradebookController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    public function show(int $class_id)
    {
        $gradebook = ClassGradebook::for($this->managedClass($class_id));

        return view('classes.gradebook', ['class' => $gradebook->class, 'gradebook' => $gradebook]);
    }

    /**
     * The gradebook as an .xlsx file, written straight to the response as it is built.
     */
    public function export(int $class_id): StreamedResponse
    {
        $gradebook = ClassGradebook::for($this->managedClass($class_id));

        return response()->streamDownload(
            fn () => $gradebook->writeXlsx('php://output'),
            $gradebook->fileName(),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function managedClass(int $class_id): SchoolClass
    {
        $class = SchoolClass::findOrFail($class_id);
        $this->authorizeClassManager($class);

        return $class;
    }
}
