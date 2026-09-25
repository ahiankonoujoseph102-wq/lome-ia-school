<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminTodo;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\LessonView;
use App\Models\Purchase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * Accueil de l'administration : chiffres du jour, de la semaine et du mois (paiements validés ;
 * provisoires et en attente à part), inscriptions, élèves actifs, ventes sur 30 jours,
 * formations les plus vendues et « À traiter ».
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $now = CarbonImmutable::now();
        $periods = [
            'today' => ['label' => 'Aujourd\'hui', 'from' => $now->startOfDay()],
            'week' => ['label' => 'Cette semaine', 'from' => $now->startOfWeek()],
            'month' => ['label' => 'Ce mois-ci', 'from' => $now->startOfMonth()],
        ];

        $stats = [];

        foreach ($periods as $key => $period) {
            $stats[$key] = [
                'label' => $period['label'],
                'sales' => Purchase::query()->where('status', PurchaseStatus::Validated)->where('created_at', '>=', $period['from'])->count(),
                'revenue' => (int) Purchase::query()->where('status', PurchaseStatus::Validated)->where('created_at', '>=', $period['from'])->sum('amount'),
                'unverified' => (int) Purchase::query()->whereIn('status', PurchaseStatus::unverified())->where('created_at', '>=', $period['from'])->sum('amount'),
                'signups' => User::query()->where('created_at', '>=', $period['from'])->count(),
            ];
        }

        return view('site.admin.dashboard', [
            'stats' => $stats,
            'activeStudents' => LessonView::query()->where('last_viewed_at', '>=', $now->subDays(7))->distinct()->count('user_id'),
            'activeStudentsMonth' => LessonView::query()->where('last_viewed_at', '>=', $now->subDays(30))->distinct()->count('user_id'),
            'chart' => $this->dailySales($now),
            'top' => $this->topFormations($now),
            'todo' => [
                ['label' => 'Paiements à vérifier', 'count' => AdminTodo::paymentsToVerify(), 'route' => route('admin.payments.index'), 'icon' => 'banknotes'],
                ['label' => 'Retraits à payer', 'count' => AdminTodo::withdrawalsToPay(), 'route' => route('admin.affiliates.withdrawals'), 'icon' => 'arrow-up-tray'],
                ['label' => 'Messages signalés', 'count' => AdminTodo::openReports(), 'route' => route('moderation.reports.index'), 'icon' => 'flag'],
                ['label' => 'Consultations du jour', 'count' => AdminTodo::consultationsToday(), 'route' => route('admin.consultations.index'), 'icon' => 'video-camera'],
                ['label' => 'Suppléments à rembourser', 'count' => AdminTodo::refundsDue(), 'route' => route('admin.sessions.index'), 'icon' => 'receipt-refund'],
            ],
        ]);
    }

    /**
     * Chiffre d'affaires validé par jour sur les 30 derniers jours (jours sans vente compris).
     *
     * @return list<array{date: CarbonImmutable, revenue: int, sales: int}>
     */
    private function dailySales(CarbonImmutable $now): array
    {
        $from = $now->subDays(29)->startOfDay();
        $purchases = Purchase::query()
            ->where('status', PurchaseStatus::Validated)
            ->where('created_at', '>=', $from)
            ->get(['amount', 'created_at'])
            ->groupBy(fn (Purchase $purchase): string => (string) $purchase->created_at?->format('Y-m-d'));

        $days = [];

        for ($day = $from; $day->lte($now); $day = $day->addDay()) {
            $sales = $purchases->get($day->format('Y-m-d'));
            $days[] = ['date' => $day, 'revenue' => (int) ($sales?->sum('amount') ?? 0), 'sales' => $sales?->count() ?? 0];
        }

        return $days;
    }

    /**
     * Formations les plus vendues sur 30 jours (paiements validés, suppléments exclus).
     *
     * @return list<array{title: string, sales: int, revenue: int}>
     */
    private function topFormations(CarbonImmutable $now): array
    {
        $rows = Purchase::query()
            ->where('status', PurchaseStatus::Validated)
            ->whereNull('live_session_id')
            ->where('created_at', '>=', $now->subDays(30))
            ->selectRaw('formation_id, count(*) as sales, sum(amount) as revenue')
            ->groupBy('formation_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $titles = Formation::query()->whereIn('id', $rows->pluck('formation_id'))->pluck('title', 'id');

        return array_values($rows->map(fn ($row): array => [
            'title' => (string) ($titles[$row->getAttribute('formation_id')] ?? 'Formation supprimée'),
            'sales' => (int) $row->getAttribute('sales'),
            'revenue' => (int) $row->getAttribute('revenue'),
        ])->all());
    }
}
