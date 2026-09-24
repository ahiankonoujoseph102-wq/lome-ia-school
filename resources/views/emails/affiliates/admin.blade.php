@php
    use App\Payments\PaymentMethods;
    use App\Support\Money;
    use App\Support\PhoneNumber;

    $user = $affiliate->user;
    [$title, $intro] = match ($kind) {
        'withdrawal_requested' => ['Nouvelle demande de retrait', Money::fcfa((int) $withdrawal?->amount).' · '.$user->name],
        'payout_changed' => ['Coordonnées de retrait modifiées', $user->name.' a modifié ses coordonnées de retrait Mobile Money.'],
        default => ['Affiliation', $user->name],
    };
    $rows = $kind === 'withdrawal_requested' ? [
        'Affilié' => $user->name.' · '.$user->email,
        'Montant demandé' => Money::fcfa((int) $withdrawal?->amount),
        'Envoyer sur' => $withdrawal?->payout_method_name.' · '.$withdrawal?->formattedPhone(),
        'Titulaire' => $withdrawal?->payout_name,
    ] : [
        'Affilié' => $user->name.' · '.$user->email,
        'Avant' => ($previous['payout_method'] ?? null) ? (PaymentMethods::find($previous['payout_method'])?->name ?? $previous['payout_method']).' · '.PhoneNumber::format($previous['payout_phone'] ?? null).' · '.($previous['payout_name'] ?? '') : 'Non renseignées',
        'Maintenant' => $affiliate->payoutSummary(),
    ];
@endphp
@extends('emails.layout')

@section('title', $title)
@section('heading', $title)
@section('intro', $intro)

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:120px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    @if ($kind === 'payout_changed')
        <p style="margin:14px 0 0;">Vérifiez que ce changement vient bien de l'affilié avant de payer son prochain retrait.</p>
    @endif
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.affiliates.withdrawals') }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Retraits à payer</a>
    </p>
@endsection
