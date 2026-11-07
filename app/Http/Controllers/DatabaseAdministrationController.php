<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;

class DatabaseAdministrationController extends Controller
{
    public function index(): View
    {
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver", $connectionName);
        $startedAt = hrtime(true);
        $connected = false;
        $responseTime = null;

        try {
            DB::connection($connectionName)->select('SELECT 1');
            $connected = true;
            $responseTime = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        } catch (PDOException $exception) {
            Log::warning('Admin database health check failed.', [
                'connection' => $connectionName,
                'exception' => $exception::class,
            ]);
        }

        return view('database.index', [
            'connectionName' => $connectionName,
            'driver' => match ($driver) {
                'mysql' => 'MySQL',
                'mariadb' => 'MariaDB',
                'pgsql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
                'sqlsrv' => 'SQL Server',
                default => Str::headline($driver),
            },
            'connected' => $connected,
            'responseTime' => $responseTime,
            'checkedAt' => now(),
        ]);
    }
}
