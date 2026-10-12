<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Enums\NewsletterAudience;
use App\Http\Controllers\Controller;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\User;
use App\Newsletters\NewsletterLinks;
use App\Newsletters\NewsletterManager;
use App\Newsletters\NewsletterQuota;
use App\Newsletters\NewsletterRecipients;
use App\Services\ImageUploader;
use App\Support\PastedLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * « Envoi d'e-mails » (admin uniquement) : e-mail groupé à toutes les personnes qui ont un compte (adresse
 * confirmée, non désabonnées). Brouillon, aperçu, test envoyé à soi seul, confirmation avec le nombre de
 * destinataires et de jours, puis envoi progressif dans la limite du jour (App\Newsletters\NewsletterQuota).
 */
class NewsletterController extends Controller
{
    public function index(): View
    {
        return view('site.admin.newsletters.index', [
            'newsletters' => Newsletter::query()->latest('id')->paginate(20),
            'dailyLimit' => NewsletterQuota::dailyLimit(),
            'sentToday' => NewsletterQuota::sentToday(),
            'availableToday' => NewsletterQuota::availableToday(),
        ]);
    }

    public function create(): View
    {
        return view('site.admin.newsletters.form', ['newsletter' => new Newsletter, 'counts' => $this->audienceCounts()]);
    }

    public function store(Request $request, ImageUploader $images): RedirectResponse
    {
        $newsletter = new Newsletter(['user_id' => $request->user()->id]);
        $this->fill($newsletter, $request, $images);

        ActivityLog::record(AdminAction::NewsletterUpdated, 'Brouillon d\'e-mail groupé créé : « '.$newsletter->subject.' ».', $newsletter);

        return to_route('admin.newsletters.show', $newsletter)->with('status', 'Brouillon enregistré. Vérifiez l\'aperçu, envoyez-vous un test, puis lancez l\'envoi.');
    }

    public function edit(Newsletter $newsletter): View|RedirectResponse
    {
        if (! $newsletter->isDraft()) {
            return to_route('admin.newsletters.show', $newsletter);
        }

        return view('site.admin.newsletters.form', ['newsletter' => $newsletter, 'counts' => $this->audienceCounts()]);
    }

    public function update(Request $request, Newsletter $newsletter, ImageUploader $images): RedirectResponse
    {
        if (! $newsletter->isDraft()) {
            return to_route('admin.newsletters.show', $newsletter)->withErrors(['newsletter' => 'Cet e-mail est déjà lancé : il ne peut plus être modifié.']);
        }

        $this->fill($newsletter, $request, $images);

        ActivityLog::record(AdminAction::NewsletterUpdated, 'Brouillon d\'e-mail groupé modifié : « '.$newsletter->subject.' ».', $newsletter);

        return to_route('admin.newsletters.show', $newsletter)->with('status', 'Brouillon enregistré.');
    }

    public function destroy(Newsletter $newsletter, ImageUploader $images): RedirectResponse
    {
        if (! $newsletter->isDraft()) {
            return back()->withErrors(['newsletter' => 'Seul un brouillon peut être supprimé. Un envoi lancé reste dans l\'historique.']);
        }

        $images->delete($newsletter->image_path);
        ActivityLog::record(AdminAction::NewsletterUpdated, 'Brouillon d\'e-mail groupé supprimé : « '.$newsletter->subject.' ».');
        $newsletter->delete();

        return to_route('admin.newsletters.index')->with('status', 'Brouillon supprimé.');
    }

    public function show(Newsletter $newsletter): View
    {
        $recipients = $newsletter->isDraft() ? NewsletterRecipients::count($newsletter->audience) : $newsletter->recipients_count;

        return view('site.admin.newsletters.show', [
            'newsletter' => $newsletter,
            'recipients' => $recipients,
            'days' => $newsletter->isDraft() ? NewsletterQuota::daysFor($recipients) : null,
            'stats' => $newsletter->stats(),
            'dailyLimit' => NewsletterQuota::dailyLimit(),
            'availableToday' => NewsletterQuota::availableToday(),
        ]);
    }

    /**
     * L'e-mail tel qu'il sera reçu (avec le prénom de l'administrateur), affiché dans l'aperçu de la page.
     */
    public function preview(Request $request, Newsletter $newsletter): Response
    {
        /** @var User $admin */
        $admin = $request->user();
        $mail = new NewsletterMail($newsletter, $admin, test: true);

        if ($request->query('version') === 'texte') {
            return response(view('emails.newsletter-text', $mail->details() + ['newsletter' => $newsletter])->render(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response($mail->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * « M'envoyer un test » : seulement à l'adresse de l'administrateur connecté, tout de suite.
     */
    public function test(Request $request, Newsletter $newsletter): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        try {
            Mail::to($admin)->send(new NewsletterMail($newsletter, $admin, test: true));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['test' => 'Le test n\'a pas pu partir (fournisseur d\'e-mails injoignable). Réessayez dans quelques minutes.']);
        }

        return back()->with('status', 'Test envoyé à '.$admin->email.' (objet commençant par « [Test] »). Pensez à regarder aussi les courriers indésirables.');
    }

    /**
     * Lancement réel. Le nombre de destinataires affiché dans la confirmation doit être encore exact.
     */
    public function send(Request $request, Newsletter $newsletter, NewsletterManager $manager): RedirectResponse
    {
        if (! $newsletter->isDraft()) {
            return to_route('admin.newsletters.show', $newsletter)->withErrors(['newsletter' => 'Cet e-mail est déjà lancé.']);
        }

        $request->validate(['confirm_count' => ['required', 'integer']]);
        $expected = NewsletterRecipients::count($newsletter->audience);

        if ((int) $request->input('confirm_count') !== $expected) {
            return back()->withErrors(['newsletter' => 'Le nombre de destinataires a changé entre-temps : il est maintenant de '.$expected.'. Vérifiez puis confirmez de nouveau.']);
        }

        $days = NewsletterQuota::daysFor($expected);
        $count = $manager->start($newsletter, $request->user());

        if ($count === null) {
            return back()->withErrors(['newsletter' => 'Envoi impossible : aucune personne ne peut recevoir cet e-mail.']);
        }

        ActivityLog::record(
            AdminAction::NewsletterSent,
            'E-mail groupé « '.$newsletter->subject.' » lancé pour '.$count.' '.($count > 1 ? 'personnes' : 'personne').($days ? ' (environ '.$days.' '.($days > 1 ? 'jours' : 'jour').')' : '').'.',
            $newsletter,
        );

        return to_route('admin.newsletters.show', $newsletter)->with('status', 'Envoi lancé : '.$count.' '.($count > 1 ? 'e-mails partiront' : 'e-mail partira').' progressivement, sans rien relancer de votre côté.');
    }

    public function pause(Newsletter $newsletter, NewsletterManager $manager): RedirectResponse
    {
        if ($manager->pause($newsletter)) {
            ActivityLog::record(AdminAction::NewsletterUpdated, 'E-mail groupé « '.$newsletter->subject.' » mis en pause.', $newsletter);

            return back()->with('status', 'Envoi en pause : aucun nouvel e-mail ne part jusqu\'à la reprise.');
        }

        return back();
    }

    public function resume(Newsletter $newsletter, NewsletterManager $manager): RedirectResponse
    {
        if ($manager->resume($newsletter)) {
            ActivityLog::record(AdminAction::NewsletterUpdated, 'E-mail groupé « '.$newsletter->subject.' » repris.', $newsletter);

            return back()->with('status', 'Envoi repris.');
        }

        return back();
    }

    public function cancel(Newsletter $newsletter, NewsletterManager $manager): RedirectResponse
    {
        if ($manager->cancel($newsletter)) {
            ActivityLog::record(AdminAction::NewsletterUpdated, 'E-mail groupé « '.$newsletter->subject.' » annulé.', $newsletter);

            return back()->with('status', 'Envoi annulé : les e-mails pas encore partis ne partiront pas.');
        }

        return back();
    }

    /**
     * @return array<string, int>
     */
    private function audienceCounts(): array
    {
        return collect(NewsletterAudience::cases())->mapWithKeys(fn (NewsletterAudience $audience): array => [$audience->value => NewsletterRecipients::count($audience)])->all();
    }

    private function fill(Newsletter $newsletter, Request $request, ImageUploader $images): void
    {
        PastedLink::cleanRequest($request, ['video_url', 'button_url']);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_image' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! NewsletterLinks::isValidVideo((string) $value)) {
                    $fail(NewsletterLinks::VIDEO_INVALID);
                }
            }],
            'button_label' => ['nullable', 'string', 'max:60', 'required_with:button_url'],
            'button_url' => ['nullable', 'string', 'max:500', 'required_with:button_label', function (string $attribute, mixed $value, \Closure $fail): void {
                if (NewsletterLinks::absolute((string) $value) === null) {
                    $fail(NewsletterLinks::BUTTON_INVALID);
                }
            }],
            'audience' => ['required', Rule::enum(NewsletterAudience::class)],
        ], [
            'subject.required' => 'Écrivez l\'objet de l\'e-mail.',
            'subject.max' => 'L\'objet est trop long (150 caractères au maximum).',
            'body.required' => 'Écrivez le texte du message.',
            'body.max' => 'Le texte est trop long (10 000 caractères au maximum).',
            'image.image' => 'L\'image doit être une photo (JPEG, PNG ou WebP).',
            'image.mimes' => 'L\'image doit être une photo (JPEG, PNG ou WebP).',
            'image.max' => 'L\'image ne doit pas dépasser 8 Mo.',
            'button_label.required_with' => 'Écrivez le texte du bouton (par exemple « Découvrir l\'accompagnement »), ou videz le lien du bouton.',
            'button_label.max' => 'Le texte du bouton est trop long (60 caractères au maximum).',
            'button_url.required_with' => 'Collez le lien du bouton, ou videz le texte du bouton.',
        ]);

        $newsletter->fill([
            'subject' => trim($validated['subject']),
            'body' => trim($validated['body']),
            'video_url' => filled($validated['video_url'] ?? null) ? $validated['video_url'] : null,
            'button_label' => filled($validated['button_label'] ?? null) ? trim($validated['button_label']) : null,
            'button_url' => filled($validated['button_url'] ?? null) ? $validated['button_url'] : null,
            'audience' => $validated['audience'],
        ]);

        $old = $newsletter->image_path;

        if ($request->hasFile('image')) {
            $newsletter->image_path = $images->fit($request->file('image'), 'newsletters', 1200, 1600);
        } elseif ($request->boolean('remove_image')) {
            $newsletter->image_path = null;
        }

        $newsletter->save();

        if ($old !== null && $old !== $newsletter->image_path) {
            $images->delete($old);
        }
    }
}
