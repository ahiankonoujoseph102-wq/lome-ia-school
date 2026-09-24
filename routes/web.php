<?php

use App\Http\Controllers\Admin\CourseContentController;
use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\LessonController;
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

// Lecteur de cours : leçons (élèves ayant accès, ou aperçus gratuits pour tous), ressources, progression
Route::get('formations/{formation}/lecons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
Route::get('formations/{formation}/lecons/{lesson}/fichiers/{resource}', [LessonController::class, 'download'])->name('lessons.resources.download');
Route::post('formations/{formation}/lecons/{lesson}/terminee', [LessonController::class, 'complete'])->middleware('auth')->name('lessons.complete');
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

    // Contenu des formations : modules et leçons
    Route::get('contenu', [CourseContentController::class, 'index'])->name('content.index');
    Route::get('contenu/{formation}', [CourseContentController::class, 'show'])->name('content.show');
    Route::post('contenu/{formation}/modules', [CourseContentController::class, 'storeModule'])->name('modules.store');
    Route::put('modules/{module}', [CourseContentController::class, 'updateModule'])->name('modules.update');
    Route::post('modules/{module}/deplacer/{direction}', [CourseContentController::class, 'moveModule'])->whereIn('direction', ['up', 'down'])->name('modules.move');
    Route::delete('modules/{module}', [CourseContentController::class, 'destroyModule'])->name('modules.destroy');
    Route::get('modules/{module}/lecons/nouvelle', [AdminLessonController::class, 'create'])->name('lessons.create');
    Route::post('modules/{module}/lecons', [AdminLessonController::class, 'store'])->name('lessons.store');
    Route::get('lecons/{lesson}', [AdminLessonController::class, 'edit'])->name('lessons.edit');
    Route::put('lecons/{lesson}', [AdminLessonController::class, 'update'])->name('lessons.update');
    Route::post('lecons/{lesson}/deplacer/{direction}', [AdminLessonController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('lessons.move');
    Route::post('lecons/{lesson}/publication', [AdminLessonController::class, 'togglePublish'])->name('lessons.publish');
    Route::delete('lecons/{lesson}', [AdminLessonController::class, 'destroy'])->name('lessons.destroy');
    Route::delete('ressources/{resource}', [AdminLessonController::class, 'destroyResource'])->name('resources.destroy');
});

require __DIR__.'/settings.php';
