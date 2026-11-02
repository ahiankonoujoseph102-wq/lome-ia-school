<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Http\Controllers\Admin\AssistantConversationController;
use App\Http\Controllers\Admin\AssistantHandoffController;
use App\Http\Controllers\Admin\ChatGroupController as AdminChatGroupController;
use App\Http\Controllers\Admin\ConsultationAvailabilityController;
use App\Http\Controllers\Admin\ConsultationController as AdminConsultationController;
use App\Http\Controllers\Admin\CourseContentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\FormationController as AdminFormationController;
use App\Http\Controllers\Admin\FreeRegistrationController as AdminFreeRegistrationController;
use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\LiveSessionController as AdminLiveSessionController;
use App\Http\Controllers\Admin\ModeratorController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\QuoteRequestController as AdminQuoteRequestController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Chat\AttachmentController;
use App\Http\Controllers\Chat\GroupController;
use App\Http\Controllers\Chat\LocationController;
use App\Http\Controllers\Chat\MessageController;
use App\Http\Controllers\Chat\ModerationController;
use App\Http\Controllers\Chat\PollController;
use App\Http\Controllers\Chat\ReportController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PurchaseProofController;
use App\Http\Controllers\Site\AssistantController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\ConsultationController;
use App\Http\Controllers\Site\FormationController;
use App\Http\Controllers\Site\FreeRegistrationController;
use App\Http\Controllers\Site\NewsletterSubscriptionController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\PackController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PwaController;
use App\Http\Controllers\Site\QuoteRequestController;
use App\Http\Controllers\Site\ServerTimeController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Student\AffiliateController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\EmailConfirmationController;
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
// Consultation gratuite : réservation sans compte, puis gestion par lien sécurisé (le lien Google Meet n'est visible que là)
Route::get('consultation-gratuite', [ConsultationController::class, 'show'])->name('consultation');
Route::post('consultation-gratuite', [ConsultationController::class, 'store'])->middleware('throttle:5,10')->name('consultation.store');
Route::middleware(['no-store', 'throttle:60,1'])->prefix('consultation-gratuite/rendez-vous/{token}')->name('consultation.')->group(function () {
    Route::get('/', [ConsultationController::class, 'manage'])->name('manage');
    Route::put('/', [ConsultationController::class, 'move'])->name('move');
    Route::delete('/', [ConsultationController::class, 'cancel'])->name('cancel');
    Route::get('agenda.ics', [ConsultationController::class, 'calendar'])->name('calendar');
});
// Assistante « Ama » (Gemini) : message du visiteur, réponse d'Ama (limites dans App\Assistant\AmaChat).
Route::post('assistante/message', [AssistantController::class, 'message'])->middleware('throttle:30,1')->name('assistant.message');
Route::post('assistante/appel', [AssistantController::class, 'call'])->middleware('throttle:30,1')->name('assistant.call');
Route::post('assistante/duree', [AssistantController::class, 'timing'])->middleware('throttle:30,1')->name('assistant.timing');
// Voix naturelle d'Ama (modèles « TTS » de Gemini) : audio d'un morceau de réponse, ou voix du téléphone en secours.
Route::post('assistante/voix', [AssistantController::class, 'voice'])->middleware('throttle:60,1')->name('assistant.voice');
Route::post('assistante/voix/secours', [AssistantController::class, 'voiceFallback'])->middleware('throttle:30,1')->name('assistant.voice.fallback');
// « Transmettre au fondateur » : question transmise à Coach Joseph, au toucher de « Envoyer » (App\Assistant\FounderHandoff).
Route::post('assistante/transmettre', [AssistantController::class, 'handoff'])->middleware('throttle:10,1')->name('assistant.handoff');
Route::get('a-propos', [PageController::class, 'about'])->name('about');
Route::get('joseph-ahiankonou', [PageController::class, 'founder'])->name('founder');
Route::get('contact', [PageController::class, 'contact'])->name('contact');
Route::get('entreprises', [PageController::class, 'business'])->name('business');
Route::get('talkami', [PageController::class, 'talkami'])->name('talkami');
// Formulaire « Demander un devis » de la page Entreprises (sans compte ; champ piège et limite par adresse IP).
Route::post('entreprises/devis', [QuoteRequestController::class, 'store'])->middleware('throttle:5,10')->name('business.quote');
Route::get('accompagnement', [PackController::class, 'index'])->name('packs');
// Heure du serveur : corrige l'horloge du téléphone dans les comptes à rebours des offres.
Route::get('heure-serveur', ServerTimeController::class)->middleware('throttle:60,1')->name('server-time');
Route::get('devenir-affilie', [PageController::class, 'affiliate'])->name('affiliate.program');
Route::get('mentions-legales', [PageController::class, 'legalNotice'])->name('legal.notice');
Route::get('conditions-generales-de-vente', [PageController::class, 'terms'])->name('legal.terms');
Route::get('politique-de-confidentialite', [PageController::class, 'privacy'])->name('legal.privacy');

// Désabonnement des e-mails groupés (lien signé de l'e-mail, sans connexion ; POST = un clic de Gmail, sans jeton CSRF).
Route::middleware(['signed', 'throttle:30,1', 'no-store'])->prefix('desabonnement/{user}')->group(function () {
    Route::get('/', [NewsletterSubscriptionController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
    Route::post('/', [NewsletterSubscriptionController::class, 'oneClick'])->name('newsletter.unsubscribe.one-click');
    Route::post('reabonnement', [NewsletterSubscriptionController::class, 'resubscribe'])->name('newsletter.resubscribe');
});

// Images de partage (Open Graph), plan du site et robots
Route::get('og/site.png', [OgImageController::class, 'site'])->name('og.site');
Route::get('og/joseph-ahiankonou.jpg', [OgImageController::class, 'founder'])->name('og.founder');
Route::get('og/formations/{formation}.png', [OgImageController::class, 'formation'])->name('og.formation');
Route::get('og/formations/{formation}.jpg', [OgImageController::class, 'formationPhoto'])->name('og.formation.photo');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Site installable sur l'écran d'accueil (manifeste et service worker)
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('application', [PwaController::class, 'start'])->name('pwa.start');

// Parcours d'achat : récapitulatif de commande et paiement Mobile Money avec preuve de paiement
Route::get('commande/{formation}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('commande/{formation}/paiement', [CheckoutController::class, 'pay'])
    ->middleware(['auth', 'email-confirmed', 'throttle:10,1'])
    ->name('checkout.pay');

// Inscription gratuite à une formation à 0 FCFA (compte et adresse e-mail confirmée obligatoires) et annulation
Route::post('formations/{formation}/inscription-gratuite', [FreeRegistrationController::class, 'store'])
    ->middleware(['auth', 'email-confirmed', 'throttle:10,1'])
    ->name('free-registration.store');
// Visiteur sans compte (ou adresse non confirmée) : « Créer mon compte » garde la formation en mémoire (aucune
// place réservée) ; après la création du compte et la confirmation de l'e-mail, écran du choix de l'option.
Route::get('formations/{formation}/inscription-gratuite/compte', [FreeRegistrationController::class, 'start'])
    ->middleware(['no-store', 'throttle:30,1'])
    ->name('free-registration.start');
Route::get('formations/{formation}/inscription-gratuite/option', [FreeRegistrationController::class, 'options'])
    ->middleware(['auth', 'no-store', 'throttle:30,1'])
    ->name('free-registration.options');
// Ancien parcours (option choisie avant le compte) : gardé pour les choix déjà en mémoire et les pages déjà ouvertes.
Route::post('formations/{formation}/inscription-gratuite/choix', [FreeRegistrationController::class, 'choose'])
    ->middleware('throttle:20,1')
    ->name('free-registration.choose');
Route::get('inscription-gratuite/terminer', [FreeRegistrationController::class, 'complete'])
    ->middleware(['auth', 'no-store', 'throttle:20,1'])
    ->name('free-registration.complete');
Route::delete('formations/{formation}/inscription-gratuite', [FreeRegistrationController::class, 'destroy'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('free-registration.destroy');

// Confirmation de l'adresse e-mail : bouton de l'e-mail (valable 30 minutes, une seule fois, même sans être connecté)
Route::get('confirmer-email/{token}', [EmailConfirmationController::class, 'link'])
    ->middleware(['no-store', 'throttle:20,1'])
    ->name('email-confirmation.link');

// Espace élève « Mon espace »
Route::middleware('auth')->prefix('mon-espace')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // « Confirmez votre e-mail » : code à 6 chiffres, nouveau code (limité), correction de l'adresse
    Route::middleware('no-store')->group(function () {
        Route::get('confirmer-email', [EmailConfirmationController::class, 'show'])->name('email-confirmation.show');
        Route::post('confirmer-email', [EmailConfirmationController::class, 'confirm'])->middleware('throttle:20,1')->name('email-confirmation.confirm');
        Route::post('confirmer-email/renvoyer', [EmailConfirmationController::class, 'resend'])->middleware('throttle:10,1')->name('email-confirmation.resend');
        Route::put('confirmer-email/adresse', [EmailConfirmationController::class, 'updateEmail'])->middleware('throttle:10,1')->name('email-confirmation.email');
    });
    Route::get('formations', [StudentFormationController::class, 'index'])->name('student.formations.index');
    Route::get('formations/{formation}', [StudentFormationController::class, 'show'])
        ->middleware('can:access,formation')
        ->name('student.formations.show');
    Route::get('consultation/{consultation}', [ConsultationController::class, 'student'])->name('student.consultation');
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

    // Affiliation : tout inscrit peut devenir affilié, même sans avoir acheté
    Route::get('affiliation', [AffiliateController::class, 'index'])->name('student.affiliate.index');
    Route::post('affiliation/activer', [AffiliateController::class, 'activate'])->middleware('throttle:10,1')->name('student.affiliate.activate');
    Route::get('affiliation/commissions', [AffiliateController::class, 'commissions'])->name('student.affiliate.commissions');
    Route::get('affiliation/retraits', [AffiliateController::class, 'withdrawals'])->name('student.affiliate.withdrawals');
    Route::put('affiliation/coordonnees', [AffiliateController::class, 'updatePayout'])->middleware('throttle:10,1')->name('student.affiliate.payout');
    Route::post('affiliation/retraits', [AffiliateController::class, 'requestWithdrawal'])->middleware(['email-confirmed', 'throttle:10,1'])->name('student.affiliate.withdraw');

    // Sessions ponctuelles en direct et en présentiel (élèves ayant accès à la formation)
    Route::get('sessions', [StudentLiveSessionController::class, 'index'])->name('student.sessions.index');
    Route::get('sessions/{session}', [StudentLiveSessionController::class, 'show'])->name('student.sessions.show');
    Route::post('sessions/{session}/inscription', [StudentLiveSessionController::class, 'register'])->middleware('throttle:20,1')->name('student.sessions.register');
    Route::delete('sessions/{session}/inscription', [StudentLiveSessionController::class, 'cancel'])->name('student.sessions.cancel');
    Route::get('sessions/{session}/paiement', [StudentLiveSessionController::class, 'payment'])->name('student.sessions.payment');
    Route::post('sessions/{session}/paiement', [StudentLiveSessionController::class, 'pay'])->middleware(['email-confirmed', 'throttle:10,1'])->name('student.sessions.pay');
    Route::get('sessions/{session}/agenda.ics', [StudentLiveSessionController::class, 'calendar'])->name('student.sessions.calendar');
    Route::get('sessions/{session}/rejoindre', [StudentLiveSessionController::class, 'join'])->name('student.sessions.join');

    // Groupes de discussion (un par formation) : réservés aux acheteurs, à l'admin et aux modérateurs
    Route::middleware('no-store')->group(function () {
        Route::get('groupes', [GroupController::class, 'index'])->name('chat.index');
        Route::get('groupes/{formation}', [GroupController::class, 'show'])->name('chat.show');
        Route::get('groupes/{formation}/messages', [GroupController::class, 'messages'])->name('chat.messages.index');
        Route::post('groupes/{formation}/messages', [MessageController::class, 'store'])->middleware('throttle:chat-messages')->name('chat.messages.store');
        Route::post('groupes/{formation}/lieux', [LocationController::class, 'store'])->middleware('throttle:chat-messages')->name('chat.locations.store');
        Route::post('groupes/{formation}/sondages', [PollController::class, 'store'])->middleware(['staff-2fa', 'throttle:chat-messages'])->name('chat.polls.store');
        Route::post('groupes/{formation}/sondages/{poll}/vote', [PollController::class, 'vote'])->middleware('throttle:60,1')->name('chat.polls.vote');
        Route::post('groupes/{formation}/sondages/{poll}/cloturer', [PollController::class, 'close'])->middleware('throttle:20,1')->name('chat.polls.close');
        Route::delete('groupes/{formation}/messages/{message}', [MessageController::class, 'destroy'])->name('chat.messages.destroy');
        Route::post('groupes/{formation}/messages/{message}/epingler', [MessageController::class, 'togglePin'])->name('chat.messages.pin');
        Route::post('groupes/{formation}/messages/{message}/reaction', [MessageController::class, 'react'])->middleware('throttle:60,1')->name('chat.messages.react');
        Route::post('groupes/{formation}/messages/{message}/signaler', [MessageController::class, 'report'])->middleware('throttle:20,1')->name('chat.messages.report');
        Route::get('groupes/{formation}/fichiers/{message}', [AttachmentController::class, 'show'])->name('chat.attachments.show');
        Route::post('groupes/{formation}/lu', [GroupController::class, 'read'])->name('chat.read');
        Route::post('groupes/{formation}/regles', [GroupController::class, 'rulesSeen'])->name('chat.rules.seen');
        Route::post('groupes/{formation}/notifications', [GroupController::class, 'toggleNotifications'])->name('chat.notifications');
        Route::get('groupes/{formation}/membres', [GroupController::class, 'members'])->name('chat.members');
        Route::get('groupes/{formation}/annonce-email', [MessageController::class, 'emailPreview'])->middleware('staff-2fa')->name('chat.announcement-email');

        // Modération (admin et modérateurs, double authentification obligatoire)
        Route::middleware('staff-2fa')->group(function () {
            Route::post('groupes/{formation}/annonces', [ModerationController::class, 'toggleAnnouncements'])->name('chat.announcements');
            Route::post('groupes/{formation}/membres/{user}/sourdine', [ModerationController::class, 'mute'])->name('chat.members.mute');
            Route::delete('groupes/{formation}/membres/{user}/sourdine', [ModerationController::class, 'unmute'])->name('chat.members.unmute');
            Route::post('groupes/{formation}/membres/{user}/exclure', [ModerationController::class, 'exclude'])->name('chat.members.exclude');
            Route::post('groupes/{formation}/membres/{user}/reintegrer', [ModerationController::class, 'reinstate'])->name('chat.members.reinstate');
        });
    });
});

// Sessions en direct et en présentiel : admin et modérateurs
Route::middleware(['auth', 'can:manage-sessions', 'staff-2fa'])->prefix('admin/sessions')->name('admin.sessions.')->group(function () {
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
Route::middleware(['auth', 'can:moderate-chat', 'staff-2fa', 'no-store'])->prefix('moderation')->name('moderation.')->group(function () {
    Route::get('signalements', [ReportController::class, 'index'])->name('reports.index');
    Route::post('signalements/{report}', [ReportController::class, 'handle'])->name('reports.handle');
});

Route::redirect('dashboard', '/mon-espace');

// Administration (pages construites à l'étape 10) : accès réservé aux comptes admin
Route::middleware(['auth', 'admin', 'staff-2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('index');

    // Ventes, codes promo, exports (étape 10)
    Route::get('ventes', [SalesController::class, 'index'])->name('sales.index');
    Route::get('codes-promo', [PromoCodeController::class, 'index'])->name('promo-codes.index');
    Route::get('codes-promo/nouveau', [PromoCodeController::class, 'create'])->name('promo-codes.create');
    Route::post('codes-promo', [PromoCodeController::class, 'store'])->name('promo-codes.store');
    Route::get('codes-promo/{promoCode}', [PromoCodeController::class, 'edit'])->name('promo-codes.edit');
    Route::put('codes-promo/{promoCode}', [PromoCodeController::class, 'update'])->name('promo-codes.update');
    Route::post('codes-promo/{promoCode}/activation', [PromoCodeController::class, 'toggle'])->name('promo-codes.toggle');
    Route::delete('codes-promo/{promoCode}', [PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');
    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{type}', [ExportController::class, 'download'])->name('exports.download');

    // Formations (prix, textes, image, bonus) et élèves (fiche, accès manuel)
    Route::get('formations', [AdminFormationController::class, 'index'])->name('formations.index');
    Route::get('formations/nouvelle', [AdminFormationController::class, 'create'])->name('formations.create');
    Route::post('formations', [AdminFormationController::class, 'store'])->name('formations.store');
    Route::put('formations/offres/affiche', [AdminFormationController::class, 'updatePacksPoster'])->name('formations.packs-poster');
    Route::put('formations/offres/fin-des-inscriptions', [AdminFormationController::class, 'updatePacksDeadline'])->name('formations.packs-deadline');
    Route::get('formations/{formation}', [AdminFormationController::class, 'edit'])->name('formations.edit');
    Route::put('formations/{formation}', [AdminFormationController::class, 'update'])->name('formations.update');
    Route::get('formations/{formation}/inscriptions', [AdminFreeRegistrationController::class, 'show'])->name('free-registrations.show');
    Route::put('formations/{formation}/inscriptions', [AdminFreeRegistrationController::class, 'update'])->name('free-registrations.update');
    Route::put('formations/{formation}/inscriptions/video', [AdminFreeRegistrationController::class, 'updateVideo'])->name('free-registrations.video');
    Route::put('formations/{formation}/inscriptions/terminee', [AdminFreeRegistrationController::class, 'updateEnded'])->name('free-registrations.ended');
    Route::get('formations/{formation}/inscriptions/export', [AdminFreeRegistrationController::class, 'export'])->name('free-registrations.export');
    Route::post('formations/{formation}/deplacer/{direction}', [AdminFormationController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('formations.move');
    Route::get('eleves', [StudentController::class, 'index'])->name('students.index');
    Route::get('eleves/{user}', [StudentController::class, 'show'])->name('students.show');
    Route::post('eleves/{user}/acces', [StudentController::class, 'grantAccess'])->name('students.access');
    Route::post('eleves/{user}/confirmer-email', [StudentController::class, 'confirmEmail'])->name('students.confirm-email');
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
    Route::put('equipe/etiquettes', [ModeratorController::class, 'updateBadges'])->name('moderators.badges');

    // Envoi d'e-mails groupés à tous les comptes (texte, image, vidéo, bouton), étalé selon le quota de Brevo
    Route::get('envoi-emails', [AdminNewsletterController::class, 'index'])->name('newsletters.index');
    Route::get('envoi-emails/nouveau', [AdminNewsletterController::class, 'create'])->name('newsletters.create');
    Route::post('envoi-emails', [AdminNewsletterController::class, 'store'])->name('newsletters.store');
    Route::get('envoi-emails/{newsletter}', [AdminNewsletterController::class, 'show'])->name('newsletters.show');
    Route::get('envoi-emails/{newsletter}/modifier', [AdminNewsletterController::class, 'edit'])->name('newsletters.edit');
    Route::put('envoi-emails/{newsletter}', [AdminNewsletterController::class, 'update'])->name('newsletters.update');
    Route::delete('envoi-emails/{newsletter}', [AdminNewsletterController::class, 'destroy'])->name('newsletters.destroy');
    Route::get('envoi-emails/{newsletter}/apercu', [AdminNewsletterController::class, 'preview'])->middleware('no-store')->name('newsletters.preview');
    Route::post('envoi-emails/{newsletter}/test', [AdminNewsletterController::class, 'test'])->middleware('throttle:6,10')->name('newsletters.test');
    Route::post('envoi-emails/{newsletter}/envoyer', [AdminNewsletterController::class, 'send'])->name('newsletters.send');
    Route::post('envoi-emails/{newsletter}/pause', [AdminNewsletterController::class, 'pause'])->name('newsletters.pause');
    Route::post('envoi-emails/{newsletter}/reprise', [AdminNewsletterController::class, 'resume'])->name('newsletters.resume');
    Route::post('envoi-emails/{newsletter}/annulation', [AdminNewsletterController::class, 'cancel'])->name('newsletters.cancel');

    // Affiliation : retraits à payer et affiliés (admin uniquement, jamais les modérateurs : il s'agit d'argent)
    Route::get('affiliation/retraits', [AdminAffiliateController::class, 'withdrawals'])->name('affiliates.withdrawals');
    Route::post('affiliation/retraits/{withdrawal}/payer', [AdminAffiliateController::class, 'pay'])->name('affiliates.withdrawals.pay');
    Route::post('affiliation/retraits/{withdrawal}/refuser', [AdminAffiliateController::class, 'reject'])->name('affiliates.withdrawals.reject');
    Route::get('affiliation/affilies', [AdminAffiliateController::class, 'index'])->name('affiliates.index');
    Route::post('affiliation/affilies/{affiliate}/suspendre', [AdminAffiliateController::class, 'suspend'])->name('affiliates.suspend');
    Route::post('affiliation/affilies/{affiliate}/reactiver', [AdminAffiliateController::class, 'unsuspend'])->name('affiliates.unsuspend');

    // Réglages du site, témoignages et journal d'activité (étape 10)
    // Assistante Ama (Gemini) : conversations, demandes du jour et incidents
    Route::get('assistante/conversations', [AssistantConversationController::class, 'index'])->middleware('no-store')->name('assistant.index');
    Route::get('assistante/conversations/{conversation}', [AssistantConversationController::class, 'show'])->middleware('no-store')->name('assistant.show');
    Route::get('devis', [AdminQuoteRequestController::class, 'index'])->middleware('no-store')->name('quotes.index');
    Route::post('devis/{quote}/statut', [AdminQuoteRequestController::class, 'toggle'])->name('quotes.toggle');
    Route::get('assistante/demandes', [AssistantHandoffController::class, 'index'])->middleware('no-store')->name('assistant.handoffs.index');
    Route::post('assistante/demandes/{handoff}/statut', [AssistantHandoffController::class, 'toggle'])->name('assistant.handoffs.toggle');
    Route::get('reglages', [SiteSettingsController::class, 'index'])->name('settings.index');
    Route::get('reglages/{section}', [SiteSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('reglages/{section}', [SiteSettingsController::class, 'update'])->name('settings.update');
    // Voix naturelle d'Ama : bouton « Écouter » (phrase d'exemple lue par la voix et le modèle choisis).
    Route::post('reglages/assistante/ecouter', [SiteSettingsController::class, 'previewVoice'])->middleware(['throttle:10,1', 'no-store'])->name('settings.voice-preview');
    Route::get('temoignages', [TestimonialController::class, 'index'])->name('testimonials.index');
    Route::get('temoignages/nouveau', [TestimonialController::class, 'create'])->name('testimonials.create');
    Route::post('temoignages', [TestimonialController::class, 'store'])->name('testimonials.store');
    Route::get('temoignages/{testimonial}', [TestimonialController::class, 'edit'])->name('testimonials.edit');
    Route::put('temoignages/{testimonial}', [TestimonialController::class, 'update'])->name('testimonials.update');
    Route::post('temoignages/{testimonial}/affichage', [TestimonialController::class, 'toggle'])->name('testimonials.toggle');
    Route::post('temoignages/{testimonial}/deplacer/{direction}', [TestimonialController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('testimonials.move');
    Route::delete('temoignages/{testimonial}', [TestimonialController::class, 'destroy'])->name('testimonials.destroy');
    Route::get('journal', [ActivityController::class, 'index'])->name('activity.index');

    // Consultation gratuite : rendez-vous et disponibilités (admin uniquement : Joseph fait seul les consultations)
    Route::get('consultations', [AdminConsultationController::class, 'index'])->name('consultations.index');
    Route::post('consultations/{consultation}/annuler', [AdminConsultationController::class, 'cancel'])->name('consultations.cancel');
    Route::post('consultations/{consultation}/presence', [AdminConsultationController::class, 'attendance'])->name('consultations.attendance');
    Route::put('consultations/{consultation}/suivi', [AdminConsultationController::class, 'followUp'])->name('consultations.follow-up');
    Route::post('consultations/{consultation}/recommandation', [AdminConsultationController::class, 'recommend'])->name('consultations.recommend');
    Route::get('consultations/disponibilites', [ConsultationAvailabilityController::class, 'edit'])->name('consultations.availability');
    Route::put('consultations/disponibilites', [ConsultationAvailabilityController::class, 'updateSettings'])->name('consultations.settings');
    Route::post('consultations/disponibilites/horaires', [ConsultationAvailabilityController::class, 'storeHour'])->name('consultations.hours.store');
    Route::delete('consultations/disponibilites/horaires/{hour}', [ConsultationAvailabilityController::class, 'destroyHour'])->name('consultations.hours.destroy');
    Route::post('consultations/disponibilites/blocages', [ConsultationAvailabilityController::class, 'storeBlock'])->name('consultations.blocks.store');
    Route::delete('consultations/disponibilites/blocages/{block}', [ConsultationAvailabilityController::class, 'destroyBlock'])->name('consultations.blocks.destroy');
});

require __DIR__.'/settings.php';
