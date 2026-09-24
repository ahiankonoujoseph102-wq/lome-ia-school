<?php

namespace App\Http\Controllers\Student;

use App\Chat\UnreadCounter;
use App\Courses\CourseProgress;
use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index(Request $request, CourseProgress $progress): View
    {
        /** @var User $user */
        $user = $request->user();

        $enrollments = $user->enrollments()->with(['formation', 'purchase'])->latest('granted_at')->get();

        return view('site.student.formations.index', [
            'enrollments' => $enrollments,
            'progress' => $progress->forEnrollments($user, $enrollments),
        ]);
    }

    /**
     * Espace d'une formation. L'accès est vérifié par la règle FormationPolicy::access (middleware can:access).
     */
    public function show(Request $request, Formation $formation, CourseProgress $progress, UnreadCounter $unread): View
    {
        /** @var User $user */
        $user = $request->user();
        $group = ChatGroup::forFormation($formation);

        return view('site.student.formations.show', [
            'formation' => $formation,
            'enrollment' => $user->enrollments()->with('purchase')->where('formation_id', $formation->id)->first(),
            'curriculum' => $formation->publicCurriculum(),
            'summary' => $progress->summary($user, $formation),
            'groupUnread' => (int) ($unread->forUser($user)[$group->id] ?? 0),
        ]);
    }
}
