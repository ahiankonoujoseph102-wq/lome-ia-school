{{-- Tableau récapitulatif d'un achat, inclus dans les e-mails de paiement. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0;border:1px solid #e4e6f0;border-radius:14px;font-size:14px;">
    @foreach (array_filter([
        'Formation' => $purchase->formation->title,
        'Montant' => $purchase->formatted_amount,
        'Moyen de paiement' => $purchase->payment_method_name,
        'Identifiant de transaction' => $purchase->transaction_id,
        'Référence' => $purchase->receipt_number,
    ] + ($extra ?? [])) as $label => $value)
        <tr>
            <td style="padding:10px 14px;color:#7a7f99;border-bottom:1px solid #eef0f7;">{{ $label }}</td>
            <td style="padding:10px 14px;text-align:right;font-weight:bold;border-bottom:1px solid #eef0f7;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
