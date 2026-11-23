<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Services\Cockpit\IssuanceDiagnosticJournal;

final class CockpitIssuanceDiagnosticController extends Controller
{
    public function __invoke(Request $request, IssuanceDiagnosticJournal $journal): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_if(strlen($request->getContent()) > 8192, 413);
        $allowed = ['reference', 'http_status', 'content_type', 'request_id', 'url_evidence', 'failure_kind', 'error_fields'];
        abort_if(array_diff(array_keys($request->all()), $allowed) !== [], 422);
        $data = $request->validate([
            'reference' => ['required', 'uuid'],
            'failure_kind' => ['sometimes', Rule::in(['client_validation', 'http', 'network'])],
            'error_fields' => ['sometimes', 'array', 'max:20'],
            'error_fields.*' => ['string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_.]{0,99}$/'],
            'http_status' => ['nullable', 'integer', 'between:0,599'],
            'content_type' => ['nullable', Rule::in(['application/json', 'text/html', 'text/plain', 'other'])],
            'request_id' => ['nullable', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
            'url_evidence' => ['sometimes', 'array:editor,preview,submitted'],
            'url_evidence.*' => ['array:present,length,fingerprint'],
            'url_evidence.*.present' => ['required', 'boolean'],
            'url_evidence.*.length' => ['required', 'integer', 'between:0,65536'],
            'url_evidence.*.fingerprint' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $reference = $data['reference'];
        unset($data['reference']);
        $recorded = $journal->record($request, $reference, 'browser_reported', ['source' => 'browser_reported', ...$data]);

        return response()->json(['reference' => $reference, 'recorded' => $recorded], $recorded ? 202 : 503)->header('Cache-Control', 'no-store');
    }
}
