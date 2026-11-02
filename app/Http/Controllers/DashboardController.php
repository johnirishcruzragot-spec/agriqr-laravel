<?php

namespace App\Http\Controllers;

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
        $stats = [
            'farmers' => Farmer::count(),
            'active_farmers' => Farmer::where('status', 'active')->count(),
            'supplies' => Supply::where('status', 'active')->count(),
            'programs' => SubsidyProgram::where('status', 'active')->count(),
            'distributions' => Distribution::where('transaction_status', 'completed')->count(),
            'users' => User::where('is_active', true)->count(),
        ];

        $stock = Inventory::query()
            ->join('supplies', 'supplies.id', '=', 'inventories.supply_id')
            ->where('supplies.status', 'active')
            ->selectRaw('COUNT(*) as items, SUM(CASE WHEN inventories.quantity <= supplies.minimum_stock THEN 1 ELSE 0 END) as low_stock')
            ->first();

        $recentDistributions = Distribution::with(['farmer', 'program', 'processor'])
            ->latest('distributed_at')
            ->limit(6)
            ->get();

        $recentMovements = InventoryMovement::with(['supply', 'performedBy'])
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'stockSummary' => $stock,
            'recentDistributions' => $recentDistributions,
            'recentMovements' => $recentMovements,
        ]);
    }
}
