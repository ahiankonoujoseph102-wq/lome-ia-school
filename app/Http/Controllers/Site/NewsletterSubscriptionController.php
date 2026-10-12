<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Newsletters\BulkEmailSubscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Désabonnement des envois groupés depuis le lien d'un e-mail (signé, sans connexion) :
 * - lien en bas de l'e-mail (GET) : désabonnement en un clic, page de confirmation avec « Me réabonner » ;
 * - désabonnement en un clic de Gmail (POST, en-tête « List-Unsubscribe-Post »), sans page.
 * Seuls les envois groupés s'arrêtent : les e-mails du compte, des achats et des groupes continuent.
 */
class NewsletterSubscriptionController extends Controller
{
    public function unsubscribe(Request $request, User $user): View
    {
        BulkEmailSubscription::unsubscribe($user, $this->newsletterId($request));

        return view('site.newsletter-unsubscribe', [
            'subscribed' => false,
            'resubscribeUrl' => BulkEmailSubscription::resubscribeUrl($user),
        ]);
    }

    public function oneClick(Request $request, User $user): Response
    {
        BulkEmailSubscription::unsubscribe($user, $this->newsletterId($request));

        return response('Désabonnement enregistré.', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function resubscribe(User $user): View
    {
        BulkEmailSubscription::resubscribe($user);

        return view('site.newsletter-unsubscribe', ['subscribed' => true, 'resubscribeUrl' => null]);
    }

    private function newsletterId(Request $request): ?int
    {
        $id = $request->query('envoi');

        return is_numeric($id) ? (int) $id : null;
    }
}
