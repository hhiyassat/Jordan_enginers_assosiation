# CL-03 · Unfinished-scaffold decision — `HttpJeaMembershipVerifier`

## What the audit said

Audit ID **U-02** in `/tmp/esp-v2-unused-code-inventory.csv`:

* Path: `backend/modules/JeaServices/Engine/HttpJeaMembershipVerifier.php`
* Classification: `RESERVED_EXTENSION_WITH_CONTRACT`
* Confidence: HIGH
* Recommendation: KEEP (documented extension point)

## Decision

**`RESERVED_EXTENSION_WITH_CONTRACT`** — matches the audit exactly.

## Why this classification fits

### 1. An external blocker exists

Activation depends on **BLK-02** in `docs/DECISION_REGISTER.md`: the
real JEA endpoint URL + authentication scheme (Bearer / Basic /
custom header) is not available in-repo. The class is a fully-formed
HTTP driver ready for a base URL to be configured, but there is no
provider contract to code against yet. The comment in
`JeaServicesServiceProvider:104-110` is explicit:

```
// Binding for the real driver is deferred here because the base URL /
// auth scheme is BLOCKED_EXTERNAL_INPUT (real JEA endpoint contract not
// available in-repo).
//
// Example production binding once the JEA contract is fixed:
//     $this->app->bind(JeaMembershipVerifier::class, HttpJeaMembershipVerifier::class);
```

### 2. Production safely fails closed

`App\Support\ProductionSafety::checkJeaMembershipVerifierBinding()`
aborts boot in `APP_ENV=production` when the resolved
`JeaMembershipVerifier` implementation is `FakeJeaMembershipVerifier`
(class-name equality check). Any real deploy therefore MUST bind
either `HttpJeaMembershipVerifier` or another real driver before
booting.

### 3. Activation requirements are documented

* **Config keys** (`backend/config/jea.php`):
  `jea.membership_api.base_url`, `.auth_scheme`, `.auth_token`,
  `.auth_header`, `.basic_user`, `.basic_password`, `.timeout`,
  `.retries`, `.retry_delay_ms`. All are read exclusively by
  `HttpJeaMembershipVerifier`.
* **Contract**: `Modules\JeaServices\Engine\JeaMembershipVerifier`
  interface with the single method
  `verify(string $name, string $membershipNumber): JeaMembershipResult`.
* **Consumers**: `OfficeRegistrationValidator` receives the
  interface via constructor DI (production code-path is
  bind-and-go once BLK-02 resolves).

### 4. Explicit owner + activation contract

* **Owner**: JeaServices module, per
  `docs/architecture/office-registration-flow.md`.
* **Activation contract**: `ProductionSafetyTest::test_http_jea_verifier_bound_is_ok`
  pins the invariant that binding Http (or any real impl) MUST
  satisfy the production gate.

## Rejected classifications

* **`CONNECT_TO_RUNTIME`** — REJECTED. Cannot wire without the base
  URL + auth credentials. Would introduce a broken production path.
* **`DELETE_AS_UNREQUIRED`** — REJECTED. A concrete requirement
  exists (production office-registration signup requires the JEA
  membership check; ProductionSafety enforces it). Deletion would
  leave the production gate with no implementation to satisfy it.
* **`BACKLOG_EXPLICIT`** — REJECTED. The feature is required for
  production acceptance, not merely valid-but-optional. Marking it
  backlog would misrepresent the production-safety story.

## Changes

None. This is a doc-only decision confirming the audit
classification.

The existing `JeaServicesServiceProvider:97-114` comment block
already contains the activation instructions verbatim; no
strengthening needed.

## Verification

None required — no code change. Sanity check ran anyway:

```
$ vendor/bin/phpstan analyse \
    modules/JeaServices/Engine/HttpJeaMembershipVerifier.php \
    modules/JeaServices/Providers/JeaServicesServiceProvider.php
{"tool":"phpstan","result":"passed","errors":0}
```

## Report fields

```
DECISION_ID=CL-03
AUDIT_ID=U-02
CLASSIFICATION=RESERVED_EXTENSION_WITH_CONTRACT
ACTION_TAKEN=none (documentation of the audit decision)
EXTERNAL_BLOCKER=BLK-02 (real JEA endpoint URL + auth scheme)
OWNER=JeaServices module
ACTIVATION_CONTRACT=bind JeaMembershipVerifier::class => HttpJeaMembershipVerifier::class in a deployment ServiceProvider; ProductionSafety::checkJeaMembershipVerifierBinding will then pass
COMMIT=<recorded post-commit in ledger>
```
