# Cockpit Wave 51C — Campaign Draft Compiler Request Compatibility

## Status

Completed.

## Scope

Compile a single-recipient campaign issuance draft into the existing `GeneratePayCodeRequest` payload shape.

## Protected behavior

The characterization proves:

- campaign context maps into `metadata.campaign`;
- template intent maps to `metadata.custom.cockpit.template_key`;
- recipient mobile maps to `cash.validation.mobile` and `feedback.mobile`;
- recipient email maps to `feedback.email`;
- purpose maps to `rider.message`;
- compiled payload passes `GeneratePayCodeRequest` validation rules.

## Boundary

This slice does not call `GeneratePayCode` and does not issue a Pay Code.

It only proves request compatibility for the existing issuance owner.

No campaign mutation, bulk issuance, distribution dispatch, feedback delivery, journal writes, provider calls, wallet movement, lifecycle truth ownership, or unsafe payload exposure was added.

## Next slice

`Cockpit Wave 51D — Functional Bridge Safety Boundary`
