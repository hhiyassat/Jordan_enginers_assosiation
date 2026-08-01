JUDGMENT_ID=JDG-SG03-03
TITLE=Legacy unversioned applications — migration policy
SCOPE=architecture/service-governance/SG-03
OWNER=service-governance-remediation

الوضع=
Existing applications reference `service_definition_id` but have no version binding. SG-03 adds a nullable FK. If SG-03 back-fills existing rows with "the latest published version", it silently attaches those rows to a version that may or may not represent the schema they were submitted under.

تحرير_محل_النزاع=
Should existing applications be (A) back-filled to a synthetic baseline version, (B) back-filled to the currently-published version, (C) left with NULL and explicitly classified `LEGACY_UNVERSIONED`, or (D) require per-application manual review?

السبب=
Program §Phase SG-03 explicitly forbids silent assignment: "Do not silently assign the latest version." Possible classifications named by the mandate: `LEGACY_UNVERSIONED`, `MIGRATED_TO_BASELINE_VERSION`, `REQUIRES_MANUAL_REVIEW`.

الشرط=
Policy must (i) not overwrite existing FK on any submitted row, (ii) preserve historical integrity — a LEGACY_UNVERSIONED application must display exactly the same behaviour it has today, (iii) allow explicit case-by-case migration for applications that ops determines are safe to attach to a baseline version, (iv) not require a database migration to reclassify (classification derivable from column state).

المانع=
Option (B) — auto-attach to latest — is explicitly forbidden.
Option (A) — synthetic baseline — would require materialising a "as-of-baseline" schema snapshot that no historical evidence supports; that snapshot would fabricate reproducibility that never existed.
Option (D) — per-application manual review — is impractical for hundreds of legacy applications and adds no governance value.

العلة=
Historical preservation + honest tracking. Applications submitted before versioning existed simply have no version binding — that is factually true and should be represented as such.

القادح=
None. Option (C) is honest and safe.

الصحة=
Valid policy: existing applications keep `service_definition_version_id=NULL`. A getter `Application::legacyVersioningClassification()` returns `LEGACY_UNVERSIONED` when the FK is null AND the row was created before the versioning feature landed (proxied by created_at vs migration date). Options for later manual attachment (per-application) remain open through an admin ledger entry (out of program scope).

الفساد=
An application that submitted post-versioning with a null FK because no published version existed at submit time is technically the same state as a legacy unversioned application. This is acceptable: both truly have no binding, and both are classified `LEGACY_UNVERSIONED`.

البطلان=
Silent back-fill (option B) is batil.

الأثر=
(1) Migration adds nullable FK without any back-fill logic. (2) `Application::legacyVersioningClassification()` returns the classification string. (3) UI / reports may filter or count LEGACY_UNVERSIONED rows to observe the transition-window progress.

البقايا=
RES-SG03-03: Per-application manual attachment procedure (with maker-checker + audit trail) if ops ever needs to attach a legacy application to a baseline version. Owner: ops. Not required for correctness.

التعارض=
None between the mandate and the chosen approach.

الجمع=
Not needed.

الترجيح=
Explicit program mandate + Tier-5 (historical integrity).

التوقف=
Not stopped.

EVIDENCE=
- Program §Phase SG-03 §Compatibility: "Do not silently assign the latest version."
- backend/modules/JeaServices/Models/Application.php (no version FK currently)

DECISION=
Existing applications keep `service_definition_version_id=NULL`. No back-fill. Classification `LEGACY_UNVERSIONED` returned by the model when the FK is null. Manual per-application attachment path deferred to RES-SG03-03.

IMPLEMENTATION_EFFECT=
Migration is strictly additive — no UPDATE statements against existing rows. Application model gains classification accessor.

MIGRATION_EFFECT=
Zero data mutation on existing rows.

TEST_EVIDENCE=
Tests: freshly-created application without published version returns LEGACY_UNVERSIONED; application bound at submit returns BOUND; classification never spontaneously changes.

OPEN_RESIDUALS=
- RES-SG03-03 (manual attachment procedure).
