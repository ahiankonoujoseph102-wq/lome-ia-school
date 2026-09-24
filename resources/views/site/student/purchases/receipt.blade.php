{{-- Reçu imprimable d'un paiement validé : page autonome, claire, pensée pour l'impression. --}}
@php
    $owner = config('site.legal.owner');
    $user = $purchase->user;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="only light">
    <title>Reçu {{ $purchase->receipt_number }} | Lomé IA School</title>
    <style>
        :root { color-scheme: only light; }
        body { margin: 0; background: #eef0f7; }
        .receipt-page, .receipt-page * { box-sizing: border-box; }
        .receipt-page { min-height: 100vh; background: #eef0f7; color: #1e2340; font: 15px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .receipt-page .page { max-width: 720px; margin: 0 auto; padding: 16px; }
        .receipt-page .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .receipt-page .btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 12px 20px; font-weight: 700; font-size: 15px; text-decoration: none; border: 0; cursor: pointer; }
        .receipt-page .btn-gold { background: #fbbf24; color: #0b1030; }
        .receipt-page .btn-light { background: #fff; color: #1e2340; border: 1px solid #d7dae8; }
        .receipt-page .receipt { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgb(11 16 48 / .08); }
        .receipt-page .head { background: linear-gradient(135deg, #0b1030, #3b1c8c); color: #fff; padding: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; }
        .receipt-page .brand { margin: 0; font-weight: 800; font-size: 18px; }
        .receipt-page .brand span { display: inline-block; width: 8px; height: 18px; background: #fbbf24; border-radius: 2px; vertical-align: -3px; margin-right: 8px; }
        .receipt-page .head h1 { margin: 10px 0 0; font-size: 24px; font-weight: 800; color: #fff; }
        .receipt-page .meta { text-align: right; font-size: 14px; color: #ddd6fe; }
        .receipt-page .meta strong { display: block; color: #fff; font-size: 16px; }
        .receipt-page .paid { display: inline-block; margin-top: 8px; background: #d1fae5; color: #065f46; border-radius: 999px; padding: 4px 12px; font-size: 13px; font-weight: 700; }
        .receipt-page .body { padding: 24px; }
        .receipt-page .grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .receipt-page h2 { margin: 0 0 6px; font-weight: 700; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: #6b7094; }
        .receipt-page p { margin: 0; }
        .receipt-page table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .receipt-page th, .receipt-page td { padding: 12px 0; border-bottom: 1px solid #eceef6; text-align: left; vertical-align: top; }
        .receipt-page th { color: #6b7094; font-weight: 500; width: 45%; }
        .receipt-page td { font-weight: 600; word-break: break-word; }
        .receipt-page .total td, .receipt-page .total th { border-bottom: 0; font-size: 18px; color: #0b1030; }
        .receipt-page .foot { padding: 16px 24px; background: #f7f7fb; font-size: 12px; color: #6b7094; }
        @media (max-width: 480px) {
            .receipt-page .meta { text-align: left; }
        }
        @media print {
            body, .receipt-page { background: #fff; }
            .receipt-page .page { padding: 0; max-width: none; }
            .receipt-page .actions { display: none; }
            .receipt-page .receipt { box-shadow: none; border-radius: 0; }
            .receipt-page .head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="receipt-page">
    <div class="page">
        <div class="actions">
            <button type="button" class="btn btn-gold" onclick="window.print()">Imprimer ou enregistrer en PDF</button>
            <a href="{{ route('student.purchases.index') }}" class="btn btn-light">Retour à mes achats</a>
        </div>

        <article class="receipt">
            <header class="head">
                <div>
                    <p class="brand"><span></span>Lomé IA School</p>
                    <h1>Reçu de paiement</h1>
                </div>
                <div class="meta">
                    N° <strong>{{ $purchase->receipt_number }}</strong>
                    Le {{ $purchase->verified_at?->translatedFormat('j F Y') }}
                    <br><span class="paid">Payé</span>
                </div>
            </header>

            <div class="body">
                <div class="grid">
                    <section>
                        <h2>Émis par</h2>
                        <p><strong>{{ config('site.legal.company_name') }}</strong></p>
                        <p>{{ $owner['name'] }}</p>
                        <p>{{ $owner['address'] }}</p>
                        <p>{{ $owner['phone'] }} · {{ $owner['email'] }}</p>
                    </section>
                    <section>
                        <h2>Élève</h2>
                        <p><strong>{{ $user?->name }}</strong></p>
                        <p>{{ $user?->email }}</p>
                        <p>{{ $user?->formatted_phone }}</p>
                    </section>
                </div>

                <table>
                    <tr><th>Formation</th><td>{{ $purchase->formation->title }}</td></tr>
                    <tr><th>Date du paiement</th><td>{{ $purchase->created_at?->translatedFormat('j F Y') }}</td></tr>
                    <tr><th>Moyen de paiement</th><td>{{ $purchase->payment_method_name }}</td></tr>
                    <tr><th>Identifiant de transaction</th><td>{{ $purchase->transaction_id }}</td></tr>
                    <tr class="total"><th>Montant payé</th><td>{{ $purchase->formatted_amount }}</td></tr>
                </table>
            </div>

            <footer class="foot">
                Paiement vérifié le {{ $purchase->verified_at?->translatedFormat('j F Y') }}. Montant en francs CFA (XOF).
            </footer>
        </article>
    </div>
</div>
</body>
</html>
