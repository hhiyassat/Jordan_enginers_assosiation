# CS-03 · Connect PaymentGateway to runtime

## Defect at start of sprint

`App\Services\Payment\PaymentGateway` (with `initiate` /
`verifyCallback` / `refund`), `PaymentIntent`, `PaymentInitiation`,
`PaymentReceipt`, and `MockPaymentGateway` all existed and had
production-safety fences (`ProductionSafety::checkPaymentGatewayBinding`
aborts boot if the Mock is bound). **Nothing in the runtime called
any of them.**

Instead:

* `PaymentsController::confirm` accepted a raw `payment_reference`
  string from the caller (staff or admin) and immediately flipped
  `applications.payment_status → paid` via
  `WorkflowEngine::confirmPayment`. Any staff account could mark any
  application paid by inventing a reference. Anyone with a stolen
  staff token could do the same.
* There was no callback / webhook controller of any kind. The
  `verifyCallback()` interface method had **zero callsites** in
  production code — `SecurityEvents::paymentCallbackFailure()`
  likewise had zero callsites.

The whole abstraction was infrastructure with no traffic on it.

## What changed

### Runtime is now built around the gateway

* **New `PaymentCallbackController` (JEA-side, unauthenticated).**
  Route: `POST /api/payment/callback`. Injects
  `App\Services\Payment\PaymentGateway`, calls `verifyCallback()` on
  the raw request payload (a signature failure returns HTTP 401 and
  emits `SecurityEvents::paymentCallbackFailure`), looks up the
  application by `payment_reference`, then confirms via
  `WorkflowEngine::confirmPaymentFromReceipt()`. This is the only
  path that flips `payment_status` off a network-originated event.

* **Idempotency via `payment_callbacks` table.** New migration
  ships a JEA-owned table with `unique(reference)` — a replayed
  callback (even with a valid signature) folds into the same 200
  response with `idempotent: true` and does not re-run the workflow
  mutation. Insertion is inside the same DB transaction as the
  workflow confirmation, so concurrent duplicates can only produce
  one committed effect.

* **New `PaymentsController::initiate`.** Route:
  `POST /applications/{id}/initiate-payment`. Resolves the gateway
  from the container, builds a `PaymentIntent` from application +
  service data, calls `PaymentGateway::initiate()`, persists the
  returned reference on the application, writes an
  `application.payment_initiated` audit-log entry, and returns the
  redirect URL for the SPA to follow. This is where the runtime
  first uses the abstraction on the outgoing side.

* **`PaymentsController::confirm` rewritten as admin-only manual
  reconciliation.** No longer usable as proof-of-payment. Now
  requires:
  * `admin` role (was `staff,admin`);
  * an explicit `manual_reason` string ≥ 10 characters — enforced
    by `ConfirmPaymentRequest::rules()`, not a controller-level
    check that's easy to bypass;
  * writes a separate `application.payment_manual_reconciliation`
    audit-log entry alongside the workflow's own
    `application.payment_confirmed` entry so the operator's stated
    rationale is searchable.

* **`SignedTestPaymentGateway`** — a real HMAC-verifying
  implementation of `PaymentGateway` lives under
  `tests/Support/`. It signs and validates
  `reference|amount|currency|settled_at` with a shared secret,
  enforces a 5-minute skew window, and is the impl every callback
  test binds. This gives the callback flow real signature-based
  coverage without inventing eFAWATEERcom or JoMoPay proprietary
  framing (which is BLOCKED_EXTERNAL_INPUT).

* **`MockPaymentGateway`** stays exactly as it was (unsigned, dev-only)
  and is still fenced by `ProductionSafety`. Any real production
  activation continues to require binding a real
  `PaymentGateway` implementation — status
  `BLOCKED_EXTERNAL_INPUT`.

### Routes

```
POST /api/payment/callback                        (public, throttle:60,1)
POST /api/v1/applications/{id}/initiate-payment   (applicant+ roles)
POST /api/v1/applications/{id}/confirm-payment    (admin only)
```

### Files

* **New:**
  * `backend/modules/JeaServices/Http/Controllers/PaymentCallbackController.php`
  * `backend/modules/JeaServices/Http/Requests/InitiatePaymentRequest.php`
  * `backend/modules/JeaServices/Models/PaymentCallback.php`
  * `backend/modules/JeaServices/Database/Migrations/2026_07_31_000020_create_payment_callbacks_table.php`
  * `backend/tests/Support/SignedTestPaymentGateway.php`
  * `backend/tests/Feature/Payment/PaymentCallbackControllerTest.php` (7 tests)
  * `backend/tests/Feature/Payment/PaymentInitiateAndManualConfirmTest.php` (6 tests)
* **Rewritten:**
  * `backend/modules/JeaServices/Http/Controllers/PaymentsController.php`
    (adds `initiate`, refactors `confirm`)
  * `backend/modules/JeaServices/Http/Requests/ConfirmPaymentRequest.php`
    (admin authorize + manual_reason rule)
  * `backend/modules/JeaServices/routes.php`
    (new callback route, split payment routes by role)
  * `backend/tests/Feature/CrossTenantIsolationTest.php`
    (retargets confirm-payment to adminB + valid manual_reason so
     the tenant-boundary assertion still holds after the role narrowing)
  * `backend/modules/JeaServices/Models/Application.php`
    (adds `@property` annotations for `payment_*`, `organization_id`,
     `basin_number`, `parcel_number` — required by the two new
     controllers under PHPStan; annotations only, no behaviour change)

## Design decision: where does `PaymentCallbackController` live?

**In JEA, not Platform.** The callback needs `Application::payment_reference`
lookup + `WorkflowEngine::confirmPaymentFromReceipt` — both JEA-owned.
Putting the controller in Platform (`App\Http\Controllers\Api\...`)
would create the exact `Platform → JEA` import that CS-05 exists to
remove. Keeping it in JEA is consistent with `JeaNotificationService`
(JEA composes on a Platform primitive but the JEA-shaped code lives
in JEA). If a second module ever needs payment callbacks, a neutral
`ApplicationLookup::findByPaymentReference()` contract can be extended
(see CS-04) — that migration is a downstream change, not a CS-03
change.

## Verification

### Focused tests

```
$ php artisan test tests/Feature/Payment tests/Unit/Services/Payment \
     tests/Feature/ConfirmPaymentFromReceiptTest.php \
     tests/Feature/CrossTenantIsolationTest.php
{"tool":"phpunit","result":"passed","tests":34,"passed":34,"assertions":83,"duration_ms":570}
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":890,"passed":886,"assertions":2958,"duration_ms":29471,"skipped":4}
```

13 net-new tests over the CS-02 baseline of 877 tests. Zero
regressions.

### PHPStan on all CS-03 files

```
$ vendor/bin/phpstan analyse \
    modules/JeaServices/Http/Controllers/PaymentsController.php \
    modules/JeaServices/Http/Controllers/PaymentCallbackController.php \
    modules/JeaServices/Http/Requests/InitiatePaymentRequest.php \
    modules/JeaServices/Http/Requests/ConfirmPaymentRequest.php \
    modules/JeaServices/Models/PaymentCallback.php \
    tests/Support/SignedTestPaymentGateway.php
{"tool":"phpstan","result":"passed","errors":0}
```

Pre-existing errors in `modules/JeaServices/Models/Application.php`
(missing generic types on `BelongsTo` / `HasMany` return types;
`ServiceDefinition::$schema` property access) are untouched and
recorded in the residual backlog rather than expanded into this
sprint item.

### Migration reversibility

```
$ php artisan migrate --path=modules/JeaServices/Database/Migrations/2026_07_31_000020_...
 2026_07_31_000020_create_payment_callbacks_table .. 4.67ms DONE
$ php artisan migrate:rollback --step=1
 2026_07_31_000020_create_payment_callbacks_table .. 4.02ms DONE
```

## Report fields

```
ITEM_ID=CS-03
ORIGINAL_FINDING=NEW-A2 (PaymentGateway abstraction had zero runtime consumers; raw payment_reference was accepted as proof-of-payment)
START_HEAD=5d0252f
END_HEAD=89bfc401e4fed4ea59f5442001922d1da39aac40
STATUS=FIXED
ROOT_CAUSE=The gateway abstraction was infrastructure without any callers — no initiation path, no callback path, and the confirm-payment endpoint bypassed it entirely. Any staff (later, admin) account could flip payment_status by inventing a reference. `verifyCallback()` was interface-only.
IMPLEMENTATION_DECISION=Build the complete internal payment boundary against the existing abstraction: dedicated callback controller (verifyCallback + idempotency table), applicant-facing initiate() endpoint that uses PaymentGateway::initiate(), and reduce confirm-payment to admin-only manual reconciliation with a required rationale string. Add a real HMAC-signed test gateway to cover unsigned/invalid/tampered/expired/duplicate/valid callback paths deterministically. Leave the real-provider binding as BLOCKED_EXTERNAL_INPUT.
FILES_CHANGED=backend/modules/JeaServices/Http/Controllers/PaymentsController.php; backend/modules/JeaServices/Http/Controllers/PaymentCallbackController.php (NEW); backend/modules/JeaServices/Http/Requests/ConfirmPaymentRequest.php; backend/modules/JeaServices/Http/Requests/InitiatePaymentRequest.php (NEW); backend/modules/JeaServices/Models/PaymentCallback.php (NEW); backend/modules/JeaServices/Models/Application.php (@property annotations only); backend/modules/JeaServices/routes.php; backend/tests/Feature/CrossTenantIsolationTest.php (retargeted to admin + reason)
MIGRATIONS_ADDED=modules/JeaServices/Database/Migrations/2026_07_31_000020_create_payment_callbacks_table.php
TESTS_ADDED=tests/Feature/Payment/PaymentCallbackControllerTest.php (7 tests: unsigned/invalid-sig/tampered/expired/valid/duplicate-idempotent/unknown-ref); tests/Feature/Payment/PaymentInitiateAndManualConfirmTest.php (6 tests: initiate uses gateway, initiate idempotent when already paid, staff-cannot-confirm-403, admin-requires-reason-422, admin-with-reason-audits-and-confirms, container-resolves-signed-test-gateway); tests/Support/SignedTestPaymentGateway.php (new HMAC test impl of PaymentGateway)
TESTS_MODIFIED=tests/Feature/CrossTenantIsolationTest.php (test_org_b_admin_cannot_confirm_payment_on_org_a_application — was staff, now admin + manual_reason, preserving the tenant-boundary assertion after the role narrowing)
FOCUSED_TEST_RESULT=PASS (34 tests / 83 assertions across Payment/Feature + Unit/Services/Payment + ConfirmPaymentFromReceiptTest + CrossTenantIsolationTest)
CONTAINING_SUITE_RESULT=PASS (full backend suite — 890 tests / 886 passed / 4 skipped / 2958 assertions)
STATIC_ANALYSIS_RESULT=PASS (PHPStan 0 errors across every touched CS-03 file). Pre-existing Application.php errors (generic type hints on relations; ServiceDefinition::$schema) are untouched.
RUNTIME_VERIFICATION=Callback flow exercised end-to-end against SignedTestPaymentGateway: valid callback flips payment_status via WorkflowEngine::confirmPaymentFromReceipt; a replayed valid callback hits the unique(reference) index and returns idempotent success without a second workflow mutation. Initiate flow persists the gateway reference on the application and writes application.payment_initiated audit. Manual confirm requires admin + reason and writes application.payment_manual_reconciliation audit.
RESIDUAL_RISK=Real-provider adapter is not bound in production. Any deploy still requires binding a real PaymentGateway implementation (concrete provider class + credentials) before payments can flow. The refund() interface method still has no controller — no operator surface to trigger a refund exists yet.
EXTERNAL_BLOCKER=BLK-01 (real payment provider: contract + credentials + callback protocol) remains unresolved.
COMMIT=89bfc40
NEXT_ITEM=CS-04
```

## Acceptance criteria

```
PAYMENTS_CONTROLLER_USES_GATEWAY=YES               (initiate() resolves + calls PaymentGateway::initiate())
RAW_REFERENCE_IS_NOT_PROOF_OF_PAYMENT=YES          (confirm-payment is admin-only + requires manual_reason)
CALLBACK_VERIFICATION_REQUIRED=YES                 (PaymentCallbackController calls gateway.verifyCallback; 401 on failure)
CALLBACK_IDEMPOTENCY=YES                           (payment_callbacks.unique(reference) + insert-inside-transaction dedupe)
REAL_PROVIDER_STATUS=BLOCKED_EXTERNAL_INPUT
```
