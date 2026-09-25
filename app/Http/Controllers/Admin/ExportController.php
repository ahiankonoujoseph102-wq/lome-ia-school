<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Admin\CsvExport;
use App\Admin\SalesQuery;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Consultation;
use App\Models\Purchase;
use App\Models\User;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CSV (admin uniquement) : ventes, élèves, commissions, retraits, consultations.
 * Compatibles Excel (UTF-8 avec BOM, séparateur « ; »). Chaque export est noté dans le journal.
 */
class ExportController extends Controller
{
    /**
     * @var array<string, string>
     */
    public const array TYPES = [
        'ventes' => 'Ventes',
        'eleves' => 'Élèves',
        'commissions' => 'Commissions d\'affiliation',
        'retraits' => 'Retraits d\'affiliation',
        'consultations' => 'Consultations gratuites',
    ];

    public function index(): View
    {
        return view('site.admin.exports', ['types' => self::TYPES]);
    }

    public function download(Request $request, string $type): StreamedResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        $response = match ($type) {
            'ventes' => $this->sales($request),
            'eleves' => $this->students($request),
            'commissions' => $this->commissions(),
            'retraits' => $this->withdrawals(),
            'consultations' => $this->consultations(),
        };

        ActivityLog::record(AdminAction::Export, 'Export CSV : '.mb_strtolower(self::TYPES[$type]).'.', properties: array_filter($request->query()));

        return $response;
    }

    private function sales(Request $request): StreamedResponse
    {
        $filters = $request->validate(SalesQuery::rules());
        $rows = SalesQuery::build($filters)->with(['user', 'formation', 'liveSession', 'promoCode', 'verifier'])->oldest()->lazy()->map(fn (Purchase $purchase): array => [
            $purchase->receipt_number,
            self::date($purchase->created_at),
            $purchase->user?->name,
            $purchase->user ? PhoneNumber::format($purchase->user->phone) : null,
            $purchase->user?->email,
            $purchase->formation->title,
            $purchase->isSessionSupplement() ? 'Supplément de session' : 'Formation',
            $purchase->original_amount ?? $purchase->amount,
            $purchase->discount_amount ?? 0,
            $purchase->promoCode?->code,
            $purchase->amount,
            $purchase->status->label(),
            $purchase->gateway->label(),
            $purchase->payment_method_name,
            $purchase->country,
            $purchase->payer_phone,
            $purchase->transaction_id,
            $purchase->affiliate_code,
            $purchase->verifier?->name,
            self::date($purchase->verified_at),
            $purchase->manual_reason ?? $purchase->rejection_reason,
        ]);

        return CsvExport::download('ventes-'.now()->format('Y-m-d').'.csv', [
            'Référence', 'Date', 'Élève', 'WhatsApp', 'E-mail', 'Formation', 'Type', 'Prix (FCFA)', 'Remise (FCFA)', 'Code promo',
            'Montant payé (FCFA)', 'Statut', 'Mode', 'Moyen de paiement', 'Pays', 'Numéro payeur', 'Identifiant de transaction',
            'Code affilié', 'Vérifié par', 'Vérifié le', 'Motif',
        ], $rows);
    }

    private function students(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'filtre' => ['nullable', 'string'],
            'formation' => ['nullable', 'integer'],
        ]);

        $rows = StudentController::search($filters)->withCount('enrollments')->oldest()->lazy()->map(fn (User $user): array => [
            $user->name,
            PhoneNumber::format($user->phone),
            $user->email,
            $user->role->label(),
            self::date($user->created_at),
            $user->enrollments_count,
            $user->wants_newsletter ? 'Oui' : 'Non',
            $user->isBlocked() ? 'Oui' : 'Non',
        ]);

        return CsvExport::download('eleves-'.now()->format('Y-m-d').'.csv', ['Nom', 'WhatsApp', 'E-mail', 'Rôle', 'Inscrit le', 'Formations', 'Accepte les annonces', 'Bloqué'], $rows);
    }

    private function commissions(): StreamedResponse
    {
        $rows = AffiliateCommission::query()->with(['affiliate.user', 'purchase.formation'])->oldest()->lazy()->map(fn (AffiliateCommission $commission): array => [
            self::date($commission->created_at),
            $commission->affiliate->user->name,
            $commission->affiliate->code,
            $commission->purchase?->receipt_number,
            $commission->purchase?->formation->title,
            $commission->type->value === 'sale' ? 'Vente' : 'Déduction',
            $commission->base_amount,
            $commission->rate,
            $commission->amount,
            $commission->status->label(),
            self::date($commission->available_at),
        ]);

        return CsvExport::download('commissions-'.now()->format('Y-m-d').'.csv', ['Date', 'Affilié', 'Code', 'Achat', 'Formation', 'Type', 'Base (FCFA)', 'Taux (%)', 'Commission (FCFA)', 'Statut', 'Disponible le'], $rows);
    }

    private function withdrawals(): StreamedResponse
    {
        $rows = AffiliateWithdrawal::query()->with(['affiliate.user', 'processor'])->oldest()->lazy()->map(fn (AffiliateWithdrawal $withdrawal): array => [
            self::date($withdrawal->created_at),
            $withdrawal->affiliate->user->name,
            $withdrawal->amount,
            $withdrawal->fee,
            $withdrawal->net_amount,
            $withdrawal->status->label(),
            $withdrawal->payout_method_name,
            $withdrawal->payout_phone,
            $withdrawal->payout_name,
            $withdrawal->transaction_id,
            $withdrawal->processor?->name,
            self::date($withdrawal->processed_at),
            $withdrawal->rejection_reason,
        ]);

        return CsvExport::download('retraits-'.now()->format('Y-m-d').'.csv', ['Demandé le', 'Affilié', 'Montant (FCFA)', 'Frais (FCFA)', 'Reçu (FCFA)', 'Statut', 'Moyen', 'Numéro', 'Titulaire', 'Identifiant de transaction', 'Traité par', 'Traité le', 'Motif du refus'], $rows);
    }

    private function consultations(): StreamedResponse
    {
        $rows = Consultation::query()->with(['formation', 'recommendedFormation'])->oldest('starts_at')->lazy()->map(fn (Consultation $consultation): array => [
            self::date($consultation->starts_at),
            $consultation->name,
            PhoneNumber::format($consultation->phone),
            $consultation->email,
            $consultation->topic,
            $consultation->formation?->title,
            $consultation->status->label(),
            $consultation->attendance?->label(),
            $consultation->recommendedFormation?->title,
            self::date($consultation->recommendation_sent_at),
            $consultation->user_id ? 'Oui' : 'Non',
        ]);

        return CsvExport::download('consultations-'.now()->format('Y-m-d').'.csv', ['Date', 'Nom', 'WhatsApp', 'E-mail', 'Besoin', 'Formation qui l\'intéresse', 'Statut', 'Présence', 'Formation recommandée', 'Recommandation envoyée le', 'Compte élève'], $rows);
    }

    private static function date(?CarbonInterface $date): ?string
    {
        return $date?->format('d/m/Y H:i');
    }
}
