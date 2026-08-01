# Justified-Duplication Register

Phase 6 of the cleanup sprint. Documents every KEEP_SEPARATE
duplicate group so that "these look similar" is not mistaken for
technical debt in a future audit.

Similarity alone is not duplication. A duplicate is genuinely
consolidatable only when all of these hold:

* same business meaning
* same invariants
* same owner
* same lifecycle
* same side effects
* shared evolution is expected

The eight backend groups below fail at least one of those tests.
The three frontend supporting groups are recorded for the same
reason.

---

## Backend KEEP_SEPARATE groups (audit's `KEEP_SEPARATE_COUNT=8`)

### DG-02 · Payment confirmation split — manual admin vs webhook

* **Implementations:**
  * `WorkflowEngine::confirmPayment(Application, User, string $reference)` (backend/modules/JeaServices/Engine/WorkflowEngine.php:472)
  * `WorkflowEngine::confirmPaymentFromReceipt(Application, User, PaymentReceipt)` (same file, 498)
  * `PaymentsController::confirm` (manual admin path — routes to `confirmPayment`)
  * `PaymentCallbackController::handle` (webhook path — routes to `confirmPaymentFromReceipt`)
* **Domain owners:** JeaServices (WorkflowEngine + both controllers).
* **Shared appearance:** both flow into a `payment_status='paid'` state mutation.
* **Different invariants:**
  * Manual path requires `manual_reason` (admin explains why the mutation happened outside the webhook).
  * Webhook path requires a verified `PaymentReceipt` from
    `PaymentGateway::verifyCallback` + a unique callback insert.
* **Why consolidation is unsafe:** CS-03 introduced the split
  precisely because a raw payment_reference was never proof of
  payment. Unifying the two entry points would either erase the
  proof-of-payment guarantee OR force admin manual reconciliation
  through a fake receipt.
* **Tests protecting separation:** `ConfirmPaymentFromReceiptTest`,
  `PaymentCallbackControllerTest`, `PaymentInitiateAndManualConfirmTest`.
* **Review trigger:** any change that removes `manual_reason` from
  `ConfirmPaymentRequest`, or removes the receipt requirement from
  `confirmPaymentFromReceipt`.

### DG-03 · Certificate serial vs Application reference serial

* **Implementations:**
  * `WorkflowEngine::generateCertificateNumber` (backend/modules/JeaServices/Engine/WorkflowEngine.php:696)
  * `Application::generateReference` (backend/modules/JeaServices/Models/Application.php:281)
* **Domain owner:** JeaServices for both — but different objects.
* **Shared appearance:** identical concurrency pattern
  (`insertOrIgnore` + `lockForUpdate` inside `DB::transaction(attempts:5)`).
* **Different invariants:**
  * Application counter is keyed by `(service_definition_id, year)`;
    certificate counter is keyed by `(organization_id, year)`.
  * Certificate serial adds an HMAC-signed `qr_token`; application
    reference does not.
  * Called at completely different workflow stages.
* **Why consolidation is unsafe:** extracting a generic
  `SerialGenerator` for two callsites is premature abstraction. If
  a third serial appears (dues invoices? sanction case number?),
  revisit.
* **Tests protecting separation:** `CertificateSerialAllocationTest`,
  `ApplicationReferenceSerialTest`, and both are exercised under
  real Postgres pcntl_fork in `RealConcurrencyOnPostgresTest`.
* **Review trigger:** a third counter table appearing in the schema.

### DG-05 · SchemaValidator (generic) vs Srv001Guard (SRV-001 specific)

* **Implementations:**
  * `Modules\JeaServices\Engine\SchemaValidator::validateData / validateDocuments`
  * `Modules\JeaServices\Engine\Srv001Guard::validate`
* **Domain owners:** AiSchema plugin (SchemaValidator, schema-driven) + JeaServices (Srv001Guard, business rules).
* **Shared appearance:** both return field-id-keyed error arrays;
  both are invoked from `ApplicationController::submit`.
* **Different invariants:**
  * `SchemaValidator` runs first, exits early, and is stateless.
  * `Srv001Guard` runs after schema, mutates `app->data` with
    derived values (net depth, exploration count), and enforces
    the SRV-001 exploration matrix.
  * New services add a new `ServiceSubmissionGuard`, they do NOT
    modify `SchemaValidator`.
* **Why consolidation is unsafe:** fusing would leak SRV-001 rules
  into a generic form validator.
* **Tests protecting separation:** `SchemaValidatorTest`,
  `Srv001GuardTest`.
* **Review trigger:** any code path adding schema-level rules to a
  per-service guard, or vice versa.

### DG-06 · `JeaNotificationService` vs Platform `NotificationService`

* **Implementations:**
  * `Modules\JeaServices\Services\JeaNotificationService` (JEA-shaped emitters + reminder dedupe)
  * `App\Services\Notifications\NotificationService::sendToUser` / `dispatch` (generic primitive)
* **Domain owners:** JeaServices vs Platform.
* **Shared appearance:** both build `Notification` rows.
* **Different invariants:**
  * `JeaNotificationService::send` takes a JEA `Application`;
    `NotificationService::dispatch` takes a generic `User`.
  * H-07 split JEA emitters from the Platform primitive
    specifically to remove the Platform→JEA import that had lived
    on `NotificationService`.
  * `JeaNotificationService::emitExpiryReminder` deduplicates on
    `(application_id × kind × threshold_days)`; the platform
    primitive does not.
* **Why consolidation is unsafe:** unifying would reintroduce the
  Platform→JEA import that H-07 explicitly removed.
* **Tests protecting separation:** `BoundariesTest::test_platform_does_not_import_service_modules`.
* **Review trigger:** any refactor that reverses H-07.

### DG-07 · Nashmi `ValidateIntegrationKey` vs GSB `GsbIpWhitelist`

* **Implementations:**
  * `Integrations\Nashmi\Http\Middleware\ValidateIntegrationKey` (IP + integration key + HMAC + timestamp + nonce)
  * `Integrations\Gsb\Http\Middleware\GsbIpWhitelist` (IP only)
* **Domain owners:** two separate integrations.
* **Shared appearance:** both are inbound integration security middlewares with IP allowlist.
* **Different invariants:**
  * Nashmi is comprehensive (HMAC + timestamp + atomic nonce
    dedupe — hardened by CS-07).
  * GSB is minimal at the middleware; further checks live in
    `GsbClient` (OAuth2 + retention policy).
  * Different config trees, different log channels, different fail-closed policies.
* **Why consolidation is unsafe:** extracting a shared IP-allowlist
  middleware would be a leaky abstraction — the caller couldn't
  see whether the middleware in question also enforces HMAC/nonce.
* **Tests protecting separation:** `NashmiSecurityTest`,
  `NashmiNonceEnforcementTest`, `GsbSecurityTest`.
* **Review trigger:** a third integration arriving with the same
  security scope as Nashmi (then extract a signed-webhook base
  middleware).

### DG-10 · `RespondsWithLockedService` trait consumers

* **Implementations:**
  * `Modules\JeaServices\Http\Concerns\RespondsWithLockedService` (the trait)
  * `ServiceCatalogController` + `ServiceFeesController` use the trait
* **Domain owner:** JeaServices.
* **Shared appearance:** both controllers emit the same 423 Locked
  response for is_locked services.
* **Not duplication:** the trait IS the shared implementation.
  Consumers correctly delegate; the trait method is the canonical
  responder.
* **Why consolidation is unsafe:** already consolidated. No further
  action needed.
* **Tests protecting separation:** every service-catalog + fee
  controller test exercises the trait method via its consumers.
* **Review trigger:** none — this group is on the register only to
  make explicit that the audit correctly did not double-count.

### DG-11 · `CapacityGuard` (gate) vs `QuotaLedger` (ledger)

* **Implementations:**
  * `Modules\JeaProjects\Engine\CapacityGuard::validate` (submit-time gate)
  * `Modules\JeaProjects\Engine\QuotaLedger::recordApproval / releaseFor / overflowSurchargeFor` (write-side ledger + surcharge)
* **Domain owner:** JeaProjects for both.
* **Shared appearance:** both read the same `QuotaConsumption` +
  `OfficeCeiling` tables.
* **Different invariants:**
  * `CapacityGuard` is a read-only submission gate.
  * `QuotaLedger` owns the write path — record on approval,
    release on soft-delete, compute overflow surcharge.
  * Model `Application::booted::deleted → QuotaLedger::releaseFor`
    depends on the ledger side being callable independently of the
    gate.
* **Why consolidation is unsafe:** fusing them would prevent
  soft-delete quota release — the model hook can't fire the gate.
* **Tests protecting separation:** `CapacityGuardTest`,
  `QuotaConsumptionOnApprovalTest`, and the model hook regression
  under `RealConcurrencyOnPostgresTest`.
* **Review trigger:** any attempt to move `recordApproval` into
  `CapacityGuard`.

### DG-12 · `FeeCalculator` (schema fees) vs `QuotaLedger::overflowSurchargeFor` (quota surcharge)

* **Implementations:**
  * `Modules\JeaServices\Engine\FeeCalculator::calculateBreakdown`
  * `Modules\JeaProjects\Engine\QuotaLedger::overflowSurchargeFor`
* **Domain owners:** JeaServices (schema fees) vs JeaProjects
  (quota-side overflow).
* **Shared appearance:** both contribute line-items to the
  composite `fee_breakdown` returned by
  `ApplicationController::show`.
* **Different invariants:**
  * `FeeCalculator` is schema-driven
    (`fixed`/`tiered`/`formula`/`matrix`/`per_unit` + fixed
    surcharges).
  * `overflowSurchargeFor` is quota-driven (fires only when
    `area_m2` breaches the per-project cap).
* **Why consolidation is unsafe:** these compute different fee
  categories from different data sources. Fusing would erase the
  quota-vs-schema distinction.
* **Tests protecting separation:** `FeeCalculatorTest`,
  `QuotaOverflowSurchargeTest`.
* **Review trigger:** none.

---

## Frontend KEEP_SEPARATE supporting groups (audit's frontend rows)

### FE-DG-02 · `ServiceList.tsx` (applicant) vs `ServicesList.tsx` (admin)

* Different endpoints, different role guards, different UX. Only
  the name similarity looked duplicative.

### FE-DG-03 · `api/jea/hooks.ts` vs `api/platform/hooks.ts`

* Workstream-6 intentional split by domain; the back-compat barrel
  `api/hooks.ts` merges them.

### FE-DG-04 · Applicant / Admin / Reviewer `Dashboard` pages

* Same layout convention, zero code overlap.

---

## Register invariants

* No implementation on this register is modified by any commit in
  this cleanup sprint.
* Adding a group here is a **decision**, not a to-do. The
  register's job is to make sure a future auditor doesn't reopen
  a decided-not-to-consolidate item as "unfinished duplication".
* If any REVIEW TRIGGER above ever fires, re-audit that group
  (do NOT silently merge).
