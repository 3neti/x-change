# Time authority

x-change treats every persisted event, activation, expiration, observation,
and settlement boundary as an instant governed by UTC.

## Invariants

- Laravel, PHP, and the database session use UTC.
- External and operator-supplied instants include `Z` or a numeric offset.
- Authoritative instants are normalized before Eloquent serializes them.
- New APIs use canonical UTC with microsecond precision. Existing immutable
  evidence retains its original approved representation until a schema-versioned
  evidence migration is introduced.
- Local time conversion occurs only in read models and user interfaces.
- A recurring civil schedule stores its IANA timezone separately; it is not
  converted into a one-time UTC instant ahead of execution.
- Calendar-only values use a date rather than a timestamp.

`UtcImmutableDateTime` is the storage cast for authoritative model fields.
`UtcInstant` is the parser and canonical serializer for external boundaries.
The strict x-change doctor fails when Laravel, PHP, and the database session
do not share UTC authority.

## Legacy columns

The historical schema contains both timezone-aware and timezone-less columns.
UTC runtime alignment keeps those legacy values operational, but it does not
prove their original interpretation. A timezone-less column may be converted
only after its values have been characterized against provider and journal
evidence. The forward migration must explicitly interpret proven legacy values
as UTC. Ambiguous evidence is preserved and resolved through an append-only
correction rather than overwritten.

The standing funding-address binding protocol is the reference implementation:
it stores cutovers as `timestamptz(6)`, compares in UTC, preserves the approved
instant, and records any historical correction append-only without provider or
Treasury mutation.
