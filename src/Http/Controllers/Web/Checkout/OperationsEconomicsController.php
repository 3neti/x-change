<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Checkout;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\ReportRegistry\Services\ReportExecutor;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleAccess;
use LBHurtado\XChange\Services\Commercial\ConfiguredPublicIssuanceEscrowPrincipalResolver;
use LBHurtado\XChange\Services\Commercial\CommercialPricingWorkspace;

final class OperationsEconomicsController extends Controller
{
    /** @var array<string, string> */
    private const REPORTS = [
        'summary' => 'xchange-operations-summary',
        'waterfall' => 'xchange-operations-waterfall',
        'products' => 'xchange-product-revenue',
        'positions' => 'xchange-position-balances',
        'movements' => 'xchange-operations-movements',
        'exceptions' => 'xchange-operations-exceptions',
    ];

    public function show(
        Request $request,
        ConfiguredPublicIssuanceEscrowPrincipalResolver $principalResolver,
        CheckoutConsoleAccess $access,
        ReportExecutor $executor,
    ): Response {
        $principal = $principalResolver->resolve();
        abort_unless($access->owner($request, $principal), 404);

        return $this->page($request, $executor, 'Payment Operations Economics', 'operations', route('x-change.operations.economics', [], false));
    }

    public function cockpit(Request $request, ReportExecutor $executor, CommercialPricingWorkspace $workspace): Response
    {
        abort_unless($workspace->canView($request->user()), 404);

        return $this->page($request, $executor, 'Operations Economics', 'cockpit', route('x-change.cockpit.operations-economics', [], false));
    }

    public function report(
        Request $request,
        string $report,
        ConfiguredPublicIssuanceEscrowPrincipalResolver $principalResolver,
        CheckoutConsoleAccess $access,
        ReportExecutor $executor,
    ): \Illuminate\Http\JsonResponse {
        $principal = $principalResolver->resolve();
        abort_unless($access->owner($request, $principal), 404);

        $driver = $this->driverFor($report);
        abort_unless($driver !== null, 404);

        return response()->json($this->execute($executor, $driver, $this->filters($request)));
    }

    public function cockpitReport(
        Request $request,
        string $report,
        ReportExecutor $executor,
        CommercialPricingWorkspace $workspace,
    ): \Illuminate\Http\JsonResponse {
        abort_unless($workspace->canView($request->user()), 404);
        $driver = $this->driverFor($report);
        abort_unless($driver !== null, 404);

        return response()->json($this->execute($executor, $driver, $this->filters($request)));
    }

    private function page(Request $request, ReportExecutor $executor, string $title, string $surface, string $operationsUrl): Response
    {
        $filters = $this->filters($request);
        $reports = [];
        foreach (self::REPORTS as $key => $driver) {
            $reports[$key] = $this->execute($executor, $driver, $filters);
        }

        return Inertia::render('x-change/operations/Economics', [
            'title' => $title,
            'surface' => $surface,
            'operations_url' => $operationsUrl,
            'filters' => $filters,
            'reports' => $reports,
        ])->rootView('x-change::claim-root');
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $period = $request->string('period')->value();
        $filters = [
            'period' => in_array($period, ['seven_days', 'thirty_days', 'custom'], true) ? $period : 'today',
            'currency' => 'PHP',
        ];

        foreach (['from', 'to'] as $key) {
            $value = $request->string($key)->value();
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /** @param  array<string, string>  $filters */
    private function execute(ReportExecutor $executor, string $driver, array $filters): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $executor->execute($driver, format: 'raw', filters: $filters);

        return $payload;
    }

    private function driverFor(string $report): ?string
    {
        if (isset(self::REPORTS[$report])) {
            return self::REPORTS[$report];
        }

        return in_array($report, self::REPORTS, true) ? $report : null;
    }
}
