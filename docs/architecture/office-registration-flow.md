# Office Registration Flow (JEA-OFF-REG)

**Status:** MVP backend (this session); frontend + operational polish pending
**Scope:** public signup for engineering offices → JEA review → account provisioning
**Origin:** stakeholder request 2026-07-27 — new service to add offices to the platform

## Why a separate model, not the ServiceDefinition/Application pipeline

Every existing service (SRV-*, DRW-P-*) is filed by an EXISTING `applicant` User of an EXISTING `Organization`. The Application model requires both `organization_id` and `applicant_id` — non-nullable, cross-cutting scope-enforced by `BelongsToOrganization`.

Office registration is filed **before** either exists. Trying to shoehorn it into the Application model would require nullable org/applicant, org-scope bypass, and a fake service_definition — none of which are clean.

The registration flow gets its own model + endpoints. On approval, it *creates* the Organization + admin User + Engineers that the rest of the platform then uses.

## Flow

```
1. Anonymous visitor  → POST /api/v1/office-registrations  (public, no auth)
                        {office info + engineers[]}
                        → FormRequest validation (shape + uniqueness)
                        → OfficeRegistrationValidator (business rules)
                        → creates OfficeRegistrationRequest row, status='pending'
                        → 201 with reference number
                        (no side-effects on Organization/User yet)

2. JEA staff/admin    → GET  /api/v1/admin/office-registrations         (paginated list, pending first)
                     → GET  /api/v1/admin/office-registrations/{id}     (single record for review)
                     → POST /api/v1/admin/office-registrations/{id}/approve
                        → creates Organization (from office info)
                        → creates User (role=applicant, admin of the new org)
                        → creates Engineer rows (one per submitted engineer)
                        → sets request.status='approved'
                        → sets request.approved_organization_id + reviewed_by/at
                        → sends welcome email (deferred; noted for future step)
                     → POST /api/v1/admin/office-registrations/{id}/reject
                        → sets request.status='rejected' + review_notes
```

## Validation rules

Enforced by `OfficeRegistrationValidator`, called from `SubmitOfficeRegistrationRequest::withValidator()`:

| rule | source |
|---|---|
| At least 1 engineer submitted | stakeholder input |
| If exactly 1 engineer, its discipline (normalized) must be `structural` OR `architectural` | stakeholder input — "مدني او عمارة" |
| Each engineer's discipline must be in `Disciplines::all()` after normalization | data-model invariant |
| Each engineer's JEA membership number must verify against `JeaMembershipVerifier` | stakeholder input |
| Office name_ar + name_en globally unique across `organizations` + prior `office_registration_requests` in `pending`/`approved` | prevent duplicate submissions |
| Office license_no globally unique | prevent duplicate licenses |
| Office email valid + globally unique across `users` | login identity |

`Disciplines::normalize()` folds the alias `civil` → `structural` — so a submitter using either "civil" or "structural" resolves to the same canonical value and the single-engineer rule accepts both.

## JeaMembershipVerifier contract

External JEA API — not accessible in dev/demo. Interface + fake pattern:

```php
interface JeaMembershipVerifier
{
    public function verify(string $engineerName, string $membershipNumber): VerificationResult;
}
```

Two implementations bound in `JeaServicesServiceProvider::register()`:

- **`FakeJeaMembershipVerifier`** — default; returns `VerificationResult::valid()` for any name+number. Used in tests and demo (per stakeholder: "لغايت الديمو يقبل اي اسم اذا لم يتوفر api").
- **`NullJeaMembershipVerifier`** — throws `NotImplementedException`. Not bound by default; used to prove the container-swap works in tests.

Real HTTP implementation is a future step (adds `HttpJeaMembershipVerifier` with base URL + auth from config; container binding changes based on env).

## Rate limiting

Public endpoint is throttled at Laravel's `throttle:5,1` (5 requests per minute per IP) to prevent spam. Existing CAPTCHA middleware is currently login-only; extending it to this endpoint is a future enhancement.

## What this MVP does NOT do

- Frontend form (public signup page + admin review dashboard) — separate session
- Email verification for the office email
- Welcome email on approval
- File attachments on the registration (e.g. commercial register PDF)
- Multi-step form (all fields submitted at once)
- Update-registration flow (submissions are immutable after submit; JEA rejects if wrong)
- Real HTTP JeaMembershipVerifier
- Captcha on the public endpoint

Each of the above is a discrete follow-up. The backend surface here is small and focused so the frontend + polish can layer on cleanly.

## Impact on the canonical catalog (Batch 01 audit alignment)

This service is **not** in the audited workbook (`docs/JEA_screen_field_service_matrix_v2_audited_RTL_fixed.xlsx`). The legacy DB row `JEA-OFF-001` (marked `LEGACY_DEMO_NOT_CANONICAL` in `01_service_catalog.csv`) has the matching name but not this behavior.

Because office registration lives in its own model (`office_registration_requests`) rather than as a `ServiceDefinition`, it does NOT need to be added to `01_service_catalog.csv` or `ServicePlan2026Seeder`. It is a **platform-level onboarding capability**, not a service in the JEA-service-catalog sense.

The canonical map remains at 57 services. Office registration is orthogonal.
