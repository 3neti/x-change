<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;

final class CockpitPublicAutoGenerateScenarioRunnerController extends Controller
{
    private const SessionKey = 'x-change.lifecycle.public-auto-generate.browser-result';

    public function show(Request $request): Response
    {
        $this->ensureEnabled();

        return Inertia::render('x-change/cockpit/PublicAutoGenerateScenarioRunner', [
            'run' => $request->session()->get(self::SessionKey),
        ]);
    }

    public function store(Request $request, LifecycleScenarioEngine $engine): RedirectResponse
    {
        $this->ensureEnabled();
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);

        $result = $engine->run(
            command: app(Command::class),
            scenarioKey: 'public_auto_generate_demo',
            options: new LifecycleScenarioRunOptions(
                issuer: (string) $operator->getKey(),
                json: true,
            ),
            output: new NullLifecycleOutput,
        );

        if ($result->exitCode !== Command::SUCCESS) {
            return back()->withErrors([
                'scenario' => (string) ($result->payload['message'] ?? 'The rollback-only lifecycle could not complete.'),
            ]);
        }

        return to_route('x-change.cockpit.campaigns.public-auto-generate-scenario-runner.show')
            ->with(self::SessionKey, $result->payload);
    }

    private function ensureEnabled(): void
    {
        abort_unless(
            (bool) config('x-change.lifecycle.public_auto_generate.browser_enabled', ! app()->isProduction()),
            404,
        );
    }
}
