<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\Checkout\PublicCheckoutDraftAccess;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;

final class PublicCheckoutDraftController extends Controller
{
    public function store(Request $request, ConfiguredCommercialPrincipalResolver $resolver, PublicCheckoutDraftAccess $drafts): JsonResponse
    {
        $validated = $request->validate(['instructions' => ['required', 'array']]);
        $draft = $drafts->save($request, $resolver->resolve(), $validated['instructions']);

        return response()->json([
            'reference' => $draft['checkout']->reference,
            'guest_token' => $draft['token'],
            'saved_at' => $draft['checkout']->updated_at?->toIso8601String(),
            'expires_at' => $draft['checkout']->draft_expires_at?->toIso8601String(),
        ]);
    }

    public function show(Request $request, ConfiguredCommercialPrincipalResolver $resolver, PublicCheckoutDraftAccess $drafts): JsonResponse
    {
        $draft = $drafts->active($request, $resolver->resolve());

        abort_unless($draft !== null
            && hash_equals($draft['token'], trim((string) $request->header(PublicCheckoutDraftAccess::TokenHeader))), 404);

        return response()->json([
            'reference' => $draft['checkout']->reference,
            'instructions' => $draft['checkout']->instructions_ciphertext,
            'saved_at' => $draft['checkout']->updated_at?->toIso8601String(),
            'expires_at' => $draft['checkout']->draft_expires_at?->toIso8601String(),
        ]);
    }
}
