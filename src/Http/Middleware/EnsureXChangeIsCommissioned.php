<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class EnsureXChangeIsCommissioned
{
    /** @var list<string> */
    private const AllowedPaths = [
        'up',
        'x/ready',
        'x/commissioning',
        'x/commissioning/checklist',
        'x/commissioning/assets/commissioning.css',
    ];

    public function __construct(private CommissioningStateResolver $commissioning) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (
            app()->runningUnitTests()
            && ! (bool) config('x-change.commissioning.enforce_during_tests', false)
        ) {
            return $next($request);
        }

        $state = $this->commissioning->resolve();

        if (
            ! (bool) config('x-change.commissioning.enabled', true)
            || in_array(trim($request->path(), '/'), self::AllowedPaths, true)
            || $state->isOperational()
        ) {
            return $next($request);
        }

        $headers = $this->headers();

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'message' => 'X-Change is not yet commissioned.',
                'state' => $state->state->value,
                'status_url' => url('/x/commissioning'),
            ], Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        }

        return response()->view('x-change::commissioning.status', [
            'commissioning' => $state,
            'checkedAt' => now(),
        ], Response::HTTP_SERVICE_UNAVAILABLE, $headers);
    }

    /** @return array<string, string> */
    public static function headers(): array
    {
        return [
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Retry-After' => (string) max(1, (int) config(
                'x-change.commissioning.retry_after_seconds',
                300,
            )),
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
