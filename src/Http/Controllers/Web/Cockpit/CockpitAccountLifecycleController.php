<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Accounts\AccountLifecycle;

final class CockpitAccountLifecycleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('x-change.view-account-lifecycle');
        $data = $request->validate(['account_id' => ['nullable', 'string', 'max:100']]);
        $account = null;
        if (! empty($data['account_id'])) {
            $owner = $this->owner($data['account_id']);
            Gate::authorize('x-change.manage-account-lifecycle', $owner);
            $lifecycle = DB::table('x_change_account_lifecycles')->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey())->first();
            $account = [
                'id' => (string) $owner->getKey(),
                'name' => (string) $owner->getAttribute('name'),
                'status' => $lifecycle?->status ?? 'active',
                'events' => $lifecycle === null ? [] : DB::table('x_change_account_lifecycle_events')
                    ->where('lifecycle_id', $lifecycle->id)->orderByDesc('id')->limit(50)
                    ->get(['previous_status', 'new_status', 'actor_type', 'actor_id', 'reason', 'created_at'])->toArray(),
            ];
        }

        return Inertia::render('x-change/cockpit/AccountLifecycle', ['account' => $account]);
    }

    public function update(Request $request, string $accountId, AccountLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('x-change.view-account-lifecycle');
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        $lifecycle->transition($this->owner($accountId), $actor, $data['status'], $data['reason']);

        return redirect()->route('x-change.cockpit.account-lifecycle.index', ['account_id' => $accountId]);
    }

    private function owner(string $id): Model
    {
        $model = config('x-change.onboarding.issuer_model', config('auth.providers.users.model'));
        abort_unless(is_string($model) && is_subclass_of($model, Model::class), 500);

        return $model::query()->findOrFail($id);
    }
}
