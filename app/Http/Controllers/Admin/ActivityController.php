<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Journal d'activité (admin uniquement) : qui a fait quoi et quand, filtrable par action, personne et dates.
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', Rule::enum(AdminAction::class)],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $activities = AdminActivity::query()
            ->with('user')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['user'] ?? null, fn ($query, $user) => $query->where('user_id', $user))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('site.admin.activity', [
            'activities' => $activities,
            'filters' => $filters,
            'actions' => collect(AdminAction::cases())->mapWithKeys(fn (AdminAction $action): array => [$action->value => $action->label()])->sort()->all(),
            'people' => User::query()->whereIn('id', AdminActivity::query()->whereNotNull('user_id')->distinct()->select('user_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
