# Cockpit Wave 53C — Quick Generate Full URL UI Presentation

## Status

Completed.

## Scope

Render the existing full Pay Code redeem URL after a successful Quick Generate submit.

## UI Change

The Quick Generate result panel now includes a read-only `Beneficiary Pay Code URL` section when `result.links.redeem` or `result.links.redeem_path` is present.

The panel shows:

- full URL;
- redeem path;
- read-only status;
- explicit copy that showing the URL does not send SMS, email, webhook, or campaign delivery.

## Boundary

This is presentation only.

It does not:

- send notifications;
- dispatch campaign delivery;
- call providers;
- mutate campaign state;
- write journal entries;
- execute actions;
- move money.

## Expected UI Result

After a successful `/x/cockpit/quick-generate` submit, operators can see the full beneficiary-facing Pay Code URL in the result panel.
