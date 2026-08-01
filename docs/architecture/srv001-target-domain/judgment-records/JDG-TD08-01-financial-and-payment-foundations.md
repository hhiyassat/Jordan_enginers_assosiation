JUDGMENT_ID=JDG-TD08-01
TITLE=Provisional SRV-001 financial + payment foundations (no disputed values chosen; no production integration)
OWNER=TD-08
PHASE=TD-08 (Batch 5 · versioned financial + payment foundations — mandatory stop after this phase)

الوضع=
TD-07 (`fa40d0a`) delivered the versioned workflow + review + PartialEditGrant use-case foundations, plus BURA/Map ports and certificate eligibility/issuance-request VOs. Batch 5 requires: versioned financial rule structures, typed financial decisions, fee/tax quote envelopes with full provenance, exemption + donation-campaign decisions, payment orchestration boundaries (initiation, callback with replay protection, confirmation, receipt), and a financial-correction request — **without** activating disputed fees, taxes, currency, unit or rounding values (OD-01/OD-10/OD-15/OD-17/OD-19/OD-30/OD-35 all preserved as blockers), **without** enabling any production payment gateway, and **without** publishing any target financial `RuleVersion`.

تحرير_محل_النزاع=
1. **Where does OD-19 (unit/currency/rounding) block?** In each caller (fragile), or as a construction-time invariant on the QUOTED path.
2. **How does the payment-initiation boundary refuse simulation quotes?** By caller check (bypassable), or by construction-time refusal on `PaymentInitiationRequest`.
3. **Where does mandatory-campaign legal-authority enforcement live?** In a service consumer, or as a construction-time invariant on `DonationCampaignDecision`.
4. **How does callback-replay protection prove atomicity without a real payment adapter?** Deterministic in-memory guard whose invariants match the eventual DB unique-constraint semantics.

السبب=
The Batch 5 mandate is explicit:
- "do not choose disputed tax, fee, currency, unit, or rounding values"
- "all financial rules remain draft, provisional, simulation-only, unpublished, and not runtime-selectable"
- "do not activate production payment"
- "no SRV-001-specific branches to generic payment controllers or engines"

Every prohibition maps to a construction-time invariant on a Domain VO. Callers cannot bypass a VO invariant.

الشرط=
- `Srv001FinancialOutcome::bindingOutcomes()` = `[QUOTED]` only.
- `FinancialRuleSelectionPolicy::isRuntimeSelectable()` returns true ONLY for `LIFECYCLE_PUBLISHED`.
- `FeeQuote::__construct()` throws when outcome=QUOTED and any of `unit`, `currency`, `roundingRule` is empty (OD-19 gate).
- `TaxQuote::__construct()` same invariant.
- `PaymentInitiationRequest::__construct()` throws when the inbound `FeeQuote::isBinding()` returns false.
- `DonationCampaignDecision::__construct()` throws when `mandatory=true` + `legalAuthorityReference` is null + `outcome=ACTIVE_MANDATORY`.
- `ReceiptIssuanceRequest::__construct()` throws when the inbound `PaymentConfirmationDecision::unlocksReceiptFlow()` is false.
- `PaymentCallbackReplayGuard::evaluate()` returns `REPLAY_DETECTED` on the second `(intent, signature)` pair.
- No `Providers` file changed. No `JeaServicesServiceProvider` bindings added.
- No `SRV-001` conditional in any controller/engine.

المانع=
Registering the financial rule policy + payment orchestration classes as container singletons would create runtime discoverability that no signed process ratifies. Colocating any of the invariants above with callers instead of construction would allow silent bypass. Introducing a "test-only" production-payment adapter would tempt future callers into using it as the default.

العلة=
Runtime safety + evidence integrity + supply-chain isolation. Financial rules touch legal artefacts (income tax, sales tax, exemptions) that no engineering test-pass can ratify. Payment callbacks are the highest-risk security surface — replay protection MUST live in code, not documentation. Receipts anchor legal evidence — a receipt bound to a rule version that later mutates would silently break audit trails.

القادح=
Any implementation that:
- adds a specific hardcoded fee / tax / percentage / cap / floor as a production default
- makes any lifecycle other than PUBLISHED selectable at runtime
- accepts a `PaymentInitiationRequest` construction with a simulation quote
- constructs a `ReceiptIssuanceRequest` from a non-CONFIRMED payment
- treats a REPLAY_DETECTED outcome as CONFIRMED
- adds `if ($service_code === 'SRV-001')` in a generic payment controller/engine
- publishes any financial `RuleVersion`

Would fire this قادح.

الصحة=
Valid implementation:

1. **`Srv001FinancialOutcome`** — 10-state enum. `bindingOutcomes()=[QUOTED]`.
2. **`FinancialRuleVersion`** — VO with 6 lifecycle states; `isPublished()` returns true only for `LIFECYCLE_PUBLISHED`.
3. **`FeeQuote` + `TaxQuote`** — carry every mandated field (rule version, source ref, source status, business approval, impl auth, publication auth, inputs, formula id, unit, currency, rounding, line items, tax lines, exemption evidence, blocking ODs, generated timestamp). Construction enforces OD-19 gate on QUOTED. `isBinding()` returns true only when outcome=QUOTED AND rule.isPublished().
4. **`ExemptionDecision`** — 5 kinds; 4 outcomes; `hasRuntimeFinancialEffect()` returns `false` for every outcome today (no financial rule is published, so no effect can land).
5. **`DonationCampaignDecision`** — construction-time rejection of `mandatory=true` + `ACTIVE_MANDATORY` + missing legal authority.
6. **`FinancialRuleSelectionPolicy`** — 3-line class enforcing the "PUBLISHED only" gate.
7. **`PaymentInitiationRequest`** — construction rejects non-binding quotes.
8. **`PaymentConfirmationDecision`** — 5 outcomes; `unlocksReceiptFlow()` returns true only for CONFIRMED.
9. **`ReceiptIssuanceRequest`** — construction rejects non-CONFIRMED payments; carries frozen `FeeQuote` + `TaxQuote` snapshots.
10. **`FinancialCorrectionRequest`** — VO with 4 statuses; defaults DRAFT.
11. **`PaymentCallbackReplayGuard`** — deterministic in-memory guard; production impl backs the `(intent, signature)` uniqueness with a DB unique constraint when the real adapter lands.

الفساد=
Providing a "fake" production payment gateway class with hardcoded successful responses would be fasid — encourages the pattern to spread. Not attempted.

البطلان=
Selecting any tax percentage, unit, or currency value while OD-01/OD-10/OD-19/OD-35 remain unresolved would be batil — every subsequent receipt would carry unratified financial evidence.

الأثر=
- 12 new source files:
  * Domain/Financial/ValueObjects: `Srv001FinancialOutcome`, `FinancialRuleVersion`, `FeeQuote`, `TaxQuote`, `ExemptionDecision`, `DonationCampaignDecision`
  * Domain/Financial/Policies: `FinancialRuleSelectionPolicy`
  * Domain/Payment/ValueObjects: `PaymentInitiationRequest`, `PaymentConfirmationDecision`, `ReceiptIssuanceRequest`, `FinancialCorrectionRequest`
  * Domain/Payment/Policies: `PaymentCallbackReplayGuard`
- 2 new test files (25 focused tests / 62 assertions on SQLite).
- 0 modifications to existing source files.
- 0 new migrations.
- 0 changes to controllers / providers / seeders / workflow engine / legacy runtime path.

البقايا=
- **RES-TD08-01** (OPEN) — no financial rule is bound at runtime. Every FeeQuote/TaxQuote is constructed with a `FinancialRuleVersion` VO whose lifecycle prevents runtime selection. Closure: signed OD-01/OD-10/OD-19/OD-35 + published rule version + integration with a signed payment gateway contract.
- **RES-TD08-02** (OPEN) — no production payment adapter wired. `PaymentCallbackReplayGuard` is in-memory; production requires a DB-backed store + unique constraint on `(payment_intent_id, callback_signature)`. Closure: adapter + migration + integration test + contract test with the sandbox.
- **RES-TD08-03** (OPEN) — `ReceiptIssuanceRequest` is a VO. Persistence + rendering + serial-number allocation land when receipts + certificates go to production (TD-06 + TD-07 doctrine).
- **RES-TD08-04** (OPEN) — `FinancialCorrectionRequest` has no workflow consumer; the mandated distinction between "pre-payment return via PartialEditGrant" and "post-payment correction via FinancialCorrectionRequest" is documented but not enforced at runtime.

Carry-forward preserved: OD-18, OD-29, OD-30, OD-31, OD-32, OD-33, OD-34 (TD-07); BURA/Map adapters not implemented; PartialEditGrant audit persistence not proven; workflow runtime inactive; production certificate blocked.

التعارض=
None. Every prohibition honoured.

الجمع=
Reconciled. TD-08 delivers the versioned financial + payment structural foundation with construction-time invariants that prevent silent activation of disputed values.

الترجيح=
Tier-5 (runtime safety — no silent selection of unpublished financial rules) + Tier-4 (target architecture — versioned rules + fully-provenanced quotes) + Tier-3 (evidence integrity — receipts freeze snapshots) + supply-chain isolation (no vendor client in Domain).

التوقف=
STOPPED on:
- publishing any financial `RuleVersion`
- activating any production payment gateway
- selecting any disputed tax / fee / currency / unit / rounding value
- adding SRV-001 conditionals to generic payment controllers/engines
- pushing, tagging, merging, deploying

Continues on: structural VOs + policies + replay guard + tests.

READINESS_CLASSIFICATION=Compliant with TD-08 mandate. `TARGET_RUNTIME_STATUS=INACTIVE`, `WORKFLOW_RUNTIME_STATUS=INACTIVE`, `FINANCIAL_RULE_PUBLISHED=NO`, `PRODUCTION_PAYMENT_ACTIVE=NO`, `PRODUCTION_CERTIFICATE_STATUS=BLOCKED`.

CLOSURE_EVIDENCE=
- Focused TD-08 tests: **25/25 PASS / 62 assertions / 21 ms** on SQLite
- Domain + Governance on Postgres 15-alpine: **81/81 PASS / 179 assertions / 3777 ms**
- Unit suite: **427/427 PASS / 1140 assertions** (+25 vs TD-07 baseline)
- Feature suite: **738/745 / 7 skipped / 2747 assertions** (unchanged — TD-08 is pure Unit)
- Architecture suite: **26/27 / 1 skipped / 1289 assertions** (unchanged; +48 assertions from new architecture-adjacent unit checks)
- PHPStan: **0 errors**
- Postgres data integrity: only `migrations` populated (54 rows, unchanged)
