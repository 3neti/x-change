<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Banking\ConfigureAssignedBankAccount;
use LBHurtado\XChange\Actions\Banking\SelectDefaultBankingAccount;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Models\BankAccountAssignment;

final class CockpitBankAccountController extends Controller
{
    public function store(Request $request, CockpitTreasuryAccessContract $access, AssignNetbankAccount $assign): RedirectResponse
    {
        abort_unless($access->canManageTreasuryReconciliation($request->user()), 403);
        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'account_number' => ['required', 'string', 'max:48'],
            'account_name' => ['required', 'string', 'max:191'],
            'customer_id' => ['required', 'string', 'max:32'],
            'vca_alias' => ['required', 'string', 'max:5'],
            'nickname' => ['nullable', 'string', 'max:191'],
        ]);
        $userModel = config('auth.providers.users.model');
        abort_unless(is_string($userModel) && is_subclass_of($userModel, Model::class), 500);
        $owner = $userModel::query()->findOrFail($data['owner_id'] ?? $request->user()->getAuthIdentifier());
        $assign->handle($owner, $data);

        return back()->with('funding_account_notice', 'Bank account added. Readiness checks are pending.');
    }

    public function update(Request $request, int $assignment, SelectDefaultBankingAccount $select, CockpitTreasuryAccessContract $access, ConfigureAssignedBankAccount $configure): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['nullable', 'in:rename,default,configure,configure_funding,configure_fresh_funding'],
            'sole_writer_confirmed' => ['accepted_if:action,configure_fresh_funding'],
            'known_vcas' => ['required_if:action,configure_fresh_funding', 'array'],
            'known_vcas.*' => ['required', 'string', 'regex:/\A[0-9]{12}\z/'],
            'treasury_connection' => ['required_if:action,configure', 'string', 'max:191'],
            'disbursement_confirmed' => ['accepted_if:action,configure'],
            'history_reviewed' => ['accepted_if:action,configure_funding'],
            'nickname' => ['nullable', 'string', 'max:191'],
        ]);
        if (($data['action'] ?? 'default') === 'configure') {
            abort_unless($access->canManageTreasuryReconciliation($request->user()) && $request->user() instanceof Model, 403);
            $record = BankAccountAssignment::query()->findOrFail($assignment);
            $configure->handle($record, $data['treasury_connection'], $request->user());

            return back()->with('funding_account_notice', 'Bank account configured for issuance.');
        }
        if (($data['action'] ?? 'default') === 'configure_fresh_funding') {
            abort_unless($access->canManageTreasuryReconciliation($request->user()) && $request->user() instanceof Model, 403);
            $record = BankAccountAssignment::query()->findOrFail($assignment);
            $configure->enableFreshFunding($record, $request->user(), $data['known_vcas'], true);

            return back()->with('funding_account_notice', 'Bank account configured for new funding requests.');
        }
        if (($data['action'] ?? 'default') === 'configure_funding') {
            abort_unless($access->canManageTreasuryReconciliation($request->user()) && $request->user() instanceof Model, 403);
            $record = BankAccountAssignment::query()->findOrFail($assignment);
            $configure->enableFunding($record, $request->user(), true);

            return back()->with('funding_account_notice', 'Bank account configured for funding.');
        }
        if (($data['action'] ?? 'default') === 'rename') {
            $record = BankAccountAssignment::query()->whereMorphedTo('owner', $request->user())->findOrFail($assignment);
            $details = $record->details_ciphertext;
            $details['nickname'] = $data['nickname'] ?? null;
            $record->forceFill(['details_ciphertext' => $details])->save();

            return back()->with('funding_account_notice', 'Account nickname updated.');
        }

        $select->handle($request->user(), $assignment);

        return back()->with('funding_account_notice', 'Default funding account updated.');
    }
}
