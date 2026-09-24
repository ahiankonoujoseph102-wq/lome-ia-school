<?php

use App\Http\Controllers\Admin\ChatGroupController as AdminChatGroupController;
use App\Http\Controllers\Admin\CourseContentController;
use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\LiveSessionController as AdminLiveSessionController;
use App\Http\Controllers\Admin\ModeratorController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Chat\AttachmentController;
use App\Http\Controllers\Chat\GroupController;
use App\Http\Controllers\Chat\MessageController;
use App\Http\Controllers\Chat\ModerationController;
use App\Http\Controllers\Chat\ReportController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PurchaseProofController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\FormationController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PwaController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\FormationController as StudentFormationController;
use App\Http\Controllers\Student\LiveSessionController as StudentLiveSessionController;
use App\Http\Controllers\Student\NotificationController;
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

// Site installable sur l'écran d'accueil (manifeste et service worker)
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

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

    // Notifications des groupes : téléphone (Web Push), e-mail récapitulatif, groupes coupés
    Route::get('notifications', [NotificationController::class, 'edit'])->name('student.notifications');
    Route::put('notifications', [NotificationController::class, 'update'])->name('student.notifications.update');
    Route::post('notifications/appareils', [NotificationController::class, 'subscribe'])->middleware('throttle:20,1')->name('student.notifications.subscribe');
    Route::delete('notifications/appareils', [NotificationController::class, 'unsubscribe'])->name('student.notifications.unsubscribe');

    // Sessions ponctuelles en direct et en présentiel (élèves ayant accès à la formation)
    Route::get('sessions', [StudentLiveSessionController::class, 'index'])->name('student.sessions.index');
    Route::get('sessions/{session}', [StudentLiveSessionController::class, 'show'])->name('student.sessions.show');
    Route::post('sessions/{session}/inscription', [StudentLiveSessionController::class, 'register'])->middleware('throttle:20,1')->name('student.sessions.register');
    Route::delete('sessions/{session}/inscription', [StudentLiveSessionController::class, 'cancel'])->name('student.sessions.cancel');
    Route::get('sessions/{session}/paiement', [StudentLiveSessionController::class, 'payment'])->name('student.sessions.payment');
    Route::post('sessions/{session}/paiement', [StudentLiveSessionController::class, 'pay'])->middleware('throttle:10,1')->name('student.sessions.pay');
    Route::get('sessions/{session}/agenda.ics', [StudentLiveSessionController::class, 'calendar'])->name('student.sessions.calendar');
    Route::get('sessions/{session}/rejoindre', [StudentLiveSessionController::class, 'join'])->name('student.sessions.join');

    // Groupes de discussion (un par formation) : réservés aux acheteurs, à l'admin et aux modérateurs
    Route::middleware('no-store')->group(function () {
        Route::get('groupes', [GroupController::class, 'index'])->name('chat.index');
        Route::get('groupes/{formation}', [GroupController::class, 'show'])->name('chat.show');
        Route::get('groupes/{formation}/messages', [GroupController::class, 'messages'])->name('chat.messages.index');
        Route::post('groupes/{formation}/messages', [MessageController::class, 'store'])->middleware('throttle:chat-messages')->name('chat.messages.store');
        Route::delete('groupes/{formation}/messages/{message}', [MessageController::class, 'destroy'])->name('chat.messages.destroy');
        Route::post('groupes/{formation}/messages/{message}/epingler', [MessageController::class, 'togglePin'])->name('chat.messages.pin');
        Route::post('groupes/{formation}/messages/{message}/signaler', [MessageController::class, 'report'])->middleware('throttle:20,1')->name('chat.messages.report');
        Route::get('groupes/{formation}/fichiers/{message}', [AttachmentController::class, 'show'])->name('chat.attachments.show');
        Route::post('groupes/{formation}/lu', [GroupController::class, 'read'])->name('chat.read');
        Route::post('groupes/{formation}/regles', [GroupController::class, 'rulesSeen'])->name('chat.rules.seen');
        Route::post('groupes/{formation}/notifications', [GroupController::class, 'toggleNotifications'])->name('chat.notifications');
        Route::get('groupes/{formation}/membres', [GroupController::class, 'members'])->name('chat.members');

        // Modération (admin et modérateurs)
        Route::post('groupes/{formation}/annonces', [ModerationController::class, 'toggleAnnouncements'])->name('chat.announcements');
        Route::post('groupes/{formation}/membres/{user}/sourdine', [ModerationController::class, 'mute'])->name('chat.members.mute');
        Route::delete('groupes/{formation}/membres/{user}/sourdine', [ModerationController::class, 'unmute'])->name('chat.members.unmute');
        Route::post('groupes/{formation}/membres/{user}/exclure', [ModerationController::class, 'exclude'])->name('chat.members.exclude');
        Route::post('groupes/{formation}/membres/{user}/reintegrer', [ModerationController::class, 'reinstate'])->name('chat.members.reinstate');
    });
});

// Sessions en direct et en présentiel : admin et modérateurs
Route::middleware(['auth', 'can:manage-sessions'])->prefix('admin/sessions')->name('admin.sessions.')->group(function () {
    Route::get('/', [AdminLiveSessionController::class, 'index'])->name('index');
    Route::get('nouvelle', [AdminLiveSessionController::class, 'create'])->name('create');
    Route::post('/', [AdminLiveSessionController::class, 'store'])->name('store');
    Route::get('{session}', [AdminLiveSessionController::class, 'show'])->name('show');
    Route::get('{session}/modifier', [AdminLiveSessionController::class, 'edit'])->name('edit');
    Route::put('{session}', [AdminLiveSessionController::class, 'update'])->name('update');
    Route::post('{session}/annuler', [AdminLiveSessionController::class, 'cancel'])->name('cancel');
    Route::get('{session}/presence', [AdminLiveSessionController::class, 'attendance'])->name('attendance');
    Route::post('{session}/presence/{registration}', [AdminLiveSessionController::class, 'toggleAttendance'])->name('attendance.toggle');
    Route::get('{session}/enregistrement', [AdminLiveSessionController::class, 'recording'])->name('recording');
    Route::post('{session}/remboursements/{purchase}', [AdminLiveSessionController::class, 'refund'])->name('refund');
});

// « Messages signalés » : admin et modérateurs
Route::middleware(['auth', 'can:moderate-chat', 'no-store'])->prefix('moderation')->name('moderation.')->group(function () {
    Route::get('signalements', [ReportController::class, 'index'])->name('reports.index');
    Route::post('signalements/{report}', [ReportController::class, 'handle'])->name('reports.handle');
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

    // Groupes de discussion : activité, espace disque, règles ; équipe de modération
    Route::get('groupes', [AdminChatGroupController::class, 'index'])->name('chat.index');
    Route::get('groupes/{group}/regles', [AdminChatGroupController::class, 'edit'])->name('chat.edit');
    Route::put('groupes/{group}/regles', [AdminChatGroupController::class, 'updateRules'])->name('chat.rules');
    Route::post('groupes/{group}/annonces', [AdminChatGroupController::class, 'toggleAnnouncements'])->name('chat.announcements');
    Route::get('equipe', [ModeratorController::class, 'index'])->name('moderators.index');
    Route::post('equipe', [ModeratorController::class, 'store'])->name('moderators.store');
    Route::delete('equipe/{user}', [ModeratorController::class, 'destroy'])->name('moderators.destroy');
});

require __DIR__.'/settings.php';
