JUDGMENT_ID=JDG-SG02-01
TITLE=ServiceAvailabilityPolicy — preference order between legacy `status` and new `publication_status`
SCOPE=architecture/service-governance/SG-02
OWNER=service-governance-remediation

الوضع=
After SG-01 the model carries two orthogonal availability signals: (a) legacy `status ∈ {active, inactive, draft}` filtered by ServiceCatalogController lines 180, 211; (b) new `publication_status ∈ {NOT_PUBLISHED, PUBLISHED, SUSPENDED, RETIRED}` default `NOT_PUBLISHED`. Every existing row has `publication_status = NOT_PUBLISHED` and every seeder writes `status = 'active'`. If SG-02 gated purely on `publication_status = PUBLISHED`, all 57 services would disappear from the catalog, breaking hundreds of tests and every current integration.

تحرير_محل_النزاع=
How should the runtime gate reconcile the two signals during the transition window before SG-03 versioning is in place?

السبب=
Program §Phase SG-02 mandates enforcing the lifecycle at every relevant runtime entry point. Program §14 permits `التوقف` only for the affected decision, not the phase.

الشرط=
Reconciliation must (i) preserve current runtime behaviour (57 services remain visible until UAT completes for each), (ii) block newly-published services with placeholder fee or template workflow (already enforced by SG-01 `ServicePublicationPolicy`), (iii) allow admin inspection of unavailable services, (iv) surface a governance warning where the transition-window fallback is being used so the follow-up work is visible, (v) allow historical applications on retired services to still be viewable.

المانع=
Strict-mode-by-default would break the 908 existing tests and every applicant workflow, exceeding the phase's scope.

العلة=
Historical preservation + backward compatibility during the transition, combined with future-safety: new publish attempts already pass through the strict SG-01 policy.

القادح=
None. The reconciliation is time-bounded: it closes when SG-03 introduces version binding and every legacy application is migrated.

الصحة=
Valid preference order (LENIENT default mode):
    1. publication_status = RETIRED       → hidden except for historical view
    2. publication_status = SUSPENDED     → hidden for applicants, visible for admin
    3. publication_status = PUBLISHED     → visible
    4. legacy status = 'active' + publication_status = NOT_PUBLISHED
                                          → visible with `AVAIL_LEGACY_STATUS_FALLBACK` warning code
    5. legacy status != 'active' + publication_status = NOT_PUBLISHED
                                          → hidden (unless admin inspecting)

الفساد=
Leaving the two signals coupled at every controller (each controller doing its own status check) would be fasid. Reconciliation MUST live in one policy.

البطلان=
Any attempt to strictly enforce publication_status = PUBLISHED as a hard gate in SG-02 without SG-03 versioning is batil for the current codebase — it would break historical applications' viewability.

الأثر=
(1) `ServiceAvailabilityPolicy::evaluate` returns a `ServiceAvailabilityVerdict` with six boolean flags + reason codes. (2) ServiceCatalogController::index filters via the policy. (3) Future SG-03 will replace rule 4 with binding-based availability.

البقايا=
RES-SG02-01: The `AVAIL_LEGACY_STATUS_FALLBACK` code should be counted in an ops dashboard so the transition window's progress is observable. Owner: ops. Out of program scope.

التعارض=
Legacy `status='active'` vs `publication_status='NOT_PUBLISHED'`. Same underlying artefact; different lifetimes of the code paths.

الجمع=
Reconcile via the ordered preference list above — both signals coexist with a bounded fallback.

الترجيح=
Tier 5 (runtime safety + historical integrity) requires the fallback for existing rows. Tier 4 (program's target architecture) demands the strict gate for newly-published rows — already enforced by SG-01.

التوقف=
Not stopped.

EVIDENCE=
- backend/modules/JeaServices/Http/Controllers/ServiceCatalogController.php:179-215 (current listing filter)
- backend/modules/JeaServices/Database/Migrations/2026_08_01_000010_add_lifecycle_governance_to_service_definitions.php (default 'NOT_PUBLISHED')
- backend/modules/JeaServices/Governance/ServicePublicationPolicy.php (already enforces strict publication)

DECISION=
Implement `ServiceAvailabilityPolicy` with LENIENT default mode using the preference order above. Strict mode reserved for a future phase when versioning + migration are complete. Emit `AVAIL_LEGACY_STATUS_FALLBACK` as a warning code (not a blocker) whenever the fallback rule 4 is used.

IMPLEMENTATION_EFFECT=
New classes: `ServiceAvailabilityPolicy`, `ServiceAvailabilityVerdict`. ServiceCatalogController::index consults the policy; the resulting list is identical to the current one for existing seeded services, plus emits the warning code for observability.

MIGRATION_EFFECT=
None. Behaviour is backward-compatible.

TEST_EVIDENCE=
Comprehensive unit tests on the policy (published / suspended / retired / legacy-fallback / not-visible / admin-bypass); one integration test on ServiceCatalogController::index proving no regression.

OPEN_RESIDUALS=
- RES-SG02-01 (ops dashboard for warning code — out of scope).
