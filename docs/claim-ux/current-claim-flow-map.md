# Current Claim Flow Map

This map is the short-form companion to the larger claim UX notes. It describes the current claim surfaces as they exist after the claim shell, rider, form-flow, approval, and campaign authorization scaffolds.

## Canonical Entry

- Canonical direct claim route: `/x/claim/{code}`.
- Query entry still exists for compatibility: `/x/claim?code={code}`.
- Campaign/officer links should prefer the canonical direct route so authentication handoff can preserve the intended claim URL.

## Redeemer Disbursement Journey

1. The redeemer opens `/x/claim/{code}`.
2. The x-change claim shell compiles the voucher instructions and shows the claim entry/x-ray/splash stages configured by `config/x-change.php`, `.env`, and the voucher rider instructions.
3. If the voucher uses form-flow, the shell hands off to `/form-flow/{flowId}`.
4. The form-flow generic form collects mobile, amount, and destination fields according to the compiled step config.
5. The confirmation stage submits the claim.
6. x-change compares the submitted fields with the immutable claim-evidence requirements captured at Pay Code issuance.
7. Required evidence is normalized and persisted against a prepared claim attempt before an execution driver may contact a bank, EMI, or settlement service.
8. Success renders in x-change, then rider message/splash/redirect behavior follows the compiled claim experience.

## Durable Claim Evidence

New Pay Codes snapshot their evidence requirements as scalar keys under `instructions.metadata.custom.claim_evidence`. The execution boundary fails closed when a required item is absent or when a verification requirement such as OTP or KYC is not verified.

Each claim owns its evidence records. Voucher-wide input records remain a compatibility projection and must not be used to associate evidence with a claimant. This prevents evidence from divisible, retried, or subsequent claims from being mixed.

- Text and structured values are encrypted at rest.
- Selfies, signatures, KYC images, and location maps use content-addressed private storage.
- Raw binary evidence is never included in Inertia page props, logs, or journal payloads.
- Media is revealed only through the owner/system-authorized, `no-store` evidence endpoint.
- Every reveal writes `pay_code.evidence.viewed` without copying the artifact into the journal.
- OTP codes are never retained; only the verified state, safe reference, and verification time are evidence.
- The claim stores a SHA-256 evidence-manifest reference. When a Settlement Envelope exists, x-change attaches the manifest hash and completeness state as claim-specific envelope signals.

The Cockpit separates four concepts:

1. **Claim Requirements** — what issuance required.
2. **Captured Evidence** — what a particular claim supplied.
3. **Verification Results** — what OTP, KYC, or another authority confirmed.
4. **Payout Outcome** — whether provider settlement succeeded, remains pending, or was rejected.

Claims completed before this boundary are shown honestly as legacy evidence that was not retained; missing historical values are never reconstructed.

## Paynamics OTP Journey

Paynamics payout OTP is issuer-side authorization, not redeemer-side input.

1. The redeemer submits the claim.
2. Paynamics requires OTP and sends it to the issuer or authorized payout owner.
3. The redeemer sees the x-change approval waiting page.
4. The issuer opens the issuer/admin approval URL and enters the OTP.
5. x-change replays/completes the pending claim.
6. The redeemer can refresh/poll into success; the issuer should return to the issuer surface, such as `/x/pay-codes/{code}` or `/x/pay-codes`.

## Campaign Officer Authorization Journey

Campaign authorization is not a payout form. It is an authenticated officer action inside the claim shell.

1. The officer opens `/x/claim/{code}`.
2. If the voucher requires an authenticated officer and the session is anonymous, x-change redirects to login and preserves the intended URL.
3. After login, x-change resumes the claim route.
4. The claim workflow resolver marks the form-flow instructions as `campaign.officer-authorization.v1`.
5. The wallet step becomes an authorization step:
   - destination fields are removed,
   - amount is removed,
   - officer mobile is retained and made readonly when available,
   - the button label becomes `Authorize Campaign`.
6. Submitting the form authorizes the campaign worksheet. It does not disburse funds to beneficiaries.

## Safety Boundary

Generic form-flow pages must not infer x-change behavior from route names, handler names, or field names. x-change-specific UI should appear only when the form receives `claim_workflow` metadata.

If `claim_workflow` is absent, the form-flow screen should behave as the package default.
