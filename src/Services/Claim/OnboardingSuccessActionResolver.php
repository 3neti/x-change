<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Facades\Route;
use LBHurtado\XAction\Contracts\ActionTargetResolverContract;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionTargetData;
use LBHurtado\XAction\Exceptions\InvalidActionTargetException;
use LBHurtado\XAction\Exceptions\UnsupportedActionTargetException;

final class OnboardingSuccessActionResolver
{
    public function __construct(
        private readonly ActionTargetResolverContract $targets,
    ) {}

    /**
     * @param  array<string, mixed>  $successPresentation
     * @return array<string, mixed>|null
     */
    public function resolve(array $successPresentation): ?array
    {
        if (($successPresentation['primary_action_intent'] ?? null) !== 'enter_workspace') {
            return null;
        }

        $action = $this->actionForRole(
            is_string($successPresentation['primary_action_role'] ?? null)
                ? $successPresentation['primary_action_role']
                : null,
        );

        try {
            $target = $this->targets->resolve($action);
        } catch (InvalidActionTargetException|UnsupportedActionTargetException) {
            $target = null;
        }

        return [
            'key' => $action->key,
            'label' => $action->label,
            'intent' => $action->intent,
            'description' => $action->description,
            'enabled' => true,
            'target' => [
                'type' => $target?->type ?? 'url',
                'url' => $target?->url ?? '/x/cockpit',
                'method' => $target?->method ?? 'GET',
                'redirectable' => $target?->redirectable ?? true,
                'external' => $target?->external ?? false,
            ],
            'source' => 'x-action',
        ];
    }

    private function actionForRole(?string $role): ActionData
    {
        [$route, $label, $description] = match ($role) {
            'Maker' => [
                'x-change.cockpit.quick-generate',
                'Go to my workspace',
                'Open the issuance workspace for preparing Pay Codes.',
            ],
            'Checker' => [
                'x-change.cockpit.dashboard',
                'Go to my workspace',
                'Open the workspace overview for reviewing payout activity.',
            ],
            default => [
                'x-change.cockpit.entry',
                'Continue',
                'Open your account workspace.',
            ],
        };

        return new ActionData(
            key: 'x-change.onboarding-success.enter-workspace',
            label: $label,
            target: new ActionTargetData(
                type: Route::has($route) ? ActionTargetData::TypeRoute : ActionTargetData::TypeExternalUrl,
                route: Route::has($route) ? $route : null,
                url: Route::has($route) ? null : '/x/cockpit',
                method: 'GET',
            ),
            intent: 'enter_workspace',
            description: $description,
            style: 'primary',
            audience: $role === null ? 'account' : strtolower($role),
            surface: 'onboarding-success',
            channel: 'web',
            meta: [
                'presentation_only' => true,
            ],
        );
    }
}
