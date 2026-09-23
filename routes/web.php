<?php

use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\FormationController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\FormationController as StudentFormationController;
use App\Http\Controllers\Student\ProfileController;
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

// Parcours d'achat : récapitulatif de commande (paiement FedaPay à l'étape 4)
Route::get('commande/{formation}', [CheckoutController::class, 'show'])->name('checkout.show');

// Espace élève « Mon espace »
Route::middleware('auth')->prefix('mon-espace')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('formations', [StudentFormationController::class, 'index'])->name('student.formations.index');
    Route::get('formations/{formation}', [StudentFormationController::class, 'show'])
        ->middleware('can:access,formation')
        ->name('student.formations.show');
    Route::get('profil', [ProfileController::class, 'edit'])->name('student.profile');
    Route::put('profil', [ProfileController::class, 'update'])->name('student.profile.update');
    Route::delete('profil/photo', [ProfileController::class, 'destroyAvatar'])->name('student.profile.avatar.destroy');
    Route::delete('profil', [ProfileController::class, 'destroy'])->name('student.profile.destroy');
});

Route::redirect('dashboard', '/mon-espace');

// Administration (pages construites à l'étape 10) : accès réservé aux comptes admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'site.admin.index')->name('index');
});

require __DIR__.'/settings.php';
