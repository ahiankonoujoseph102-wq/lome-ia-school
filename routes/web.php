<?php

use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\PurchaseProofController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\FormationController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\FormationController as StudentFormationController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\PurchaseController;
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

// Parcours d'achat : récapitulatif de commande et paiement Mobile Money avec preuve de paiement
Route::get('commande/{formation}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('commande/{formation}/paiement', [CheckoutController::class, 'pay'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('checkout.pay');

// Espace élève « Mon espace »
Route::middleware('auth')->prefix('mon-espace')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('formations', [StudentFormationController::class, 'index'])->name('student.formations.index');
    Route::get('formations/{formation}', [StudentFormationController::class, 'show'])
        ->middleware('can:access,formation')
        ->name('student.formations.show');
    Route::get('achats', [PurchaseController::class, 'index'])->name('student.purchases.index');
    Route::get('achats/{purchase}', [PurchaseController::class, 'show'])->middleware('can:view,purchase')->name('student.purchases.show');
    Route::get('achats/{purchase}/recu', [PurchaseController::class, 'receipt'])->middleware('can:viewReceipt,purchase')->name('student.purchases.receipt');
    Route::get('achats/{purchase}/preuve', PurchaseProofController::class)->middleware('can:view,purchase')->name('student.purchases.proof');
    Route::get('profil', [ProfileController::class, 'edit'])->name('student.profile');
    Route::put('profil', [ProfileController::class, 'update'])->name('student.profile.update');
    Route::delete('profil/photo', [ProfileController::class, 'destroyAvatar'])->name('student.profile.avatar.destroy');
    Route::delete('profil', [ProfileController::class, 'destroy'])->name('student.profile.destroy');
});

Route::redirect('dashboard', '/mon-espace');

// Administration (pages construites à l'étape 10) : accès réservé aux comptes admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'site.admin.index')->name('index');
    Route::get('paiements', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::post('paiements/{purchase}/valider', [AdminPaymentController::class, 'approve'])->name('payments.approve');
    Route::post('paiements/{purchase}/refuser', [AdminPaymentController::class, 'reject'])->name('payments.reject');
    Route::post('eleves/{user}/bloquer', [AdminPaymentController::class, 'block'])->name('users.block');
    Route::post('eleves/{user}/debloquer', [AdminPaymentController::class, 'unblock'])->name('users.unblock');
});

require __DIR__.'/settings.php';
