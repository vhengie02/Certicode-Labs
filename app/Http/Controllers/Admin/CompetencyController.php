<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Competencies: the skills catalogue. Instructors tag labs with these, and grading
 * credits students with the competencies of the labs they complete.
 */
class CompetencyController extends Controller
{
    public function index()
    {
        $competencies = Competency::query()
            ->withCount(['laboratories', 'studentCompetencies'])
            ->withAvg('studentCompetencies', 'score_achieved')
            ->orderBy('code')
            ->get();

        return view('admin.competencies.index', compact('competencies'));
    }

    public function store(Request $request)
    {
        Competency::create($this->validated($request));

        return back()->with('success', 'Competency added.');
    }

    public function update(Request $request, Competency $competency)
    {
        $competency->update($this->validated($request, $competency));

        return back()->with('success', "“{$competency->name}” updated.");
    }

    public function destroy(Competency $competency)
    {
        $name = $competency->name;
        // Removes its lab tags and the students' scores for it (cascade)
        $competency->delete();

        return back()->with('success', "“{$name}” deleted.");
    }

    private function validated(Request $request, ?Competency $competency = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('competencies', 'code')->ignore($competency?->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ], [
            'code.regex' => 'Use letters, numbers, dots, dashes or underscores in the code.',
        ]);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
