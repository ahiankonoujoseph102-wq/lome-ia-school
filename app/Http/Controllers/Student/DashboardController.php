<?php

namespace App\Http\Controllers\Student;

use App\Affiliates\AffiliateAccount;
use App\Courses\CourseProgress;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CourseProgress $progress, AffiliateAccount $affiliates): View
    {
        /** @var User $user */
        $user = $request->user();

        $enrollments = $user->enrollments()->with(['formation', 'purchase'])->latest('granted_at')->get();

        return view('site.student.dashboard', [
            'user' => $user,
            'enrollments' => $enrollments,
            'progress' => $progress->forEnrollments($user, $enrollments),
            'sessions' => LiveSessionController::upcomingFor($user),
            'sessionRegistrations' => $user->liveSessionRegistrations()->get()->keyBy('live_session_id'),
            'consultation' => Consultation::query()->upcoming()->where('user_id', $user->id)->orderBy('starts_at')->first(),
            'affiliateBalance' => $user->affiliate ? $affiliates->balance($user->affiliate) : null,
            'suggestions' => Formation::published()->where('is_featured', true)
                ->whereNotIn('id', $user->enrollments()->select('formation_id'))
                ->limit(3)
                ->get(),
        ]);
    }
}
