<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Support\Talkami;
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

    /**
     * Page personnelle de Joseph Ahiankonou (textes et liens : App\Support\Founder).
     */
    public function founder(): View
    {
        return view('site.founder');
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function business(): View
    {
        return view('site.business');
    }

    /**
     * Page Talkami (plateforme créée par Joseph pour apprendre à parler anglais) ; absente si l'admin l'a masquée.
     */
    public function talkami(): View
    {
        abort_unless(Talkami::enabled(), 404);

        return view('site.talkami');
    }

    public function affiliate(): View
    {
        return view('site.affiliate');
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
