JUDGMENT_ID=JDG-TD06-01
TITLE=SRV-001 document + partial-edit-grant foundations (OD-24 blocked, no production storage/AV claim)
OWNER=TD-06
PHASE=TD-06 (Batch 3 · document + partial-edit foundations — mandatory final stop after this phase)

الوضع=
TD-05 (`389342f`) delivered the fail-closed external-port boundary. TD-06 must complete Batch 3 with document metadata + versioning + contract-locking + quarantine + PartialEditGrant foundations — while OD-24 (attachment limits) remains unresolved, no production storage/AV integration exists, and TARGET_RUNTIME_STATUS must stay INACTIVE.

تحرير_محل_النزاع=
1. **How does OD-24 (unresolved attachment limits) surface in code?** Three options: hardcode a "temporary" 4 MB / 500 MB default; leave the limit field null and let downstream code coerce; or return a typed CONFIGURATION_BLOCKED decision until a signed configuration lands.
2. **Where does file-bytes containment live?** Inside the storage adapter only, or reinforced by a Domain-layer constraint that refuses to construct a metadata VO with a bytes-shaped property.
3. **How narrow is the signed-contract lock?** Only a small documented field list, or a broader legal-edit matrix that anticipates future rules.

السبب=
The Batch 3 mandate is explicit:
- "HARDCODED_UNAPPROVED_LIMITS=NO"
- "Do not silently adopt 500 MB or 4 MB as approved runtime limits"
- "Do not claim a control is operational merely because an interface exists"
- "Do not invent a broader legal edit matrix"
- "Domain entities must not contain file bytes"

الشرط=
- No hardcoded attachment limits in production code. Callers asking for a limit when configuration is unpublished must receive a typed CONFIGURATION_BLOCKED decision.
- Document metadata VO never carries file bytes.
- Signed-contract lock covers only the documented narrow field set.
- Grant enforcement composes with the signed-contract lock: a valid grant does NOT unlock a legally-protected field.
- No production storage / AV claim without adapter evidence.
- No OD-18 / OD-24 / OD-29 / OD-31 / OD-32 / OD-33 / OD-34 activation.

المانع=
Hardcoding 4 MB "for now" would fire the قادح. Making SignedContractLockPolicy return a broader field set to "anticipate" future rules would fire the قادح. Registering a production storage adapter as the default would fire the قادح.

العلة=
Runtime safety + evidence integrity. Attachment limits are legal/policy artefacts — inventing them silently would create false compliance evidence. File-bytes in Domain would introduce injection surface + memory-usage bloat. A broader legal-edit matrix would pre-approve edits that no signed policy authorises.

القادح=
Any implementation that:
- adds a constant `524_288_000` or `4_194_304` to production Documents-domain code
- declares a `$fileBytes` / `$rawContent` / `$base64Payload` property on a Domain VO
- imports `Aws\` / `Google\Cloud\Storage\` / `ClamAV\` / `VirusTotal\` in Domain\Documents
- extends the signed-contract lock's field list beyond the five documented fields
- claims a control is production-verified without executed adapter evidence

Would fire this قادح.

الصحة=
Valid implementation:

1. **`DocumentCategory` enum** — the 13 mandated categories (signed contract; land registration; title deed qushan; zoning; land-related evidence; relationship proof; exemption evidence; justification letter; borehole image; general-site image; optional additional media; technical report; clearance letter). `locksApplicationFields()` returns `[SIGNED_CONTRACT]`.
2. **`DocumentMetadata` VO** — carries every listed metadata field, NEVER file bytes. Construction-time guards reject unknown categories, negative sizes, malformed checksums (must be 64-hex-char SHA-256), unknown enum states. `withReplacementVersion()` returns a new VO with `documentVersion+1` and `supersededDocumentId` set — SG-04 immutability preserved.
3. **`PartialEditGrant` VO** — carries grantId, applicationId, granting actor + role, reason, permitted sections + fields, issue + expiry timestamps, state (ACTIVE / CONSUMED / EXPIRED / REVOKED), singleUse flag. `isUsable()` returns true only when state=ACTIVE and expiry has not passed.
4. **`AttachmentLimitPolicy` + `AttachmentLimitDecision`** — pure resolver. Returns `configurationBlocked(['OD-24_UNRESOLVED'])` when no signed configuration exists. Returns `allowed($bytes)` only when the caller has explicitly registered a published limit via `withPublishedLimit()`. Never coerces to a default.
5. **`SignedContractLockPolicy`** — narrow, documented field list: `contract_owner_name, contract_party_type, contract_signed_at, tax_number, national_number`. `isLocked($docs)` returns true iff any document is `category=SIGNED_CONTRACT` AND `validationStatus=VALIDATION_ACCEPTED`.
6. **`PartialEditGrantEnforcementPolicy`** — composes with lock policy. Deny order: `REVOKED → CONSUMED → EXPIRED → expiry-passed → out-of-scope → legal-lock-active → allowed`. Even a valid grant CANNOT unlock a legally-protected field.
7. **Ports (Domain/Documents/Contracts/)**: `ObjectStoragePort`, `ChecksumCalculatorPort`, `MimeValidatorPort`, `MalwareScannerPort` (+ immutable `MalwareScanResult` value with `clean()` / `infected()` / `unknown()` factories — `isClean()` returns true ONLY for CLEAN), `QuarantinePort`. No production adapters.
8. **Architecture test `DocumentsDomainBoundariesTest`** — enforces: no `$fileBytes`/`$rawContent`/`$base64Payload` in any Domain VO; no vendor storage/AV client imports in Domain\Documents; no hardcoded 500 MB or 4 MB constants; TargetSrv001SubmissionPolicy still unbound.

Control axis reported (CONTROL_MODELLED / ADAPTER_IMPLEMENTED / ADAPTER_TESTED / PRODUCTION_CONFIGURED / PRODUCTION_VERIFIED) per port:

| Port | Modelled | Impl (fake) | Tested | Prod config | Prod verified |
|---|---|---|---|---|---|
| ObjectStoragePort         | YES | NO | NO | NO | NO |
| ChecksumCalculatorPort    | YES | NO | NO | NO | NO |
| MimeValidatorPort         | YES | NO | NO | NO | NO |
| MalwareScannerPort        | YES | NO (contract enforced by unit test) | NO | NO | NO |
| QuarantinePort            | YES | NO | NO | NO | NO |

الفساد=
Providing an in-memory storage adapter that ONLY writes to a temp path (no signed access, no size enforcement) would be fasid — technically works locally but must not be defaulted in production.

البطلان=
Silently defaulting attachment limits to any specific byte count while OD-24 is unresolved would be batil — the limit becomes de-facto policy without any signed authorisation.

الأثر=
- 12 new source files (5 VOs + 5 port interfaces + MalwareScanResult + 2 enforcement policies).
- 3 new test files (34 focused tests / 262 assertions on SQLite; +1 architecture file with 4 tests).
- 0 new migrations (structural VOs only).
- 0 modifications to existing source files.
- 0 changes to controllers / providers / seeders.
- 0 changes to legacy runtime path.

البقايا=
- **RES-TD06-01** (OPEN) — no production storage / AV adapter wired. Every port has interface only. Per-provider closure requires: signed integration contract + adapter + contract test + production config + production verification (the full 5-step axis).
- **RES-TD06-02** (OPEN) — OD-24 unresolved. `AttachmentLimitPolicy` returns CONFIGURATION_BLOCKED for every category today. Closure: signed per-category limits registered via `withPublishedLimit()` + integration test proving the limit is enforced at upload.
- **RES-TD06-03** (OPEN) — `PartialEditGrantEnforcementPolicy` is a Domain policy; no runtime consumer wired. When TD-06+ adds a partial-edit controller endpoint, it must resolve the policy from the container and enforce it before any application-data mutation.
- **RES-TD06-04** (OPEN) — `DocumentMetadata` is a VO. Migration + Eloquent model land when TD-06+ needs persistence (the existing `documents` table already has some columns; a migration to align with this VO shape is out of TD-06 scope).

التعارض=
None. Every mandate prohibition is honoured.

الجمع=
Reconciled. TD-06 delivers the structural foundation for document handling + partial-edit grants without inventing limits, activating storage/AV, or extending the signed-contract lock.

الترجيح=
Tier-5 (runtime safety — no fabricated limits) + Tier-4 (target architecture — document boundary) + Tier-3 (supply-chain isolation — no vendor client imports in Domain) all support the chosen design.

التوقف=
STOPPED on:
- promoting any target `RuleVersion`
- activating any storage / AV / quarantine adapter
- inventing attachment limits
- extending the signed-contract lock beyond the documented five fields
- pushing, tagging, merging, deploying, publishing

Continues on: honest architectural build for the Documents domain + partial-edit-grants — pure structural code, port interfaces, and pure decision functions.

READINESS_CLASSIFICATION=Compliant with TD-06 mandate. TARGET_RUNTIME_STATUS=INACTIVE maintained. Production storage / AV / quarantine: all UNCLAIMED. OD-24 unresolved — attachment limits remain CONFIGURATION_BLOCKED.

CLOSURE_EVIDENCE=
- Focused TD-06 tests: **34/34 PASS / 262 assertions / 32 ms** on SQLite (16 metadata + 12 grant + 2 signed-contract lock + 4 architecture)
- All Documents + Srv001 + Governance suites on Postgres 15-alpine: **155/155 PASS / 614 assertions / 4429 ms**
- Unit suite: **374/374 PASS / 938 assertions** (+30 vs TD-05 baseline)
- Feature suite: **738/745 / 7 skipped / 2747 assertions** (unchanged — TD-06 is pure Unit / Architecture)
- Architecture suite: **26/27 / 1 skipped / 1081 assertions** (+4 vs TD-05)
- PHPStan: **0 errors**
- Postgres data integrity: only `migrations` populated (54 rows, unchanged)
