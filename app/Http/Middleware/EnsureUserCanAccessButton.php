<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ButtonAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessButton
{
    public function __construct(private ButtonAccess $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $button): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->access->allows($user, $button), 403);

        return $next($request);
    }
}
