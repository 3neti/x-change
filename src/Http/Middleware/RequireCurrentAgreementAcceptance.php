<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Services\Legal\CurrentAgreementService;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireCurrentAgreementAcceptance
{
    public function __construct(private CurrentAgreementService $agreements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->agreements->enabled()) {
            return $next($request);
        }

        $user = $request->user();
        $document = $this->agreements->document();

        if ($user instanceof Model && $this->agreements->hasAccepted($user, $document)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Accept the current end user agreement before continuing.',
                'agreement' => [
                    'key' => $document->key,
                    'version' => $document->version,
                    'sha256' => $document->sha256,
                    'url' => route('x-change.legal.eula.show'),
                ],
            ], 428);
        }

        $agreementUrl = route('x-change.legal.eula.show');

        // The agreement is a server-rendered legal document. An Inertia visit
        // cannot render that response in place, so force a real browser
        // navigation when authentication lands on the agreement gate.
        if ($request->header('X-Inertia') !== null) {
            return Inertia::location($agreementUrl);
        }

        return redirect()->guest($agreementUrl);
    }
}
