@php
    use App\Support\Money;

    $firstName = $affiliate->user->first_name;
    $days = (int) config('affiliate.availability_days', 7);
    [$title, $heading, $intro] = match ($kind) {
        'commission_created' => ['Nouvelle commission', 'Nouvelle commission : '.Money::fcfa((int) $commission?->amount), 'Bonjour '.$firstName.', un achat passé grâce à votre lien vient d\'être validé.'],
        'commission_available' => ['Commissions disponibles', Money::fcfa($amount).' disponibles', 'Bonjour '.$firstName.', le délai de '.$days.' jours est passé : ces commissions sont maintenant dans votre solde.'],
        'withdrawal_paid' => ['Retrait payé', 'Votre retrait a été payé', 'Bonjour '.$firstName.', nous vous avons envoyé votre retrait par Mobile Money.'],
        'withdrawal_rejected' => ['Retrait refusé', 'Votre demande de retrait a été refusée', 'Bonjour '.$firstName.', nous n\'avons pas pu payer votre demande de retrait. Le montant est revenu dans votre solde.'],
        default => ['Affiliation', 'Programme d\'affiliation', 'Bonjour '.$firstName.'.'],
    };
    $rows = match ($kind) {
        'commission_created' => [
            'Achat' => $commission?->itemLabel(),
            'Commission' => Money::fcfa((int) $commission?->amount).' ('.$commission?->rate.' % de '.Money::fcfa((int) $commission?->base_amount).')',
            'Disponible le' => $commission?->available_at?->translatedFormat('j F Y'),
        ],
        'withdrawal_paid' => [
            'Montant demandé' => Money::fcfa((int) $withdrawal?->amount),
            'Frais d\'envoi' => Money::fcfa((int) $withdrawal?->fee),
            'Montant envoyé' => Money::fcfa((int) $withdrawal?->net_amount),
            'Envoyé sur' => $withdrawal?->payout_method_name.' · '.$withdrawal?->formattedPhone(),
            'Identifiant de transaction' => $withdrawal?->transaction_id,
        ],
        'withdrawal_rejected' => [
            'Montant demandé' => Money::fcfa((int) $withdrawal?->amount),
            'Motif' => $withdrawal?->rejection_reason,
        ],
        default => [],
    };
@endphp
@extends('emails.layout')

@section('title', $title)
@section('heading', $heading)
@section('intro', $intro)

@section('content')
    @if ($kind === 'commission_created')
        <p style="margin:0 0 14px;">La commission devient disponible {{ $days }} jours après la validation du paiement. Elle est annulée si l'achat est remboursé d'ici là.</p>
    @endif
    @if ($rows !== [])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
            @foreach (array_filter($rows) as $label => $value)
                <tr>
                    <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:140px;vertical-align:top;">{{ $label }}</td>
                    <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('student.affiliate.index') }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Mon espace affilié</a>
    </p>
@endsection
