<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleAccess;
use LBHurtado\XChange\Services\Commercial\ConfiguredPublicIssuanceEscrowPrincipalResolver;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\StoredValue\StoredValueCollectionService;

final class StoredValueCollectionController extends Controller
{
    public function __construct(
        private readonly StoredValueCollectionService $collections,
        private readonly CheckoutConsoleAccess $operations,
        private readonly ConfiguredPublicIssuanceEscrowPrincipalResolver $escrow,
        private readonly PartnerApiRequestContext $partners,
    ) {}

    public function page(Request $request): Response
    {
        $channel = $this->channel($request);
        $this->collector($request, $channel);
        $base = $channel === 'operations'
            ? route('x-change.operations.stored-value-collections.store', [], false)
            : route('x-change.cockpit.stored-value-collections.store', [], false);
        $routePrefix = $channel === 'operations'
            ? 'x-change.operations.stored-value-collections.'
            : 'x-change.cockpit.stored-value-collections.';

        return Inertia::render('x-change/pos/CollectStoredValue', [
            'channel' => $channel,
            'endpoints' => [
                'start' => $base,
                'status' => route($routePrefix.'show', ['reference' => '__REFERENCE__'], false),
                'verify' => route($routePrefix.'verify', ['reference' => '__REFERENCE__'], false),
                'commit' => route($routePrefix.'commit', ['reference' => '__REFERENCE__'], false),
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'in:PHP'],
        ]);
        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '' || strlen($key) > 160, 422, 'A bounded Idempotency-Key is required.');
        $channel = $this->channel($request);
        $result = $this->collections->start(
            $this->collector($request, $channel),
            $channel,
            $validated['code'],
            (int) $validated['amount_minor'],
            $validated['currency'],
            $key,
            $this->sessionHash($request, $channel),
        );

        return response()->json($result, 201)->header('Cache-Control', 'private, no-store');
    }

    public function verify(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'min:4', 'max:12']]);
        $channel = $this->channel($request);
        $result = $this->collections->verify(
            $this->collector($request, $channel),
            $channel,
            $reference,
            $validated['code'],
            $this->sessionHash($request, $channel),
        );

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function commit(Request $request, string $reference): JsonResponse
    {
        $channel = $this->channel($request);
        $result = $this->collections->commit(
            $this->collector($request, $channel),
            $channel,
            $reference,
            $this->sessionHash($request, $channel),
        );

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, string $reference): JsonResponse
    {
        $channel = $this->channel($request);
        $result = $this->collections->status(
            $this->collector($request, $channel),
            $channel,
            $reference,
            $this->sessionHash($request, $channel),
        );

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    private function channel(Request $request): string
    {
        return (string) $request->route('collection_channel');
    }

    private function collector(Request $request, string $channel): Model
    {
        if ($channel === 'partner') {
            return $this->partners->client();
        }
        if ($channel === 'cockpit') {
            $user = $request->user();
            abort_unless($user instanceof Model && $user->exists, 403);

            return $user;
        }

        $principal = $this->escrow->resolve();
        abort_unless($principal instanceof CommercialPrincipal && $this->operations->owner($request, $principal), 404);

        return $principal;
    }

    private function sessionHash(Request $request, string $channel): ?string
    {
        if ($channel !== 'operations') {
            return null;
        }

        $nonce = $request->session()->get('x-change.checkout.collection_nonce');
        if (! is_string($nonce) || strlen($nonce) !== 64) {
            $nonce = bin2hex(random_bytes(32));
            $request->session()->put('x-change.checkout.collection_nonce', $nonce);
        }

        return hash('sha256', $nonce);
    }
}
