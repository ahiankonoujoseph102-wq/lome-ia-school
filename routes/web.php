<?php

use App\Http\Controllers\Site\FormationController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

// Site vitrine
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('formations', [FormationController::class, 'index'])->name('formations.index');
Route::get('formations/{formation}', [FormationController::class, 'show'])->name('formations.show');
Route::get('consultation-gratuite', [PageController::class, 'consultation'])->name('consultation');
Route::get('a-propos', [PageController::class, 'about'])->name('about');
Route::get('contact', [PageController::class, 'contact'])->name('contact');
Route::get('mentions-legales', [PageController::class, 'legalNotice'])->name('legal.notice');
Route::get('conditions-generales-de-vente', [PageController::class, 'terms'])->name('legal.terms');
Route::get('politique-de-confidentialite', [PageController::class, 'privacy'])->name('legal.privacy');

// Images de partage (Open Graph), plan du site et robots
Route::get('og/site.png', [OgImageController::class, 'site'])->name('og.site');
Route::get('og/formations/{formation}.png', [OgImageController::class, 'formation'])->name('og.formation');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
