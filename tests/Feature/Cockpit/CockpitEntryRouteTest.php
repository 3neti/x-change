<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\CockpitEntryDestination;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitEntryPageController;
use LBHurtado\XChange\Services\Cockpit\CockpitEntryDestinationResolver;

function configureCockpitEntrySystemPrincipal(): void
{
    $system = provisionTestSystemPrincipalForCommissioning();

    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => $system::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);
}

it('requires authentication at the smart Cockpit entry', function (): void {
    $this->getJson(route('x-change.cockpit.entry'))->assertUnauthorized();
});

it('redirects the smart Cockpit entry without changing explicit page destinations', function (
    CockpitEntryDestination $destination,
): void {
    configureCockpitEntrySystemPrincipal();
    $operator = actingAsTestUser();
    $resolver = Mockery::mock(CockpitEntryDestinationResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($operator)->andReturn($destination);
    app()->instance(CockpitEntryDestinationResolver::class, $resolver);

    $this->get(route('x-change.cockpit.entry'))
        ->assertRedirect(route($destination->routeName()))
        ->assertSessionHas(
            CockpitEntryPageController::NOTICE_SESSION_KEY,
            $destination->notice(),
        );

    $explicitRoute = $destination === CockpitEntryDestination::Funding
        ? 'x-change.cockpit.quick-generate'
        : 'x-change.cockpit.funding.index';

    $this->withHeader('X-Inertia', 'true')
        ->get(route($explicitRoute))
        ->assertOk();
})->with([
    'Funding' => CockpitEntryDestination::Funding,
    'Issuance' => CockpitEntryDestination::Issuance,
]);

it('shares the one-time entry explanation with the destination page', function (): void {
    configureCockpitEntrySystemPrincipal();
    actingAsTestUser();
    $notice = CockpitEntryDestination::Funding->notice();

    $this->withSession([
        CockpitEntryPageController::NOTICE_SESSION_KEY => $notice,
    ])->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.cockpit_entry_notice', $notice);
});
