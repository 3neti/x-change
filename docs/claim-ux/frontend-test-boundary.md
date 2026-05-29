# Claim UX Frontend Test Boundary

## Current frontend coverage

`useClaimSuccessRedirect.test.ts` owns redirect decision logic.

It verifies:

- compiled redirect is built when enabled
- no redirect is built when disabled
- rider redirect takes precedence

`Success.redirect-countdown.test.ts` owns Success page wiring.

It verifies:

- `Success.vue` renders `RiderCountdown`
- countdown is hidden when disabled
- countdown is hidden without `redirectEndpoint`
- rider redirect wins
- raw rider URL is never passed as redirect target

## Boundary rule

`x-change` tests do not test `RiderCountdown` timer internals.

That belongs to `x-rider`.

`x-change` only verifies that the correct redirect object and safe redirect endpoint are passed to `RiderCountdown`.

## Security rule

The success page must route through:

```text
redirectEndpoint
```

not directly to:
```text
rider.redirect.url
```

The backend ClaimRedirectController remains the redirect gate.
