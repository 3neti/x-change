<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\PayCodeTemplate;

if (! app()->environment('local')) {
    throw new LogicException('The synthetic browser fixture is local-only.');
}

$model = config('auth.providers.users.model');
$owner = $model::query()->firstOrCreate(['email' => 'workflow-draft-browser@example.test'], [
    'name' => 'Workflow Draft Browser',
    'mobile' => '639170000088',
    'password' => Hash::make(Str::random(48)),
    'email_verified_at' => now(),
]);
$template = PayCodeTemplate::query()->firstOrCreate([
    'owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey(),
    'name' => 'Workflow browser settlement fixture',
], [
    'base_template_key' => 'blank-pay-code', 'include_amount' => true, 'include_purpose' => true, 'status' => 'active',
    'instructions_ciphertext' => [
        'voucher_type' => 'settlement', 'cash' => ['amount' => 0, 'currency' => 'PHP'],
        'target_amount' => 50, 'prefix' => 'POLI', 'mask' => '****',
        'inputs' => ['fields' => ['name', 'mobile', 'email', 'address', 'birth_date']],
        'rider' => ['message' => 'Local demonstration details'],
    ],
]);

return ['owner_id' => $owner->getKey(), 'identity' => $owner->getMorphClass().':'.$owner->getKey(), 'template_id' => $template->getKey()];
