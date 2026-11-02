<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    private const MODULES = ['FARMER', 'SUPPLY', 'PROGRAM', 'DISTRIBUTION', 'USER'];

    public function index(Request $request): View
    {
        $module = $request->query('module');
        $activities = AuditLog::with('user')
            ->when(in_array($module, self::MODULES, true), fn ($query) => $query->where('module', $module))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('activity.index', [
            'activities' => $activities,
            'module' => in_array($module, self::MODULES, true) ? $module : '',
            'modules' => self::MODULES,
        ]);
    }
}
