<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Cockpit\IssuanceDiagnosticJournal;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class JournalIssuanceAttempt
{
    public function __construct(private IssuanceDiagnosticJournal $journal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->header('X-XChange-Submission-Reference');
        $reference = is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
        try {
            $evidence = $this->journal->requestEvidence($request);
        } catch (Throwable) {
            $evidence = ['source' => 'server_confirmed'];
        }
        $this->journal->record($request, $reference, 'received', $evidence);
        try {
            $response = $next($request);
            $data = $response instanceof JsonResponse ? $response->getData(true) : [];
            $status = $response->getStatusCode();
            $event = $status === 422 ? 'validation_rejected' : ($status >= 400 ? 'failed' : 'response_returned');
            $result = ['http_status' => $status];
            if (is_array($data)) {
                $result['error_fields'] = $this->errorFields(is_array($data['errors'] ?? null) ? $data['errors'] : []);
                foreach (['result.code', 'order.reference', 'funding_order.reference'] as $path) {
                    $value = data_get($data, $path);
                    if (is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $value)) {
                        $result[str_replace('.', '_', $path)] = $value;
                    }
                }
                if (is_string(data_get($data, 'result.code'))) {
                    $event = 'succeeded';
                }
            }
            $this->journal->record($request, $reference, $event, ['source' => 'server_confirmed', ...$result]);
            $response->headers->set('X-XChange-Submission-Reference', $reference);

            return $response;
        } catch (Throwable $error) {
            $this->journal->record($request, $reference, $error instanceof ValidationException ? 'validation_rejected' : 'failed', [
                'source' => 'server_confirmed',
                'http_status' => $error instanceof ValidationException ? $error->status : ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500),
                'error_fields' => $error instanceof ValidationException ? $this->errorFields($error->errors()) : [],
                'exception_class' => $error::class,
            ]);
            throw $error;
        }
    }

    /**
     * @param  array<array-key, mixed>  $errors
     * @return list<string>
     */
    private function errorFields(array $errors): array
    {
        return array_slice(array_values(array_filter(array_keys($errors), static fn (mixed $key): bool => is_string($key)
            && (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]{0,99}$/', $key))), 0, 20);
    }
}
