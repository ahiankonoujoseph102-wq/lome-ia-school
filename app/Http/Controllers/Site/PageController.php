<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\LiveSession;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'featured' => Formation::published()->where('is_featured', true)->get(),
            'formationCount' => Formation::published()->count(),
            'upcomingSessions' => LiveSession::query()->upcoming()->whereHas('formation', fn ($query) => $query->where('is_active', true))->with('formation')->limit(6)->get(),
        ]);
    }

    public function about(): View
    {
        return view('site.about', [
            'formationCount' => Formation::published()->count(),
        ]);
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function affiliate(): View
    {
        return view('site.affiliate');
    }

    public function consultation(): View
    {
        return view('site.consultation');
    }

    public function legalNotice(): View
    {
        return view('site.legal.notice');
    }

    public function terms(): View
    {
        return view('site.legal.terms');
    }

    public function privacy(): View
    {
        return view('site.legal.privacy');
    }
}
