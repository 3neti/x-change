<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Commissioning;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LBHurtado\XChange\Http\Middleware\EnsureXChangeIsCommissioned;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use LBHurtado\XChange\Services\Configuration\PreInstallReadinessInspector;
use LBHurtado\XChange\Services\Configuration\RuntimeOperationsChecklist;
use LBHurtado\XChange\Services\Configuration\SystemPrincipalAccountReadinessInspector;

final readonly class CommissioningChecklistController
{
    public function __construct(
        private Application $application,
        private CommissioningStateResolver $commissioning,
        private PreInstallReadinessInspector $readiness,
        private RuntimeOperationsChecklist $runtimeOperations,
        private SystemPrincipalAccountReadinessInspector $systemPrincipalAccount,
    ) {}

    public function show(Request $request): Response
    {
        abort_unless($this->isAuthorized($request), Response::HTTP_NOT_FOUND);

        return response()->view('x-change::commissioning.checklist', [
            'commissioning' => $this->commissioning->resolve(),
            'readiness' => $this->readiness->inspect(),
            'runtime' => $this->runtimeOperations->describe(),
            'installationChecks' => [$this->systemPrincipalAccount->inspect()],
            'checkedAt' => now(),
        ], Response::HTTP_OK, EnsureXChangeIsCommissioned::headers());
    }

    public function unlock(Request $request): RedirectResponse
    {
        $request->validate(['access_token' => ['required', 'string', 'max:512']]);
        $configured = $this->effectiveAccessToken();

        abort_unless(
            $configured !== ''
            && hash_equals($configured, (string) $request->input('access_token')),
            Response::HTTP_NOT_FOUND,
        );

        $request->session()->regenerate();
        $request->session()->put($this->sessionKey(), hash('sha256', $configured));

        return redirect()->route('x-change.commissioning.checklist');
    }

    private function isAuthorized(Request $request): bool
    {
        $configured = $this->effectiveAccessToken();
        $stored = (string) $request->session()->get($this->sessionKey(), '');

        return $configured !== ''
            && $stored !== ''
            && hash_equals(hash('sha256', $configured), $stored);
    }

    private function sessionKey(): string
    {
        return 'x-change.commissioning.authorized';
    }

    private function effectiveAccessToken(): string
    {
        $configured = trim((string) config('x-change.commissioning.access_token'));

        if ($configured !== '') {
            return $configured;
        }

        if (! $this->application->environment('local')) {
            return '';
        }

        return trim((string) config('x-change.commissioning.local_fallback_access_token'));
    }
}
