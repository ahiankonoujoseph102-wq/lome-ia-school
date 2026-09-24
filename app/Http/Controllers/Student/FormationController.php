<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.student.formations.index', [
            'enrollments' => $user->enrollments()->with(['formation', 'purchase'])->latest('granted_at')->get(),
        ]);
    }

    /**
     * Espace d'une formation. L'accès est vérifié par la règle FormationPolicy::access (middleware can:access).
     */
    public function show(Request $request, Formation $formation): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.student.formations.show', [
            'formation' => $formation,
            'enrollment' => $user->enrollments()->with('purchase')->where('formation_id', $formation->id)->first(),
        ]);
    }
}
