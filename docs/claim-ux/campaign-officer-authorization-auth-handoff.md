# Campaign Officer Authorization Auth Handoff

Date: 2026-07-30

## Intent

Campaign worksheet authorization Pay Codes are officer-side approvals, not beneficiary redemption links. If an unauthenticated user opens one, x-change now shows an explicit authorization handoff before login.

## Journey

1. Officer opens `/x/claim/{code}` for a Pay Code whose claim workflow resolves to `campaign.officer-authorization.v1`.
2. If no authenticated session exists, x-change stores:
   - `url.intended` => `/x/claim/{code}`
   - `x-change.auth.intent` => campaign authorization intent payload
3. The officer is redirected to `/x/claim/{code}/authorization-required`.
4. The handoff page explains that this Pay Code approves a frozen worksheet and does not issue Pay Codes, deliver messages, transfer funds, or redeem a beneficiary payout by itself.
5. The officer clicks through to `login`.
6. Fortify preserves the intended URL and returns the officer to `/x/claim/{code}` after successful mobile/PIN login.
7. If mobile verification is required, `/x/onboarding/mobile/verify` receives the same `auth_intent` prop and renders campaign-specific officer copy.
8. Once authenticated with a usable officer mobile, the normal compiled claim workflow renders the single officer authorization form and executes `campaign_worksheet_authorization`.

## Endpoints

- `GET /x/claim/{code}`: public claim entry. Campaign authorization Pay Codes require an authenticated officer before the claim UI renders.
- `GET /x/claim/{code}/authorization-required`: public explanatory handoff for unauthenticated campaign authorization.
- `GET /login`: Fortify login page, now receives `auth_intent` when a campaign authorization session intent exists.
- `GET /x/onboarding/mobile/verify`: authenticated mobile verification page, now receives `auth_intent` for campaign-specific copy.

## Boundary

The handoff does not relax authorization. It only replaces an abrupt generic login redirect with an explicit officer-facing explanation. The actual worksheet approval still requires:

- an authenticated officer session,
- an officer profile with mobile,
- the claim mobile matching the authenticated officer mobile,
- the campaign authorization status remaining `awaiting_officer`,
- the worksheet manifest hash matching the frozen authorization.

## Files

- `src/Support/Claim/CampaignOfficerAuthorizationLoginIntent.php`
- `src/Http/Controllers/Web/Claim/ClaimAuthorizationRequiredController.php`
- `src/Http/Controllers/Web/Claim/ClaimPageController.php`
- `src/Http/Controllers/Web/Claim/ClaimStartController.php`
- `src/Http/Controllers/Web/Onboarding/MobileVerificationPageController.php`
- `src/Providers/XChangeServiceProvider.php`
- `resources/js/pages/x-change/claim/AuthRequired.vue`
- `resources/js/pages/x-change/onboarding/MobileVerification.vue`
- `stubs/resources/js/pages/auth/Login.vue.stub`

## Tests

- Feature coverage proves unauthenticated campaign authorization redirects to the handoff and persists `url.intended`.
- Feature coverage proves mobile verification receives the campaign authorization intent.
- Frontend coverage proves the handoff page and mobile verification page render campaign-specific copy.
