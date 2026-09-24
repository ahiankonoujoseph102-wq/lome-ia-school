<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.student.dashboard', [
            'user' => $user,
            'enrollments' => $user->enrollments()->with(['formation', 'purchase'])->latest('granted_at')->get(),
            'suggestions' => Formation::published()->where('is_featured', true)
                ->whereNotIn('id', $user->enrollments()->select('formation_id'))
                ->limit(3)
                ->get(),
        ]);
    }
}
