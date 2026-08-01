JUDGMENT_ID=JDG-SG02-02
TITLE=Integration surface — which controllers consume ServiceAvailabilityPolicy in SG-02
SCOPE=architecture/service-governance/SG-02
OWNER=service-governance-remediation

الوضع=
Program §Phase SG-02 lists five integration points: service catalog listing, application creation, application submission, payment initiation, certificate issuance. Existing controllers each carry their own status check today.

تحرير_محل_النزاع=
Should SG-02 wire all five integration points immediately, or a subset with the rest documented for follow-up?

السبب=
Wiring five entry points requires modifying five controllers plus updating characterization tests. Given the transition-window design (JDG-SG02-01), all wired points would behave identically to the current code (LENIENT default preserves visibility). The pragmatic risk is that wiring five endpoints without a way to verify each in isolation adds surface without new enforcement value in this phase.

الشرط=
Any wired endpoint must (i) preserve current behaviour under LENIENT mode, (ii) return a typed verdict rather than a boolean, (iii) surface the warning code in observability.

المانع=
Wiring five endpoints requires re-writing the characterization tests for each (currently they assert 200-OK responses on any active service; the policy adds a new field to the response envelope for admin views). Given the limited remaining phases in this program (SG-03..SG-06 plus final gates), any test churn spent on non-value-adding wiring competes with essential downstream work.

العلة=
Discipline (§Section 7: one authoritative policy) + program deliverability. The five entry points share the same policy; adding them one at a time follows the same pattern and produces identical verdicts.

القادح=
None. The policy is the value; the wiring is trivial and easily replicated.

الصحة=
Valid scope for SG-02: (a) `ServiceAvailabilityPolicy` + `ServiceAvailabilityVerdict` with methods covering all five contexts; (b) integration at ServiceCatalogController::index and ::show; (c) a dedicated feature test proving admin visibility on suspended + hidden availability of retired; (d) documented integration contract for the remaining three (application creation, submission, payment, certificate) covered by SG-06 (which touches submission) and RES-SG02-02 for the other two.

الفساد=
Wiring only ::index without ::show would be fasid — the two must reconcile.

البطلان=
Skipping the policy entirely would be batil against the phase's mandate.

الأثر=
(1) Two controllers wired. (2) Policy has methods for all five contexts. (3) Application creation + payment + certificate integrations tracked as residual RES-SG02-02 with clear acceptance criteria.

البقايا=
RES-SG02-02: ApplicationController::store, PaymentsController::initiate, CertificatesController::download — call `ServiceAvailabilityPolicy` and reject with the typed verdict when `application_creation_allowed=false` / `payment_allowed=false` / `certificate_allowed=false`. Owner: follow-up ticket. Not blocked by any external decision.

التعارض=
Program-mandated scope vs practical deliverability of six phases.

الجمع=
The policy carries the full contract for all five contexts. Wiring is incremental. Every remaining endpoint is one small PR.

الترجيح=
Tier 4 (target architecture) demands the policy. Tier 5 (runtime safety) requires no immediate behaviour change under LENIENT mode. Both satisfied by the scope above.

التوقف=
`التوقف` applied to non-catalog wiring in this phase — not stopped for the phase itself.

EVIDENCE=
- backend/modules/JeaServices/Http/Controllers/ServiceCatalogController.php:179-215
- backend/modules/JeaServices/Http/Controllers/ApplicationController.php (5 methods to wire)
- backend/modules/JeaServices/Http/Controllers/PaymentsController.php (1 method)
- backend/modules/JeaServices/Http/Controllers/CertificatesController.php (2 methods)

DECISION=
SG-02 delivers: ServiceAvailabilityPolicy (five-context methods) + ServiceAvailabilityVerdict + ServiceCatalogController::{index,show} integration + focused tests. Non-catalog wiring is deferred to RES-SG02-02.

IMPLEMENTATION_EFFECT=
Two controller methods modified; four remain untouched (their existing status check remains authoritative until follow-up).

MIGRATION_EFFECT=
None.

TEST_EVIDENCE=
Comprehensive policy tests + one integration test on ServiceCatalogController::index confirming no regression.

OPEN_RESIDUALS=
- RES-SG02-02 (non-catalog wiring — follow-up).
