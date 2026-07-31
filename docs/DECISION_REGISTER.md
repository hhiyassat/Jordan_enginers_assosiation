# ESP v2 — Business-Decision Register

**Document ID:** ESP-DR-001
**Status:** ACTIVE
**Owner:** Product / JEA stakeholder committee
**Related:** `docs/remediation/architecture-review-remediation-ledger.md` (BLK-06)

---

## Purpose

The 2026-07-30 architecture review found `REQUIREMENTS.md` clauses that
describe features the code does not implement, plus features the code
implements that are not in `REQUIREMENTS.md`. Each such gap is EITHER:

  - a documentation error (update REQUIREMENTS to reflect reality), OR
  - a missing feature (build it), OR
  - a business decision that has not been made.

The remediation batches CAN and SHOULD close (a) and (b) autonomously.
Category (c) is what this file exists for — every item is explicitly
labelled so future readers can see exactly what remains open and why.

Each row is one of:

```
IMPLEMENTED               feature exists in code, matches REQUIREMENTS
DOCUMENTATION_STALE       code is right, REQUIREMENTS is wrong; update REQUIREMENTS
IMPLEMENTATION_MISSING    REQUIREMENTS is right, feature does not exist; build it
BUSINESS_DECISION_REQUIRED  neither is right until a stakeholder decides direction
EXTERNAL_DEPENDENCY       resolution needs external input (vendor, gov'ment agency)
```

## Register

| ID | Topic | Status | Current implemented behavior | REQUIREMENTS.md claim | Decision required |
|---|---|---|---|---|---|
| DR-01 | Applicant authentication | `BUSINESS_DECISION_REQUIRED` | Email + password (Sanctum bearer + `esp_session` httpOnly cookie), min-12 policy (P1-08), HIBP-optional. | NFR-007: "Authentication for applicants is OTP-only (SMS); no username/password login for applicant role". | Keep password auth (drop NFR-007) OR build SMS OTP for applicants (~4 weeks, needs SMS provider). |
| DR-02 | Upload formats | `BUSINESS_DECISION_REQUIRED` | PDF + DWG only, magic-byte-verified (`PdfOrDwgFile` rule), 50 MB cap. | FR-018: "Document upload slots accept MP4 video files in addition to PDF/JPG/PNG". | Add MP4/JPG/PNG (needs magic-byte rules + malware-scan integration) OR narrow REQUIREMENTS to pdf/dwg (matches current use case for JEA engineering drawings). |
| DR-03 | Draft autosave | `BUSINESS_DECISION_REQUIRED` | Explicit `PUT /applications/{id}` on Save Draft click; drafts persisted in `applications` table. | NFR-009: "Draft form data is autosaved to server-side cache on each field change". | Ship autosave (needs debounce + conflict resolution + cache backend) OR clarify that "save on click" is intended UX. |
| DR-04 | Reference-number format | `DOCUMENTATION_STALE` | `YY(2) + SVC(4, service_id % 10000) + SEQ(4)` (H-02 atomic counter). | NFR-008: "Reference format `{YY}{ServiceCode:4}{Seq:4}` — 10 digits, no separators". | REQUIREMENTS matches code. Row keeps here as a historical audit trail — no action needed. |
| DR-05 | Payment gateway | `EXTERNAL_DEPENDENCY` (BLK-01) | `MockPaymentGateway` bound in non-production; ProductionSafety aborts prod boot if Mock resolves. `PaymentIntent` DTO in place; `PaymentGateway` interface stable. | REQUIREMENTS names eFAWATEERcom in one location, JoMoPay in another. | Which provider? Once decided, implement one PaymentGateway class + bind in production ServiceProvider. |
| DR-06 | JEA membership verification endpoint | `EXTERNAL_DEPENDENCY` (BLK-02) | `FakeJeaMembershipVerifier` in non-production; `HttpJeaMembershipVerifier` skeleton with configurable auth scheme + retries; ProductionSafety guards. | REQUIREMENTS assumes a JEA API exists; endpoint URL + auth are not documented. | JEA to provide endpoint URL + auth spec + response schema. Then `HttpJeaMembershipVerifier` mapping updated in one place. |
| DR-07 | Nashmi integration secret + IP allowlist | `EXTERNAL_DEPENDENCY` (BLK-03) | HMAC + timestamp + nonce + IP-allowlist middleware live (H-04); production requires `NASHMI_SIGNING_SECRET` + `NASHMI_ALLOWED_IPS`. | REQUIREMENTS doesn't mention rotation policy. | Nashmi ops must issue the shared secret + publish rotation cadence. |
| DR-08 | GSB IP allowlist | `EXTERNAL_DEPENDENCY` (BLK-04) | `GsbIpWhitelist` middleware fails closed in production when empty (H-05). | REQUIREMENTS assumes MODEE Annex 4.15 §4.5 rule 11 IP list. | MODEE / GSB operations team to publish authorized caller IPs. |
| DR-09 | PostgreSQL CI matrix | `IMPLEMENTED` (C-05) | `backend-postgres` job in `.github/workflows/ci.yml` runs the full suite against Postgres 15 (session 2). Session 3 executed the suite locally against a Docker Postgres 15 — 864 tests / 863 pass / 1 skipped / 2890 assertions. | — | none. |
| DR-10 | Superuser scope | `IMPLEMENTED` (C-01) | Superuser is user-management only (routes + model helpers + tests). | REQUIREMENTS/AUTH implies role-tier boundaries. | none. |

## How to close a `BUSINESS_DECISION_REQUIRED` row

1. A named stakeholder writes their decision under the row (add a
   dated bullet), e.g.:

   ```
   - **2026-08-XX** — [stakeholder]: keep password auth; drop NFR-007.
   ```

2. If the decision is "drop the REQUIREMENTS clause", update
   `REQUIREMENTS.md` in the same commit and mark the row `IMPLEMENTED`
   here.

3. If the decision is "build the feature", open a new remediation
   ticket / P-tier entry and mark the row `IMPLEMENTATION_MISSING`
   here; the row moves to `IMPLEMENTED` when the feature ships.

## How to close an `EXTERNAL_DEPENDENCY` row

Rows in this state block on someone outside this repo (payment vendor,
JEA IT, MODEE ops, etc.). When the external artefact arrives:

1. Update the row with the source (email, doc reference, ticket).
2. Wire it into the code — most rows have a corresponding
   `BLK-*` entry in the remediation ledger with the exact activation
   steps.
3. Flip the row to `IMPLEMENTED` and remove the corresponding `BLK-*`
   marker from the ledger.
