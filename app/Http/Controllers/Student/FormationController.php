<?php

namespace App\Http\Controllers\Student;

use App\Chat\UnreadCounter;
use App\Courses\CourseProgress;
use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\User;
use App\Packs\StudentEnrollments;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index(Request $request, CourseProgress $progress): View
    {
        /** @var User $user */
        $user = $request->user();

        $enrollments = StudentEnrollments::for($user);

        return view('site.student.formations.index', [
            'enrollments' => $enrollments,
            'progress' => $progress->forEnrollments($user, $enrollments),
        ]);
    }

    /**
     * Espace d'une formation. L'accès est vérifié par la règle FormationPolicy::access (middleware can:access).
     */
    public function show(Request $request, Formation $formation, CourseProgress $progress, UnreadCounter $unread): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Offre d'accompagnement : ses formations incluses sont dans « Mes formations » ; Groupe VIP : son groupe.
        if ($formation->isPack()) {
            return to_route('student.formations.index');
        }

        if ($formation->isGroupOnly()) {
            return to_route('chat.show', $formation);
        }

        $group = ChatGroup::forFormation($formation);

        return view('site.student.formations.show', [
            'formation' => $formation,
            'enrollment' => $user->enrollments()->with('purchase')->where('formation_id', $formation->id)->first(),
            'curriculum' => $formation->publicCurriculum(),
            'summary' => $progress->summary($user, $formation),
            'groupUnread' => (int) ($unread->forUser($user)[$group->id] ?? 0),
            'sessions' => LiveSession::query()->upcoming()->where('formation_id', $formation->id)->get(),
        ]);
    }
}
