JUDGMENT_ID=JDG-SG00-04
TITLE=Service Package Contract — correction on persistence and mutation
SCOPE=architecture/service-governance/SG-00
OWNER=service-governance-remediation

الوضع=
Prior handoff (docs/handoff/service-architecture/09-Proposed-Service-Package-Contract.md §2.8) states that a service submission guard "MAY mutate $app->data (derived values), MUST persist via $app->save() before returning". This reflects the current behaviour of Srv001Guard::validate, which writes derived values into `$app->data` and calls `$app->save()`. However, this pattern couples domain policy to Eloquent persistence, violates the target architecture rule (§7: "Service domain policy must not save Eloquent models"), and makes it impossible to distinguish a validation-only run (dry-run, preview) from a submission run.

تحرير_محل_النزاع=
Should the Service Package Contract endorse the current pattern (domain policy calls $app->save internally) or require domain policies to return typed decisions with derived values, leaving persistence to an application use case?

السبب=
SG-05 will define minimal extension contracts, and SG-06 will encapsulate the current SRV-001 pilot inside a legacy boundary. Both phases need a consistent rule about who persists.

الشرط=
The contract correction must (i) preserve the current SRV-001 externally observable behaviour (SG-06 characterization tests), (ii) allow submission use cases to orchestrate persistence, (iii) enable dry-run validation without side effects, (iv) enable calculation snapshots (SG-04) to be written atomically with the derived values.

المانع=
Requiring domain policies to save Eloquent models directly makes them impossible to unit-test in isolation, violates §7 dependency rules, and prevents outbox / transactional-boundary control from a single use-case orchestrator.

العلة=
Separation of concerns + testability + transactional atomicity. A calculation snapshot (SG-04) must be written in the same transaction as the derived-value write; if the guard is the only writer, the transaction boundary lives outside the guard's control.

القادح=
The current Srv001Guard behaviour is a legacy pattern preserved for pilot wiring. The contract document (an *aspirational* target) should not codify a pattern that will be refactored in SG-06.

الصحة=
Valid contract: domain policies (SubmissionPolicy, CalculationPolicy, EligibilityPolicy) accept typed input and return typed decisions. They MAY compute derived values, but those values ride out inside the decision object, not written to Eloquent models by the policy.

الفساد=
The current Srv001Guard is a fasid (defective-but-repairable) implementation. SG-06 will wrap it in a legacy adapter that preserves observable behaviour while migrating to the corrected contract.

البطلان=
Endorsing the current $app->save-inside-guard pattern in the contract document would produce a batil contract — invalid as a governance instrument even if it describes today's code.

الأثر=
(1) SG-00 corrects §2.8 of the prior contract. (2) SG-05 implements typed decision objects. (3) SG-06 introduces `LegacySrv001SubmissionPolicy` that returns a decision object; the use case orchestrates persistence. (4) SG-04's CalculationSnapshot writer becomes the sole persistence path for derived values.

البقايا=
RES-SG00-04: The current Srv001Guard implementation still calls $app->save inside itself. Owner: SG-06. Blocks: SG-06 completion. Closure: SG-06 characterization tests + refactor.

التعارض=
Prior handoff (09-...md §2.8) vs this program's target architecture (§5.4, §7). Prior contract is aspirational; this program's rules are prescriptive.

الجمع=
Reconcilable in phases: the current-code behaviour continues to work as-is until SG-06 refactors it. The contract itself must state the target rule.

الترجيح=
Evidence tier 4 (approved architecture constitution) — this program's target architecture rules — outrank tier 6 (current implementation).

التوقف=
Not stopped.

EVIDENCE=
- docs/handoff/service-architecture/09-Proposed-Service-Package-Contract.md §2.8 (endorses $app->save inside policy)
- backend/modules/JeaServices/Engine/Srv001Guard.php (current mutation-and-save pattern)
- This program §5.4, §7 (target architecture rules)

DECISION=
Correct §2.8 of the Service Package Contract as follows:

    Policies:
      - accept typed input (Application entity + form data + context)
      - return typed decision object with:
          - error array (field-id keyed)
          - derived values (name → value)
          - warnings
          - rule-version references (for calculators)
          - snapshot payload for CalculationSnapshot (SG-04)
      - MUST NOT call $app->save
      - MUST NOT mutate the passed Application entity's persistent state
      - MUST NOT dispatch jobs, emit events, or call HTTP transports

    Use cases:
      - orchestrate loading + policy invocation + persistence + snapshot creation + audit + workflow transition
      - own the transaction boundary
      - are the only writers to Application, application_documents, calculation_snapshots

IMPLEMENTATION_EFFECT=
SG-00-handoff-reconciliation.md includes the corrected §2.8 text. SG-05 defines the typed decision object. SG-06 refactors Srv001Guard behind an adapter.

MIGRATION_EFFECT=
Zero user-visible behaviour change (SG-06 characterization tests enforce this).

TEST_EVIDENCE=
Not applicable in SG-00. Enforced by SG-06 tests.

OPEN_RESIDUALS=
- RES-SG00-04 (Srv001Guard actual refactor — SG-06).
