# 002 — Cockpit Porting Map

## Preserve

| Capability | Reason |
|---|---|
| Claim Journey | Mature, frontend-tested redeemer experience. |
| Paynamics OTP approval UX | Issuer-side authorization UX must remain separate from redeemer flow. |
| Rider message UX | Existing continuation experience is mature and tested. |
| Success redirect UX | Existing redirect ownership/countdown behavior is tested. |
| Compiled claim/form-flow rendering | Protected claim compiler output rendering. |
| Existing package test environments | x-change, voucher, x-journal, x-action, and x-feedback each have independent test roots. |

## Promote

| Capability | Target |
|---|---|
| Current x-change dashboard shell | Cockpit dashboard foundation after namespace/shell exists. |
| `StatCard`, `QuickActions`, `RecentActivity` | Cockpit dashboard widget primitives where they fit the workstation model. |
| Existing route composable patterns | Cockpit route/navigation helpers. |
| x-journal Cockpit read models | Operator audit/timeline surfaces after host redaction. |
| x-action host composition | Operator CTA panels after host authorization/redaction. |
| x-feedback UI component view models | Notification/delivery widgets under Cockpit page ownership. |

## Enhance

| Capability | Enhancement needed |
|---|---|
| `XChangeLayout.vue` | Convert from starter-kit style to bank-grade Cockpit shell with global header, balance HUD, navigation groups, and context areas. |
| Current dashboard | Shift from generic stats to liquidity, redemption pipeline, funding, risk, and recent activity. |
| Pay Code pages | Add operator exploration/search framing without changing existing issuance/claim semantics. |
| Settlement/evidence displays | Add forensic operator presentation without changing settlement readiness policy. |
| Reconciliation visibility | Add read-only status/exception views before any correction workflows. |

## Create

| Capability | First allowed slice |
|---|---|
| Cockpit namespace | Slice 1 |
| Cockpit layout/shell | Slice 1 |
| Global header | Slice 1 |
| Sidebar navigation model | Slice 1 |
| Balance HUD placeholder | Slice 1 |
| Cockpit dashboard placeholder | Slice 1 |
| Cockpit frontend test folder | Slice 1 |

## Defer

| Capability | Reason |
|---|---|
| Real execution monitor wiring | Requires host read-model/redaction design. |
| Real journal timeline pages | Requires Cockpit authorization/redaction and pagination decisions. |
| Real action execution connectors | x-action intentionally deferred connectors/execution. |
| Real feedback provider delivery controls | x-feedback delivery controls must remain outside UI until host policies are defined. |
| AI Copilot | Search/recommend/prepare only; no autonomous money movement. |
| Campaign/program dashboards | x-campaign has not started. |
| PWA/native mobile implementation | Desktop Cockpit shell should stabilize first. |
| Marketing analytics | Explicitly out of Cockpit scope. |

