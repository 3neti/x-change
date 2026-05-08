# x-change Lifecycle Scenario Builder Guide

## Purpose

This guide explains how to author x-change lifecycle scenarios in JSON.

A lifecycle scenario is an executable operational specification. It tells the Lifecycle Scenario Engine:

1. what voucher or transaction to issue,
2. what claims, payments, settlement steps, or attestations to perform,
3. what outcomes are expected,
4. what operational timing rules must be observed.

Although the package currently stores scenarios in PHP config, the same scenario shape can be represented in JSON for handoff, documentation, partner authoring, sandbox tools, and future scenario upload APIs.

---

# 1. Top-Level Scenario Shape

A scenario is a JSON object keyed by a scenario name.

```json
{
  "scenario_key": {
    "label": "Human readable scenario name",
    "amount": 25,
    "currency": "PHP",
    "metadata": {},
    "cash": {},
    "inputs": {},
    "feedback": {},
    "claim": {},
    "attempts": {},
    "claims": {},
    "expect": {}
  }
}
```

Example:

```json
{
  "basic_cash": {
    "label": "Basic Cash",
    "amount": 12.5,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "claim": {},
    "expect": {
      "tariffs": ["cash"]
    }
  }
}
```

---

# 2. Common Fields

## `label`

Human-readable name.

```json
{
  "label": "Secret Required"
}
```

---

## `amount`

Voucher amount in major currency units.

```json
{
  "amount": 25
}
```

For PHP/Peso, this means ₱25.00.

---

## `currency`

Currency code.

```json
{
  "currency": "PHP"
}
```

---

## `metadata`

Operational metadata attached to the voucher.

```json
{
  "metadata": {
    "flow_type": "disbursable"
  }
}
```

Common `flow_type` values:

```json
"disbursable"
"collectible"
"settlement"
```

---

## `meta`

Scenario classification metadata. This is different from voucher `metadata`.

```json
{
  "meta": {
    "family": "contract",
    "tags": ["secret"]
  }
}
```

Use `meta` for organizing scenarios.

Use `metadata` for voucher/instruction behavior.

---

# 3. Cash Instruction

The `cash` object describes money movement behavior.

## Minimal cash

```json
{
  "cash": {}
}
```

---

## Cash with validation

```json
{
  "cash": {
    "validation": {
      "secret": "ABC123",
      "mobile": "639171234567",
      "country": "PH"
    }
  }
}
```

---

## Disbursable cash

```json
{
  "metadata": {
    "flow_type": "disbursable"
  },
  "cash": {
    "amount": 150,
    "currency": "PHP",
    "validation": {
      "country": "PH"
    },
    "settlement_rail": "INSTAPAY",
    "fee_strategy": "absorb",
    "slice_mode": "open",
    "max_slices": 3,
    "min_withdrawal": 25
  }
}
```

---

## Divisible open cash

```json
{
  "cash": {
    "divisible": true,
    "withdrawable": true,
    "slice_mode": "open"
  }
}
```

---

## Divisible fixed cash

```json
{
  "cash": {
    "divisible": true,
    "withdrawable": true,
    "slice_mode": "fixed",
    "max_slices": 3
  }
}
```

---

# 4. Inputs

Inputs define data the claimant must submit.

```json
{
  "inputs": {
    "fields": ["name", "email", "birth_date"]
  }
}
```

Supported input examples:

```json
"name"
"email"
"address"
"birth_date"
"otp"
"signature"
"location"
"selfie"
"kyc"
```

---

# 5. Claim Payload

The `claim` object defines the default claim payload.

```json
{
  "claim": {
    "inputs": {
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "birth_date": "1990-01-01"
    }
  }
}
```

For disbursement:

```json
{
  "claim": {
    "amount": 100
  }
}
```

For secret-protected claim:

```json
{
  "claim": {
    "secret": "ABC123"
  }
}
```

---

# 6. Feedback

Feedback defines notification or callback channels.

```json
{
  "feedback": {
    "webhook": "https://example.test/webhook",
    "email": "example@example.com",
    "mobile": "09171234567"
  }
}
```

Use `null` when intentionally disabled:

```json
{
  "feedback": {
    "email": null,
    "mobile": null,
    "webhook": null
  }
}
```

---

# 7. Rider

Rider defines UX content shown before or after redemption.

```json
{
  "rider": {
    "message": null,
    "url": null,
    "redirect_timeout": null,
    "splash": null,
    "splash_timeout": null,
    "og_source": null
  }
}
```

Example redirect:

```json
{
  "rider": {
    "url": "https://example.com/thank-you",
    "redirect_timeout": 3
  }
}
```

---

# 8. Expectations

Expectations define what the engine should consider correct.

## Basic expectation

```json
{
  "expect": {
    "status": "succeeded"
  }
}
```

## Expected failure

```json
{
  "expect": {
    "status": "failed"
  }
}
```

## Expected message fragments

```json
{
  "expect": {
    "status": "failed",
    "message_contains": ["secret"]
  }
}
```

## Tariff expectation

```json
{
  "expect": {
    "tariffs": ["cash", "otp", "signature"]
  }
}
```

---

# 9. Single-Attempt Scenario

A simple scenario has one default claim.

```json
{
  "basic_cash": {
    "label": "Basic Cash",
    "amount": 12.5,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "claim": {},
    "expect": {
      "tariffs": ["cash"]
    }
  }
}
```

Use this for:

- simple issuance
- simple redemption
- smoke checks
- no-claim checks

---

# 10. Multi-Attempt Scenario

Use `attempts` when one voucher is tested against several possible claim attempts.

Example: wrong secret fails, correct secret succeeds.

```json
{
  "secret_required": {
    "label": "Secret Required",
    "amount": 25,
    "currency": "PHP",
    "cash": {
      "validation": {
        "secret": "ABC123"
      }
    },
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["secret"]
    },
    "attempts": {
      "wrong_secret_fails": {
        "claim": {
          "secret": "WRONG-SECRET"
        },
        "expect": {
          "status": "failed",
          "message_contains": ["secret"]
        }
      },
      "correct_secret_succeeds": {
        "claim": {
          "secret": "ABC123"
        },
        "expect": {
          "status": "succeeded"
        }
      }
    },
    "expect": {
      "tariffs": ["cash"]
    }
  }
}
```

Use `attempts` for:

- validation contracts
- expected failures
- approval rules
- presence checks
- semantic checks

---

# 11. Sequential Claims Scenario

Use `claims` when multiple real claims are executed in sequence.

This is different from `attempts`.

| Field | Meaning |
|---|---|
| `attempts` | multiple possible test attempts against a scenario |
| `claims` | actual sequential claims that should execute one after another |

Example:

```json
{
  "divisible_open_three_slices_enforced_interval": {
    "label": "Divisible Open Three Slices (Enforced Interval)",
    "metadata": {
      "flow_type": "disbursable"
    },
    "amount": 150,
    "currency": "PHP",
    "cash": {
      "amount": 150,
      "currency": "PHP",
      "validation": {
        "country": "PH"
      },
      "settlement_rail": "INSTAPAY",
      "fee_strategy": "absorb",
      "slice_mode": "open",
      "max_slices": 3,
      "min_withdrawal": 25
    },
    "bank_code": "GXCHPHM2XXX",
    "account_number": "09173011987",
    "mobile": "639171234567",
    "claims": {
      "claim_1_withdraw": {
        "wait_before_seconds": 0,
        "claim": {
          "amount": 75
        },
        "expect": {
          "status": "succeeded",
          "claim_type": "withdraw"
        }
      },
      "claim_2_withdraw": {
        "wait_before_seconds": 10,
        "claim": {
          "amount": 50
        },
        "expect": {
          "status": "succeeded",
          "claim_type": "withdraw"
        }
      },
      "claim_3_withdraw": {
        "wait_before_seconds": 10,
        "claim": {
          "amount": 25
        },
        "expect": {
          "status": "succeeded",
          "claim_type": "withdraw"
        }
      }
    }
  }
}
```

Use sequential claims for:

- divisible withdrawals
- open-slice withdrawals
- timing-sensitive providers
- provider pacing
- staged disbursement validation

---

# 12. Sequential Timing

Each sequential claim may define:

```json
{
  "wait_before_seconds": 10
}
```

Supported aliases:

```json
"wait_before_seconds"
"wait": {
  "before_seconds": 10
}
"delay_before_seconds"
"pause_before_seconds"
```

Recommended canonical form:

```json
"wait_before_seconds"
```

Example:

```json
{
  "claim_2_withdraw": {
    "wait_before_seconds": 10,
    "claim": {
      "amount": 50
    }
  }
}
```

Why this matters:

- some providers need time between disbursement calls
- avoids rate limiting
- avoids duplicate/failed provider references
- supports operational realism

---

# 13. Scenario-Level Sequential Default

You may set a default wait for all claims after the first.

```json
{
  "sequential": {
    "wait_between_claims_seconds": 10
  }
}
```

Then claim 2, claim 3, etc. inherit this value unless they define their own explicit `wait_before_seconds`.

Example:

```json
{
  "sequential": {
    "wait_between_claims_seconds": 10
  },
  "claims": {
    "claim_1_withdraw": {
      "claim": {
        "amount": 75
      }
    },
    "claim_2_withdraw": {
      "claim": {
        "amount": 50
      }
    }
  }
}
```

Recommended for simple uniform pacing.

Use per-claim waits when the sequence needs explicit documentation.

---

# 14. Collectible Scenario

A collectible scenario represents a voucher meant to receive payment, not execute outward withdrawal claims.

```json
{
  "collectible_basic_payment": {
    "label": "Collectible Basic Payment QR",
    "metadata": {
      "flow_type": "collectible"
    },
    "amount": 100,
    "currency": "PHP",
    "issuer": {
      "email": "system@example.test",
      "mobile": "639178251991",
      "wallet_balance": 1000000
    },
    "cash": {
      "settlement_rail": "INSTAPAY",
      "validation": {
        "secret": null,
        "mobile": null,
        "payable": null,
        "country": "PH",
        "location": null,
        "radius": null
      }
    },
    "inputs": {
      "fields": []
    },
    "feedback": {
      "email": "example@example.com",
      "mobile": "09171234567",
      "webhook": "https://example.com/webhook"
    },
    "rider": {
      "message": null,
      "url": null,
      "redirect_timeout": null,
      "splash": null,
      "splash_timeout": null,
      "og_source": null
    },
    "count": 1,
    "prefix": "PAY",
    "mask": "****",
    "ttl": null,
    "claims": [
      {
        "name": "default",
        "claim_mobile": "639171234567",
        "claim_payload": {
          "mobile": "639171234567",
          "recipient_country": "PH",
          "bank_account": {
            "bank_code": "GXCHPHM2XXX",
            "account_number": "09173011987"
          },
          "inputs": []
        },
        "expect": {
          "status": "failed",
          "message_contains": [
            "cannot execute outward claims"
          ]
        }
      }
    ]
  }
}
```

Important:

A collectible voucher should generally not allow outward disbursement claims.

---

# 15. Settlement Envelope Evaluation Scenario

Use `mode: settlement_envelope_evaluation` to evaluate settlement readiness.

```json
{
  "settlement_philhealth_bst": {
    "label": "Settlement — PhilHealth BST",
    "flow_type": "settlement",
    "mode": "settlement_envelope_evaluation",
    "metadata": {
      "flow_type": "settlement",
      "settlement_driver": "philhealth-bst"
    },
    "attempts": [
      {
        "name": "blocked_missing_amount_verification",
        "settlement": {
          "payload": {
            "patient_name": "Juan Dela Cruz",
            "patient_mobile": "09171234567"
          },
          "checklist": {
            "amount_verified": false
          }
        },
        "expect": {
          "status": "blocked",
          "missing": ["amount_verified"]
        }
      },
      {
        "name": "ready_after_amount_verification",
        "settlement": {
          "payload": {
            "patient_name": "Juan Dela Cruz",
            "patient_mobile": "09171234567"
          },
          "checklist": {
            "amount_verified": true
          }
        },
        "expect": {
          "status": "ready",
          "satisfied": ["payload_present", "amount_verified"]
        }
      }
    ]
  }
}
```

Use this for:

- settlement readiness
- checklist evaluation
- evidence completeness
- pre-settlement gating

---

# 16. Three-Party Settlement Scenario

Use `mode: settlement_three_party_flow` for multi-party settlement.

```json
{
  "settlement_philhealth_bst_three_party": {
    "label": "Settlement — PhilHealth BST Three-Party Flow",
    "mode": "settlement_three_party_flow",
    "amount": 20000,
    "currency": "PHP",
    "claim_mobile": "639171234567",
    "metadata": {
      "flow_type": "settlement",
      "settlement_driver": "philhealth-bst",
      "settlement_role_model": "three_party",
      "settlement_issuer_role": "hospital",
      "settlement_attestor_role": "patient",
      "settlement_payer_role": "philhealth",
      "settlement_recipient_role": "hospital"
    },
    "hospital": {
      "name": "Demo General Hospital",
      "gross_bill": 30000,
      "patient_payable": 10000,
      "philhealth_cover": 20000
    },
    "patient": {
      "name": "Juan Dela Cruz",
      "mobile": "09171234567",
      "birth_date": "1985-01-15"
    },
    "payer": {
      "name": "PhilHealth",
      "provider": "manual",
      "provider_reference": "PHILHEALTH-BST-CLAIM-001"
    },
    "phases": {
      "issue": {
        "expect": {
          "issuer_role": "hospital",
          "amount": 20000
        }
      },
      "attest": {
        "payload": {
          "mobile": "639171234567",
          "inputs": {
            "name": "Juan Dela Cruz",
            "birth_date": "1985-01-15",
            "signature": "demo-signature"
          },
          "settlement_attestation": true
        },
        "expect": {
          "role": "patient",
          "claim_type": "redeem",
          "disbursement": false
        }
      },
      "evaluate_before_completion": {
        "settlement": {
          "payload": {
            "patient_name": "Juan Dela Cruz",
            "patient_mobile": "09171234567",
            "diagnosis": "Demo diagnosis",
            "procedure": "Demo procedure",
            "gross_bill": 30000,
            "patient_payable": 10000,
            "philhealth_cover": 20000
          },
          "checklist": {
            "amount_verified": false
          }
        },
        "expect": {
          "ready": false,
          "missing": ["amount_verified"]
        }
      },
      "complete_envelope": {
        "settlement": {
          "payload": {
            "patient_name": "Juan Dela Cruz",
            "patient_mobile": "09171234567",
            "diagnosis": "Demo diagnosis",
            "procedure": "Demo procedure",
            "gross_bill": 30000,
            "patient_payable": 10000,
            "philhealth_cover": 20000
          },
          "documents": {
            "loa": "demo://documents/loa.pdf",
            "mdr": "demo://documents/mdr.pdf"
          },
          "checklist": {
            "amount_verified": true
          }
        },
        "expect": {
          "ready": true,
          "satisfied": ["payload_present", "amount_verified"]
        }
      },
      "settle": {
        "payment": {
          "provider": "manual",
          "provider_reference": "PHILHEALTH-BST-CLAIM-001",
          "amount": 20000,
          "currency": "PHP",
          "status": "succeeded"
        },
        "expect": {
          "payer_role": "philhealth",
          "recipient_role": "hospital",
          "status": "collected"
        }
      }
    }
  }
}
```

Use this for:

- institutional settlement flows
- patient attestation
- payer settlement
- hospital/merchant settlement
- settlement envelope completion

---

# 17. Reconciliation Scenario

Reconciliation scenarios use metadata to trigger reconciliation behavior.

```json
{
  "reconciliation_review_required": {
    "label": "Reconciliation Review Required",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "meta": {
      "family": "reconciliation",
      "tags": ["reconciliation", "review", "provider"]
    },
    "metadata": {
      "lifecycle": {
        "reconciliation_mode": "review_required"
      }
    },
    "claim": {},
    "expect": {
      "status": "succeeded"
    }
  }
}
```

Known reconciliation modes from current scenarios:

```json
"review_required"
"provider_failed_recorded"
"resolve_success"
"resolve_failed"
```

---

# 18. Time Window Scenario

Use `starts_at` to block early claims.

```json
{
  "starts_future": {
    "label": "Starts Future",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "starts_at": "2026-04-20T01:00:00+08:00",
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["time", "starts_at"]
    },
    "attempts": {
      "before_start_fails": {
        "claim": {},
        "expect": {
          "status": "failed"
        }
      }
    },
    "expect": {
      "tariffs": ["cash"]
    }
  }
}
```

Use `expires_at` to block late claims.

```json
{
  "expired_voucher": {
    "label": "Expired Voucher",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "expires_at": "2026-04-19T21:00:00+08:00",
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["time", "expires_at"]
    },
    "attempts": {
      "after_expiry_fails": {
        "claim": {},
        "expect": {
          "status": "failed"
        }
      }
    },
    "expect": {
      "tariffs": ["cash"]
    }
  }
}
```

---

# 19. KYC Scenario

```json
{
  "kyc_required_approved": {
    "label": "KYC Required Approved",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": ["kyc"]
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["kyc", "presence", "contact"]
    },
    "claim": {
      "inputs": {
        "kyc": {
          "transaction_id": "MOCK-KYC-123",
          "status": "approved",
          "name": "Juan Dela Cruz",
          "id_number": "ABC123456",
          "id_type": "National ID"
        }
      }
    },
    "expect": {
      "status": "succeeded"
    }
  }
}
```

Unapproved variant:

```json
{
  "expect": {
    "status": "failed"
  }
}
```

---

# 20. Location Radius Scenario

```json
{
  "location_radius": {
    "label": "Location Radius",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": ["location"]
    },
    "validation": {
      "location": {
        "required": true,
        "target_lat": 14.5995,
        "target_lng": 120.9842,
        "radius_meters": 100,
        "on_failure": "block"
      }
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["location", "radius", "semantic"]
    },
    "attempts": {
      "outside_radius_fails": {
        "claim": {
          "inputs": {
            "location": {
              "lat": 14.6095,
              "lng": 120.9942
            }
          }
        },
        "expect": {
          "status": "failed"
        }
      },
      "inside_radius_succeeds": {
        "claim": {
          "inputs": {
            "location": {
              "lat": 14.5995,
              "lng": 120.9842
            }
          }
        },
        "expect": {
          "status": "succeeded"
        }
      }
    },
    "expect": {
      "tariffs": ["cash", "location"]
    }
  }
}
```

---

# 21. OTP Scenario

```json
{
  "otp_required": {
    "label": "OTP Required",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": ["otp"]
    },
    "validation": {
      "otp": {
        "required": true,
        "on_failure": "block"
      }
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["otp", "presence", "semantic"]
    },
    "attempts": {
      "missing_otp_fails": {
        "claim": {
          "inputs": {}
        },
        "expect": {
          "status": "failed"
        }
      },
      "unverified_otp_fails": {
        "claim": {
          "inputs": {
            "otp": {
              "otp_code": "123456",
              "verified": false
            }
          }
        },
        "expect": {
          "status": "failed"
        }
      },
      "verified_otp_succeeds": {
        "claim": {
          "inputs": {
            "otp": {
              "otp_code": "123456",
              "verified": true,
              "verified_at": "2026-04-19T10:30:00+08:00"
            }
          }
        },
        "expect": {
          "status": "succeeded"
        }
      }
    },
    "expect": {
      "tariffs": ["cash", "otp"]
    }
  }
}
```

---

# 22. Scenario Authoring Checklist

Before adding a scenario, answer:

1. What business flow does this scenario prove?
2. Is it a single claim, multiple attempts, sequential claims, settlement, or reconciliation scenario?
3. Does it touch real external APIs?
4. Does it require timing delays?
5. Does it require real wallet funding?
6. What exact success or failure is expected?
7. What data should be visible in the final result?
8. Does the scenario belong to a group such as smoke, contract, settlement, provider, or regression?

---

# 23. Choosing the Right Scenario Shape

## Use `claim` when:

There is one default claim.

```json
{
  "claim": {}
}
```

---

## Use `attempts` when:

You want to test multiple possible outcomes against one scenario.

```json
{
  "attempts": {
    "wrong_secret_fails": {},
    "correct_secret_succeeds": {}
  }
}
```

---

## Use `claims` when:

You want multiple actual claims to execute sequentially.

```json
{
  "claims": {
    "claim_1_withdraw": {},
    "claim_2_withdraw": {},
    "claim_3_withdraw": {}
  }
}
```

---

## Use `phases` when:

You are modeling multi-party settlement.

```json
{
  "phases": {
    "issue": {},
    "attest": {},
    "evaluate_before_completion": {},
    "complete_envelope": {},
    "settle": {}
  }
}
```

---

# 24. Recommended Naming Convention

Use snake_case scenario keys.

Good:

```json
"secret_required"
"mobile_locked"
"divisible_open_three_slices_enforced_interval"
"settlement_philhealth_bst_three_party"
```

Avoid:

```json
"Secret Required"
"test1"
"scenarioA"
```

---

# 25. Recommended Claim Naming

For sequential claims:

```json
"claim_1_withdraw"
"claim_2_withdraw"
"claim_3_withdraw"
```

For attempts:

```json
"wrong_secret_fails"
"correct_secret_succeeds"
"missing_location_fails"
"inside_radius_succeeds"
```

Names should describe expected behavior.

---

# 26. Common Status Values

Observed scenario expectations use:

```json
"succeeded"
"failed"
"ready"
"blocked"
"collected"
```

Common usage:

| Status | Meaning |
|---|---|
| `succeeded` | Claim or operation should pass |
| `failed` | Claim or operation should fail |
| `ready` | Settlement envelope is ready |
| `blocked` | Settlement envelope is not ready |
| `collected` | Settlement payment was collected |

---

# 27. Recommended Scenario Metadata

For future governance, add:

```json
{
  "meta": {
    "family": "contract",
    "tags": ["secret", "validation"]
  }
}
```

Recommended families:

```json
"smoke"
"contract"
"provider"
"settlement"
"reconciliation"
"partner"
"regression"
```

Recommended tags:

```json
"cash"
"wallet"
"voucher"
"redemption"
"withdrawal"
"settlement"
"reconciliation"
"otp"
"kyc"
"location"
"signature"
"selfie"
"provider"
"instapay"
"netbank"
```

---

# 28. JSON Template: Basic Scenario

```json
{
  "my_basic_scenario": {
    "label": "My Basic Scenario",
    "amount": 25,
    "currency": "PHP",
    "cash": {},
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "claim": {},
    "expect": {
      "status": "succeeded",
      "tariffs": ["cash"]
    }
  }
}
```

---

# 29. JSON Template: Contract Scenario

```json
{
  "my_contract_scenario": {
    "label": "My Contract Scenario",
    "amount": 25,
    "currency": "PHP",
    "cash": {
      "validation": {
        "secret": "ABC123"
      }
    },
    "inputs": {
      "fields": []
    },
    "feedback": {},
    "meta": {
      "family": "contract",
      "tags": ["secret"]
    },
    "attempts": {
      "wrong_value_fails": {
        "claim": {
          "secret": "WRONG"
        },
        "expect": {
          "status": "failed",
          "message_contains": ["secret"]
        }
      },
      "correct_value_succeeds": {
        "claim": {
          "secret": "ABC123"
        },
        "expect": {
          "status": "succeeded"
        }
      }
    },
    "expect": {
      "tariffs": ["cash"]
    }
  }
}
```

---

# 30. JSON Template: Sequential Disbursement Scenario

```json
{
  "my_sequential_disbursement_scenario": {
    "label": "My Sequential Disbursement Scenario",
    "metadata": {
      "flow_type": "disbursable"
    },
    "amount": 150,
    "currency": "PHP",
    "cash": {
      "amount": 150,
      "currency": "PHP",
      "validation": {
        "country": "PH"
      },
      "settlement_rail": "INSTAPAY",
      "fee_strategy": "absorb",
      "slice_mode": "open",
      "max_slices": 3,
      "min_withdrawal": 25
    },
    "bank_code": "GXCHPHM2XXX",
    "account_number": "09173011987",
    "mobile": "639171234567",
    "claims": {
      "claim_1_withdraw": {
        "wait_before_seconds": 0,
        "claim": {
          "amount": 75
        },
        "expect": {
          "status": "succeeded",
          "claim_type": "withdraw"
        }
      },
      "claim_2_withdraw": {
        "wait_before_seconds": 10,
        "claim": {
          "amount": 50
        },
        "expect": {
          "status": "succeeded",
          "claim_type": "withdraw"
        }
      },
      "claim_3_withdraw": {
        "wait_before_seconds": 10,
        "claim": {
          "amount": 25
        },
        "expect": {
          "status": "succeeded",
          "claim_type": "withdraw"
        }
      }
    }
  }
}
```

---

# 31. JSON Template: Settlement Readiness Scenario

```json
{
  "my_settlement_readiness_scenario": {
    "label": "My Settlement Readiness Scenario",
    "flow_type": "settlement",
    "mode": "settlement_envelope_evaluation",
    "metadata": {
      "flow_type": "settlement",
      "settlement_driver": "my-driver"
    },
    "attempts": [
      {
        "name": "blocked_missing_required_item",
        "settlement": {
          "payload": {
            "customer_name": "Juan Dela Cruz"
          },
          "checklist": {
            "amount_verified": false
          }
        },
        "expect": {
          "status": "blocked",
          "missing": ["amount_verified"]
        }
      },
      {
        "name": "ready_after_completion",
        "settlement": {
          "payload": {
            "customer_name": "Juan Dela Cruz"
          },
          "checklist": {
            "amount_verified": true
          }
        },
        "expect": {
          "status": "ready",
          "satisfied": ["payload_present", "amount_verified"]
        }
      }
    ]
  }
}
```

---

# 32. Runtime Execution

Run from CLI:

```bash
php artisan xchange:lifecycle:run my_scenario_key --timeout=1 --poll=1 --accept-pending --json
```

Run only one attempt:

```bash
php artisan xchange:lifecycle:run secret_required --only-attempt=correct_secret_succeeds --json
```

Run without claiming:

```bash
php artisan xchange:lifecycle:run basic_cash --no-claim --json
```

Run through API:

```http
POST /api/x/v1/lifecycle/scenarios/run
Content-Type: application/json

{
  "scenario": "secret_required",
  "only_attempt": "correct_secret_succeeds",
  "timeout": 1,
  "poll": 1,
  "accept_pending": true
}
```

---

# 33. Validation Before Use

Before running a scenario against real APIs:

1. Confirm environment.
2. Confirm provider credentials.
3. Confirm wallet funding.
4. Confirm target account/mobile is safe.
5. Confirm amount is intentionally small.
6. Confirm timing settings.
7. Confirm expected provider behavior.
8. Confirm reconciliation monitoring is enabled.

---

# 34. Common Mistakes

## Mistake: Using `attempts` when you need sequential execution

Wrong:

```json
{
  "attempts": {
    "claim_1": {},
    "claim_2": {}
  }
}
```

Use `claims` instead.

---

## Mistake: Missing wait before provider-sensitive claims

Wrong:

```json
{
  "claims": {
    "claim_1_withdraw": {},
    "claim_2_withdraw": {}
  }
}
```

Better:

```json
{
  "claims": {
    "claim_1_withdraw": {
      "wait_before_seconds": 0
    },
    "claim_2_withdraw": {
      "wait_before_seconds": 10
    }
  }
}
```

---

## Mistake: Putting scenario classification under `metadata`

Wrong:

```json
{
  "metadata": {
    "family": "contract"
  }
}
```

Better:

```json
{
  "meta": {
    "family": "contract"
  }
}
```

Use `metadata` for voucher behavior. Use `meta` for scenario organization.

---

## Mistake: Forgetting `flow_type`

For disbursable vouchers:

```json
{
  "metadata": {
    "flow_type": "disbursable"
  }
}
```

For collectible vouchers:

```json
{
  "metadata": {
    "flow_type": "collectible"
  }
}
```

For settlement vouchers:

```json
{
  "metadata": {
    "flow_type": "settlement"
  }
}
```

---

# 35. Best Practices

1. Keep scenario amounts small.
2. Use explicit claim names.
3. Use `wait_before_seconds` for provider-sensitive flows.
4. Use `message_contains` for expected failure assertions.
5. Use `meta.family` and `meta.tags` for governance.
6. Separate contract scenarios from provider scenarios.
7. Keep settlement scenarios explicit and evidence-driven.
8. Never run real provider scenarios without confirming environment.
9. Prefer sandbox accounts for live API validation.
10. Commit scenario definitions with code changes.

---

# 36. Mental Model

A lifecycle scenario answers:

> Can x-change perform this business lifecycle correctly, right now, in this environment?

JSON scenarios are the portable representation of that question.

They are not merely test data.

They are operational proof scripts.
