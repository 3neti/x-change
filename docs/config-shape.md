# Proposed Config Shape — x-change

## Purpose

This document defines the proposed configuration surface for voucher flow behavior in x-change.

It incorporates existing ideas already present in:

- `config/x-change.php`
- `config/lifecycle-scenarios.php`

The goal is to evolve the current config, not replace it abruptly.

---

## 1. Existing Config Ideas Worth Preserving

The current config already has strong building blocks:

### Product Defaults

```php
'product' => [
    'name' => 'X-Change',
    'code' => 'x-change',
    'default_currency' => 'PHP',
    'default_country' => 'PH',
],
```

### Terminology

Current terminology config already supports product language customization.

Recommended refinement:

```php
'terminology' => [
    'voucher' => 'Voucher',
    'pay_code' => 'Pay Code',
    'redeem' => 'Claim',
    'withdraw' => 'Withdraw',
    'wallet' => 'Wallet',
    'account' => 'Account',
],
```

Important distinction:

- Voucher = internal governed instrument
- Pay Code = external presentation/reference

---

## 2. voucher_flow_types

Defines canonical voucher flow types and legacy aliases.

```php
'voucher_flow_types' => [
    'default' => 'disbursable',

    'canonical' => [
        'disbursable' => [
            'label' => 'Cash Out Voucher',
            'direction' => 'outbound',
            'can_disburse' => true,
            'can_collect' => false,
            'can_settle' => false,
            'supports_open_slices' => true,
            'supports_delegated_spend' => true,
            'requires_envelope' => false,
        ],

        'collectible' => [
            'label' => 'Pay In Voucher',
            'direction' => 'inbound',
            'can_disburse' => false,
            'can_collect' => true,
            'can_settle' => false,
            'supports_open_slices' => false,
            'supports_delegated_spend' => false,
            'requires_envelope' => false,
        ],

        'settlement' => [
            'label' => 'Settlement Voucher',
            'direction' => 'bidirectional',
            'can_disburse' => true,
            'can_collect' => true,
            'can_settle' => true,
            'supports_open_slices' => true,
            'supports_delegated_spend' => true,
            'requires_envelope' => true,
        ],
    ],

    'aliases' => [
        'redeemable' => 'disbursable',
        'payable' => 'collectible',
    ],
],
```

---

## 3. claim_policy

This evolves the current `withdrawal` config.

Current config already has:

```php
'withdrawal' => [
    'open_slice_min_interval_seconds' => 10,
    'pipeline' => [...],
    'otp' => [...],
],
```

Recommended future shape:

```php
'claim_policy' => [
    'default_claim_type' => 'withdraw',

    'ownership_claim' => [
        'enabled' => true,
        'allow_zero_disbursement' => true,
        'bind_mobile' => true,
        'bind_identity' => false,
    ],

    'open_slice' => [
        'enabled' => true,
        'min_interval_seconds' => 10,
        'default_min_withdrawal' => 25,
        'default_max_slices' => null,
        'same_destination_interval_enforced' => true,
    ],

    'delegated_spend' => [
        'enabled' => true,
        'requires_owner_binding' => true,
        'requestor_mobile_required' => true,
        'destination_required' => true,
    ],

    'pipeline' => [
        'withdrawal' => [
            ResolveWithdrawalClaimantStep::class,
            AssertWithdrawalEligibilityStep::class,
            AuthorizeWithdrawalClaimantStep::class,
            ResolveWithdrawalAmountStep::class,
            AuthorizeWithdrawalOtpStep::class,
            AuthorizeWithdrawalPolicyStep::class,
            ResolveWithdrawalBankAccountStep::class,
            BuildWithdrawalPayoutRequestStep::class,
            GuardWithdrawalRailStep::class,
            ExecuteWithdrawalDisbursementStep::class,
            WithdrawalWalletSettlementStep::class,
            BuildWithdrawalResultStep::class,
        ],
    ],
],
```

Migration note:

`withdrawal.open_slice_min_interval_seconds` can remain as a backward-compatible alias for:

```php
claim_policy.open_slice.min_interval_seconds
```

---

## 4. authorization_policy

This expands the current withdrawal OTP settings.

Current config:

```php
'withdrawal' => [
    'otp' => [
        'driver' => 'null',
        'required' => false,
        'label' => 'x-change',
    ],
],
```

Recommended shape:

```php
'authorization_policy' => [
    'default_mode' => 'owner_otp',

    'otp' => [
        'driver' => env('XCHANGE_AUTH_OTP_DRIVER', 'null'),
        'required' => env('XCHANGE_AUTH_OTP_REQUIRED', false),
        'label' => env('OTP_LABEL', config('app.name', 'x-change')),
        'ttl_seconds' => 300,
        'single_use' => true,
        'bind_to_claim_request' => true,
    ],

    'low_value' => [
        'enabled' => true,
        'max_per_transaction' => 50,
        'daily_limit' => 500,
        'otp_required' => false,
    ],

    'trusted_vendors' => [
        'enabled' => true,
        'otp_required' => false,
        'max_per_transaction' => 100,
        'daily_limit' => 1000,
    ],

    'channels' => [
        'sms' => [
            'enabled' => true,
            'driver' => 'txtcmdr',
        ],

        'push' => [
            'enabled' => false,
        ],
    ],
],
```

The existing `withdrawal.otp.txtcmdr` block can be moved here later.

---

## 5. settlement_modes

Settlement should be configured by mode, not by creating many voucher types.

```php
'settlement_modes' => [
    'default' => 'disburse_then_collect',

    'modes' => [
        'disburse_then_collect' => [
            'label' => 'Disburse then Collect',
            'description' => 'Loan-style settlement: release funds first, collect repayment later.',
            'allows_initial_disbursement' => true,
            'allows_collection' => true,
            'requires_envelope_before_disbursement' => false,
            'requires_envelope_before_collection' => false,
            'close_when_collection_target_met' => true,
        ],

        'collect_only_with_evidence' => [
            'label' => 'Collect Only with Evidence',
            'description' => 'Insurance or claim-style settlement where payment depends on proof.',
            'allows_initial_disbursement' => false,
            'allows_collection' => true,
            'requires_envelope_before_disbursement' => false,
            'requires_envelope_before_collection' => true,
            'close_when_collection_target_met' => true,
        ],

        'collect_then_release' => [
            'label' => 'Collect then Release',
            'description' => 'Escrow-style flow where funds are collected first and released later.',
            'allows_initial_disbursement' => false,
            'allows_collection' => true,
            'allows_release' => true,
            'requires_envelope_before_release' => true,
            'close_when_released' => true,
        ],

        'bilateral_closeout' => [
            'label' => 'Bilateral Closeout',
            'description' => 'Supports both inward and outward movements until settlement conditions are met.',
            'allows_initial_disbursement' => true,
            'allows_collection' => true,
            'allows_release' => true,
            'requires_envelope' => true,
            'close_when_net_settled' => true,
        ],
    ],
],
```

---

## 6. lifecycle_scenarios

The current lifecycle scenario structure is very useful and should remain.

It already models:

- amount
- currency
- cash behavior
- input fields
- feedback
- rider
- claims
- expected outcomes

Recommended convention:

```php
'scenarios' => [
    'divisible_open_three_slices_enforced_interval' => [
        'label' => 'Divisible Open Three Slices (Enforced Interval)',

        'flow_type' => 'disbursable',

        'amount' => 150,
        'currency' => 'PHP',

        'cash' => [
            'amount' => 150,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'slice_mode' => 'open',
            'max_slices' => 3,
            'min_withdrawal' => 25,
        ],

        'pay_code' => [
            'presentation' => 'url',
            'entry_path' => 'disburse',
        ],

        'claims' => [
            'claim_1_withdraw' => [
                'claim' => [
                    'amount' => 75,
                ],
                'expect' => [
                    'status' => 'succeeded',
                    'claim_type' => 'withdraw',
                ],
            ],

            'claim_2_withdraw' => [
                'claim' => [
                    'amount' => 50,
                ],
                'expect' => [
                    'status' => 'succeeded',
                    'claim_type' => 'withdraw',
                ],
            ],

            'claim_3_withdraw' => [
                'claim' => [
                    'amount' => 25,
                ],
                'expect' => [
                    'status' => 'succeeded',
                    'claim_type' => 'withdraw',
                ],
            ],
        ],
    ],
],
```

Important lifecycle insight:

`wait_before_seconds` should be optional in scenarios.

The command runner should auto-wait based on:

```php
claim_policy.open_slice.min_interval_seconds
```

That keeps tests and scenarios declarative.

---

## 7. Pay Code Presentation Config

Since Pay Code is the external presentation of the voucher, it deserves its own config surface.

```php
'pay_code' => [
    'default_presentation' => 'url',

    'presentations' => [
        'code' => [
            'enabled' => true,
        ],

        'qr' => [
            'enabled' => true,
            'backend' => 'voucher_url',
        ],

        'sms' => [
            'enabled' => true,
        ],

        'url' => [
            'enabled' => true,
        ],
    ],

    'routes' => [
        'disbursable' => 'disburse',
        'collectible' => 'pay',
        'settlement' => 'settle',
    ],
],
```

This builds on current route paths:

```php
'routes.paths.redeem'
'routes.paths.withdraw'
'routes.paths.pay'
```

---

## 8. Rider and Rich Experience Config

The lifecycle scenarios already support rider fields:

```php
'rider' => [
    'message' => null,
    'url' => null,
    'redirect_timeout' => null,
    'splash' => null,
    'splash_timeout' => null,
    'og_source' => null,
],
```

Recommended formal shape:

```php
'experience' => [
    'rider' => [
        'enabled' => true,

        'message' => [
            'enabled' => true,
        ],

        'splash' => [
            'enabled' => true,
            'default_timeout' => 3,
        ],

        'redirect' => [
            'enabled' => true,
            'default_timeout' => 3,
        ],
    ],
],
```

This keeps Pay Code differentiated from plain bank transfer.

---

## 9. Suggested Config Organization

Recommended final top-level config sections:

```php
return [
    'product' => [],
    'terminology' => [],
    'routes' => [],
    'api' => [],

    'voucher_flow_types' => [],
    'pay_code' => [],
    'claim_policy' => [],
    'authorization_policy' => [],
    'settlement_modes' => [],
    'experience' => [],

    'services' => [],
    'service_contracts' => [],
    'integrations' => [],
    'integration_contracts' => [],

    'pricing' => [],
    'revenue' => [],

    'lifecycle' => [],
];
```

---

## 10. Implementation Guidance

Do not migrate everything at once.

Recommended path:

1. Add new config sections while keeping old keys.
2. Add resolver services that read new keys first, old keys second.
3. Update tests to target resolver behavior.
4. Deprecate old keys in docs.
5. Remove old keys only after downstream apps migrate.

Example:

```php
$minInterval = config(
    'x-change.claim_policy.open_slice.min_interval_seconds',
    config('x-change.withdrawal.open_slice_min_interval_seconds', 0)
);
```

---

## Bottom Line

The current config already contains the seeds of the new architecture:

- `terminology` → supports Pay Code language
- `routes.paths` → supports presentation routing
- `withdrawal.pipeline` → supports claim execution policy
- `withdrawal.otp` → becomes authorization policy
- `lifecycle.scenarios.claims` → validates real voucher behavior
- `cash.slice_mode`, `max_slices`, `min_withdrawal` → encode open-slice behavior

The proposed config shape simply organizes these into clearer architectural layers:

```text
Voucher Flow → Pay Code Presentation → Claim Policy → Authorization Policy → Settlement Mode → Experience
```
