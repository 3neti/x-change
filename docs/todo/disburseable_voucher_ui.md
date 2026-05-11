# Implementation Plan — Evolve x-change Voucher UI Toward redeem-x Parity

## Goal

Upgrade the x-change voucher dashboard screens to approach redeem-x UX parity for the **disburseable Pay Code flow** only:

```text
Create.vue  → generate Pay Codes
Index.vue   → list, search, filter, inspect Pay Codes
Show.vue    → view Pay Code detail, claim link, QR/share, lifecycle/status
```

Out of scope for this slice:

```text
payable vouchers
settlement vouchers
settlement envelope
campaign management
vendor alias management
advanced host-app-only redeem-x assumptions
```

---

## Package Ownership

Patch the package source first:

```text
packages/x-change/resources/js/pages/x-change/pay-codes/Create.vue
packages/x-change/resources/js/pages/x-change/pay-codes/Index.vue
packages/x-change/resources/js/pages/x-change/pay-codes/Show.vue
```

Create shared package-owned components under:

```text
packages/x-change/resources/js/components/x-change/pay-codes/
```

After package source is updated, publish to host app:

```bash
php artisan vendor:publish --tag=x-change-ui --force
npm run build
```

or during development:

```bash
npm run dev
```

---

# Design Principle

Do not copy redeem-x wholesale.

Copy only the useful UX patterns:

```text
- card-based dashboard sections
- basic/advanced generation
- stats at top of listing
- status badges
- claim/share links
- QR/share panel
- instruction summary
- lifecycle/claim history
```

Do not import redeem-x-only assumptions:

```text
- campaigns
- vendor aliases
- settlement envelopes
- payable mode
- settlement mode
- host-only config
```

---

# Target Page Architecture

## Before

```text
Create.vue
Index.vue
Show.vue
```

contain most UI directly.

## After

Pages become thin orchestrators:

```text
Create.vue
  → PayCodeGenerationBasicForm
  → PayCodeGenerationAdvancedForm
  → PayCodeCostEstimateCard
  → PayCodeInstructionPreview

Index.vue
  → PayCodeStatsCards
  → PayCodeFilters
  → PayCodeListTable
  → PayCodeEmptyState

Show.vue
  → PayCodeStatusCard
  → PayCodeQrSharePanel
  → PayCodeInstructionSummary
  → PayCodeClaimHistory
  → PayCodeMetadataCard
```

---

# New Component Scaffold

Create directory:

```bash
mkdir -p packages/x-change/resources/js/components/x-change/pay-codes
```

Create these files:

```text
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeStatusBadge.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeStatsCards.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeFilters.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeListTable.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeQrSharePanel.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeInstructionSummary.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeClaimHistory.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeGenerationBasicForm.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeGenerationAdvancedForm.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeCostEstimateCard.vue
packages/x-change/resources/js/components/x-change/pay-codes/PayCodeInstructionPreview.vue
```

Optional later:

```text
PayCodeEmptyState.vue
PayCodeCopyButton.vue
PayCodeAmountBadge.vue
PayCodeLifecycleTimeline.vue
```

---

# Phase 1 — Shared UI Components

## 1. `PayCodeStatusBadge.vue`

Purpose:

```text
Render consistent status badges for active/redeemed/expired/pending/failed.
```

Suggested props:

```ts
interface Props {
    status?: string | null;
    redeemed_at?: string | null;
    expires_at?: string | null;
}
```

Status rules:

```text
redeemed_at present → redeemed
expires_at past → expired
otherwise → active
```

Use badge variants:

```text
active   → default / green-ish
redeemed → secondary
expired  → destructive/outline
pending  → outline
failed   → destructive
```

Used by:

```text
Index.vue
Show.vue
PayCodeListTable.vue
PayCodeStatusCard.vue
```

---

## 2. `PayCodeStatsCards.vue`

Purpose:

```text
Top-level dashboard cards for Index.vue.
```

Props:

```ts
interface Props {
    stats: {
        total?: number;
        active?: number;
        redeemed?: number;
        expired?: number;
        total_amount?: string | number;
        redeemed_amount?: string | number;
    };
}
```

Cards:

```text
Total Pay Codes
Active
Redeemed
Expired
```

If amount fields exist:

```text
Total Value
Redeemed Value
```

Do not require backend changes yet. Compute from loaded vouchers in the frontend if backend does not provide stats.

---

## 3. `PayCodeFilters.vue`

Purpose:

```text
Search and filter controls for Index.vue.
```

Props/events:

```ts
interface Props {
    search: string;
    status: string;
}

emit:
update:search
update:status
```

Controls:

```text
search input
status select: all, active, redeemed, expired
```

Future filters:

```text
date range
amount range
instruction type
```

---

## 4. `PayCodeListTable.vue`

Purpose:

```text
Display vouchers in an operational table/list.
```

Props:

```ts
interface Props {
    vouchers: any[];
}
```

Columns:

```text
Code
Amount
Status
Created
Redeemed
Actions
```

Actions:

```text
View
Copy Claim Link
Start Claim
```

For mobile, use stacked card layout if table becomes cramped.

---

## 5. `PayCodeQrSharePanel.vue`

Purpose:

```text
Show QR/share/copy claim URL in Show.vue.
```

Props:

```ts
interface Props {
    code: string;
    claim_url: string;
    qr_code?: string | null;
}
```

Actions:

```text
Copy claim URL
Open claim page
Download QR if available
```

Do not add new QR generation yet unless already present. If no QR exists, render claim URL copy/open only.

---

## 6. `PayCodeInstructionSummary.vue`

Purpose:

```text
Human-readable summary of voucher instructions.
```

Props:

```ts
interface Props {
    instructions: any;
}
```

Sections:

```text
Amount / settlement rail
Required inputs
Evidence requirements
Validation rules
Rider
Splash
Feedback
```

Use readable labels. Hide empty sections.

---

## 7. `PayCodeClaimHistory.vue`

Purpose:

```text
Show claims/redeem attempts if available from Show.vue data.
```

Props:

```ts
interface Props {
    claims?: any[];
}
```

Render:

```text
date
mobile/account masked if present
status
transaction ID
error/pending note
```

If no claims:

```text
No claim history yet.
```

---

# Phase 2 — Index.vue Upgrade

Patch:

```text
packages/x-change/resources/js/pages/x-change/pay-codes/Index.vue
```

## Objective

Make the listing page operationally useful.

## Layout

```text
Page title + Create button
Stats cards
Filters/search
List/table
```

## Suggested structure

```vue
<XChangeLayout>
    <Head title="Pay Codes" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1>Pay Codes</h1>
                <p>Generate, monitor, and manage disburseable Pay Codes.</p>
            </div>

            <Button @click="router.visit(routes.payCodes.create())">
                Generate Pay Code
            </Button>
        </div>

        <PayCodeStatsCards :stats="computedStats" />

        <Card>
            <CardHeader>
                <PayCodeFilters
                    v-model:search="search"
                    v-model:status="status"
                />
            </CardHeader>

            <CardContent>
                <PayCodeListTable :vouchers="filteredVouchers" />
            </CardContent>
        </Card>
    </div>
</XChangeLayout>
```

## Computed stats

If backend does not provide stats:

```ts
const computedStats = computed(() => {
    const all = props.vouchers?.data ?? props.vouchers ?? [];

    return {
        total: all.length,
        active: all.filter(isActive).length,
        redeemed: all.filter(v => !!v.redeemed_at).length,
        expired: all.filter(isExpired).length,
    };
});
```

## Filtering

```ts
const filteredVouchers = computed(() => {
    return allVouchers.value
        .filter(matchesSearch)
        .filter(matchesStatus);
});
```

## Important

Do not change backend pagination yet unless needed.

If `vouchers` is paginated, preserve existing pagination shape.

---

# Phase 3 — Show.vue Upgrade

Patch:

```text
packages/x-change/resources/js/pages/x-change/pay-codes/Show.vue
```

## Objective

Make Show.vue the operational detail center for a Pay Code.

## Layout

```text
Header with code/status/actions
Left/main column:
  Status card
  Instruction summary
  Claim history
Right column:
  QR/share panel
  Metadata/debug
```

## Suggested desktop layout

```vue
<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <div class="space-y-6">
        <PayCodeStatusCard :voucher="voucher" />
        <PayCodeInstructionSummary :instructions="voucher.instructions" />
        <PayCodeClaimHistory :claims="voucher.claims" />
    </div>

    <div class="space-y-6">
        <PayCodeQrSharePanel
            :code="voucher.code"
            :claim_url="claimUrl"
            :qr_code="voucher.qr_code"
        />

        <PayCodeMetadataCard :voucher="voucher" />
    </div>
</div>
```

## Claim URL

Use centralized route composable if already available:

```ts
const routes = useXChangeRoutes();
const claimUrl = computed(() => routes.claim.startWithCode(voucher.code));
```

If no helper exists, use:

```ts
const claimUrl = computed(() => `/x/claim?code=${voucher.code}`);
```

But prefer `useXChangeRoutes`.

## Required actions

```text
Copy code
Copy claim URL
Open claim page
Back to list
```

## Important

Keep existing data structure. Do not require backend changes unless the currently available Show payload is insufficient.

---

# Phase 4 — Create.vue Upgrade

Patch:

```text
packages/x-change/resources/js/pages/x-change/pay-codes/Create.vue
```

## Objective

Bring redeem-x CreateV2-style UX without dragging in payable/settlement modes.

## Layout

```text
Header
Two-column layout:
  Main: Basic / Advanced tabs
  Side: Cost estimate + instruction preview
```

## Basic tab

Fields:

```text
amount
quantity
required recipient fields
evidence requirements:
  - KYC
  - OTP
  - location
  - selfie
  - signature
rider message
rider URL
```

## Advanced tab

Fields:

```text
prefix
mask/code length
starts_at
expires_at / TTL
splash enabled
splash timeout
feedback options
validation options
raw metadata if already supported
```

## Side panel

```text
Cost estimate
Instruction preview
Generated payload preview
```

## Suggested component composition

```vue
<div class="grid gap-6 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        <Tabs default-value="basic">
            <TabsList>
                <TabsTrigger value="basic">Basic</TabsTrigger>
                <TabsTrigger value="advanced">Advanced</TabsTrigger>
            </TabsList>

            <TabsContent value="basic">
                <PayCodeGenerationBasicForm v-model="form" />
            </TabsContent>

            <TabsContent value="advanced">
                <PayCodeGenerationAdvancedForm v-model="form" />
            </TabsContent>
        </Tabs>
    </div>

    <div class="space-y-6">
        <PayCodeCostEstimateCard :form="form" />
        <PayCodeInstructionPreview :instructions="computedInstructions" />
    </div>
</div>
```

## Submit behavior

Keep current submit mechanism:

```text
usePayCodeForm
usePayCodeApi
existing controller/API
```

Do not change backend payload shape in this UI-only slice unless strictly necessary.

---

# Phase 5 — Route/Composable Audit

Patch if needed:

```text
packages/x-change/resources/js/composables/useXChangeRoutes.ts
```

Ensure it exposes:

```ts
payCodes: {
    index()
    create()
    show(code)
}

claim: {
    start()
    startWithCode(code)
    success(code)
    redirect(code)
}
```

If route helpers already exist, do not duplicate.

---

# Phase 6 — Publish Map

Confirm `XChangeServiceProvider` publishes the new component directory.

Expected publish config should already copy:

```text
packages/x-change/resources/js/components/x-change
→ resources/js/components/x-change
```

If yes, no service provider change needed.

If not, update:

```text
packages/x-change/src/XChangeServiceProvider.php
```

to include:

```php
$this->packagePath('resources/js/components/x-change') => resource_path('js/components/x-change'),
```

---

# Phase 7 — Host App Publish/Test

From host app:

```bash
php artisan vendor:publish --tag=x-change-ui --force
php artisan optimize:clear
rm -rf node_modules/.vite public/build
npm run dev
```

Open:

```text
/x/pay-codes
/x/pay-codes/create
/x/pay-codes/{code}
```

Test:

```text
1. Generate simple disburseable Pay Code
2. See it in Index.vue
3. Open Show.vue
4. Copy/open claim link
5. Complete claim flow
6. Return to Show.vue and verify redeemed/claim history if available
```

---

# Phase 8 — Suggested Implementation Order

Do this in small commits.

## Commit 1 — Shared components

```text
PayCodeStatusBadge
PayCodeStatsCards
PayCodeFilters
PayCodeListTable
```

Commit:

```bash
git commit -m "feat(ui): add pay code dashboard shared components"
```

## Commit 2 — Index.vue

```text
Upgrade listing page with stats, filters, and table.
```

Commit:

```bash
git commit -m "feat(ui): improve pay code listing dashboard"
```

## Commit 3 — Show.vue

```text
Add status, QR/share, instructions, and history sections.
```

Commit:

```bash
git commit -m "feat(ui): improve pay code detail view"
```

## Commit 4 — Create.vue shared components

```text
Generation basic/advanced form components
Cost estimate
Instruction preview
```

Commit:

```bash
git commit -m "feat(ui): add pay code generation form sections"
```

## Commit 5 — Create.vue page upgrade

```text
Wire components into Create.vue.
```

Commit:

```bash
git commit -m "feat(ui): upgrade pay code generation workflow"
```

---

# Quality Bar

Before each commit:

```bash
npm run build
```

If tests exist:

```bash
./vendor/bin/pest
```

Manual checks:

```text
- mobile width
- desktop width
- dark mode
- no sidebar in public claim pages
- dashboard pages still use XChangeLayout
- no references to redeem-x paths
- no settlement/payable UI exposed yet
```

---

# Important Guardrails for AI Agent

Do not:

```text
- import files from redeem-x directly
- add payable or settlement tabs
- introduce new backend payload shape
- hardcode host app URLs
- edit host app pages first
- remove current working form submit logic
```

Do:

```text
- patch package source under packages/x-change
- keep components package-owned
- use existing composables
- preserve current API calls
- keep pages publishable
- make UI improvements incremental
```

---

# End State

After this work:

```text
Create.vue feels like a guided Pay Code generator
Index.vue feels like an operational Pay Code dashboard
Show.vue feels like a lifecycle/detail console
claim/form-flow pages remain visually consistent
licensees get a polished default UI after x-change:install
```
