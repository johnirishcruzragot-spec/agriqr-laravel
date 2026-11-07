<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $isTechnician = $user->role === 'technician';
        $stats = [
            'farmers' => $user->canAccessModule('farmers') ? Farmer::count() : 0,
            'active_farmers' => $user->canAccessModule('farmers') ? Farmer::where('status', 'active')->count() : 0,
            'supplies' => $user->canAccessModule('inventory') ? Supply::where('status', 'active')->count() : 0,
            'programs' => $user->canAccessModule('programs') ? SubsidyProgram::count() : 0,
            'active_programs' => $user->canAccessModule('programs') ? SubsidyProgram::where('status', 'active')->count() : 0,
            'distributions' => $user->canAccessModule('distributions') ? Distribution::count() : 0,
            'completed_distributions' => $user->canAccessModule('distributions')
                ? Distribution::where('transaction_status', 'completed')->count()
                : 0,
            'users' => $user->canAccessModule('users') ? User::where('is_active', true)->count() : 0,
            'active_staff' => $user->canAccessModule('users')
                ? User::where('role', 'technician')->where('is_active', true)->count()
                : 0,
            'active_admins' => $user->canAccessModule('users')
                ? User::whereIn('role', ['admin', 'system_admin'])->where('is_active', true)->count()
                : 0,
        ];

        $distributionTrend = $user->canAccessModule('distributions')
            ? Distribution::query()
                ->where('transaction_status', 'completed')
                ->whereBetween('distributed_at', [today()->subDays(6)->startOfDay(), now()->endOfDay()])
                ->when($isTechnician, fn ($query) => $query->where('processed_by', $user->id))
                ->selectRaw('DATE(distributed_at) as distribution_date, COUNT(*) as total')
                ->groupBy('distribution_date')
                ->orderBy('distribution_date')
                ->pluck('total', 'distribution_date')
            : collect();
        $distributionTrendMax = max(1, (int) $distributionTrend->max());
        $distributionChart = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($distributionTrend, $distributionTrendMax): array {
                $date = today()->subDays($daysAgo);
                $total = (int) $distributionTrend->get($date->toDateString(), 0);

                return [
                    'label' => $date->format('D'),
                    'date' => $date->format('M j'),
                    'total' => $total,
                    'height' => $total === 0 ? 4 : max(12, (int) round($total / $distributionTrendMax * 100)),
                ];
            })
            ->all();

        $stock = $user->canAccessModule('inventory')
            ? Inventory::query()
                ->join('supplies', 'supplies.id', '=', 'inventories.supply_id')
                ->where('supplies.status', 'active')
                ->selectRaw('COUNT(*) as items, SUM(CASE WHEN inventories.quantity <= supplies.minimum_stock THEN 1 ELSE 0 END) as low_stock')
                ->first()
            : null;

        $recentDistributions = $user->canAccessModule('distributions')
            ? Distribution::with(['farmer', 'program', 'processor'])
                ->when($isTechnician, fn ($query) => $query->where('processed_by', $user->id))
                ->latest('distributed_at')
                ->limit(6)
                ->get()
            : collect();

        $recentMovements = $user->canAccessModule('inventory')
            ? InventoryMovement::with(['supply', 'performedBy'])->latest()->limit(6)->get()
            : collect();
        $lowStockSupplies = $user->canAccessModule('inventory')
            ? Inventory::with('supply')
                ->whereHas('supply', fn ($query) => $query->where('status', 'active'))
                ->whereRaw('quantity <= (SELECT minimum_stock FROM supplies WHERE supplies.id = inventories.supply_id)')
                ->orderBy('quantity')
                ->limit(6)
                ->get()
            : collect();
        $recentActivities = $user->canAccessModule('activity')
            ? AuditLog::with('user')->latest()->limit(6)->get()
            : collect();

        return view('dashboard', [
            'stats' => $stats,
            'isTechnician' => $isTechnician,
            'distributionChart' => $distributionChart,
            'distributionChartTotal' => array_sum(array_column($distributionChart, 'total')),
            'stockSummary' => $stock,
            'recentDistributions' => $recentDistributions,
            'recentMovements' => $recentMovements,
            'lowStockSupplies' => $lowStockSupplies,
            'recentActivities' => $recentActivities,
        ]);
    }
}
