# ESP v2 — Eqratech Services Platform

A generic, schema-driven e-government services platform built by **Eqratech**. One JSON schema file defines a complete e-service — form fields, workflow stages, fee rules, document requirements, and certificate configuration — with no additional code beyond the schema.

**Stack:** Laravel 12 · PHP 8.2+ · MySQL 8 · React 18 · TypeScript · Tailwind CSS v3  
**Auth:** Laravel Sanctum bearer tokens  
**Methodology:** Eqratech IEEE-Aligned Decision Assurance Methodology v1.1

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Key Concepts](#key-concepts)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [API Reference](#api-reference)
- [Roles & Permissions](#roles--permissions)
- [Schema Format](#schema-format)
- [AI Schema Generation](#ai-schema-generation)
- [Hukm Governance Engine](#hukm-governance-engine)
- [GSB Integration](#gsb-integration)
- [Nashmi Integration](#nashmi-integration)
- [Security Controls](#security-controls)
- [MCP Servers](#mcp-servers)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    React 18 Frontend                     │
│         (TypeScript · Tailwind · Vite · port 5173)      │
└────────────────────────┬────────────────────────────────┘
                         │ Bearer Token (Sanctum)
┌────────────────────────▼────────────────────────────────┐
│                 Laravel 12 Backend API                   │
│                    /api/v1/*  (port 8002)                │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐ │
│  │  WorkflowEngine│  │SchemaValidator│  │ FeeCalculator │ │
│  │  (EDA B-1–11) │  │ (field rules) │  │ (schema-driven│ │
│  └──────────────┘  └──────────────┘  └───────────────┘ │
│                                                          │
│  ┌─────────────────────────────────────────────────────┐ │
│  │           Hukm Governance Engine                    │ │
│  │  Claude API → RequirementHukmIR → Schema Verdict   │ │
│  │  (sahih / fasid / batil)                            │ │
│  └─────────────────────────────────────────────────────┘ │
└──────────┬──────────────────┬───────────────────────────┘
           │                  │
    ┌──────▼──────┐   ┌───────▼──────┐
    │  MySQL 8    │   │  GSB / Nashmi │
    │  (SQLite    │   │  Integration  │
    │   for dev)  │   └──────────────┘
    └─────────────┘
```

The platform is **schema-first**: a `ServiceDefinition` record holds a JSON schema that drives everything — the applicant form, document checklist, fee calculation, review workflow, and certificate generation. Adding a new government service requires only a new schema record, no code changes.

---

## Key Concepts

### Schema-Driven Engine

Every active service is defined by a single JSON document stored in `service_definitions.schema`. The engine reads it at runtime to:

- Render the dynamic applicant form (`DynamicForm.tsx`)
- Validate submitted data field-by-field (`SchemaValidator`)
- Calculate the applicable fee (`FeeCalculator`)
- Route the application through review stages (`WorkflowEngine`)
- Generate the final certificate

### EDA Decision Chain

All state mutations in `WorkflowEngine` satisfy the 10-element Eqratech Decision Assurance (EDA) chain (Appendix B of the methodology). Key enforcement points:

| Element | Enforcement |
|---------|-------------|
| B-1 Origin | `AuditLog::record()` inside every DB transaction |
| B-2 Legitimate Branch | `CheckRole` middleware + organization-scoped queries |
| B-5 Critical Difference | `ALLOWED_TRANSITIONS` constant — single enforcement point |
| B-9 Effect Recorded | Every transition writes `audit_logs` with full state snapshot |
| B-10 Residual Outcomes | Correctable Defects return 422 with field-level errors; never silently dropped |

### Hukm Traceability

Every node in a generated schema (field, workflow stage, document, fee, certificate) carries a `requirement_source` object linking it to an exact verbatim quote from the SRS. Schemas without this traceability are classified **batil** (void) and blocked from activation.

---

## Project Structure

```
esp-v2/
├── backend/                    # Laravel 12 API
│   ├── app/
│   │   ├── Console/Commands/   # Artisan commands (GSB log pruning, etc.)
│   │   ├── Engine/
│   │   │   ├── FeeCalculator.php        # Schema-driven fee calculation
│   │   │   ├── SchemaValidator.php      # Field + document validation
│   │   │   ├── SchemaStructureValidator.php  # Hukm structural checks
│   │   │   └── WorkflowEngine.php       # EDA-compliant state machine
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── AdminController.php       # Dashboard, users, AI schema gen
│   │   │   │   ├── ApplicationController.php # Full application lifecycle
│   │   │   │   ├── AuthController.php        # Login, register, password
│   │   │   │   ├── GsbController.php         # GSB OTP + citizen lookup
│   │   │   │   ├── IntegrationController.php # Nashmi webhook receiver
│   │   │   │   └── ServiceCatalogController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── CheckRole.php             # Role-based access control
│   │   │   │   ├── EnforcePasswordPolicy.php # Password expiry enforcement
│   │   │   │   ├── GsbIpWhitelist.php        # MODEE §4.5 rule 11
│   │   │   │   ├── LogApiAccess.php          # Structured JSON access log
│   │   │   │   ├── SecurityHeaders.php       # HSTS, CSP, X-Frame-Options
│   │   │   │   ├── TokenInactivityCheck.php  # Session timeout enforcement
│   │   │   │   └── ValidateIntegrationKey.php
│   │   │   └── Requests/                     # FormRequest validation classes
│   │   ├── Models/
│   │   │   ├── Application.php      # Status machine + scopes
│   │   │   ├── ApplicationDocument.php
│   │   │   ├── AuditLog.php         # Immutable audit trail
│   │   │   ├── Certificate.php
│   │   │   ├── ServiceDefinition.php # Schema host
│   │   │   └── User.php             # Roles: applicant/staff/auditor/admin
│   │   └── Services/
│   │       ├── Gsb/GsbClient.php        # MODEE Annex 4.15 GSB client
│   │       └── NashmiIntegrationService.php
│   ├── database/
│   │   ├── migrations/          # 10 migrations (ordered 000001–000010)
│   │   └── seeders/
│   │       ├── DemoSeeder.php   # Demo org + 4 demo users
│   │       └── JeaServicesSeeder.php
│   └── routes/api.php           # Versioned API routes (v1)
│
├── frontend/                   # React 18 + TypeScript SPA
│   └── src/
│       ├── App.tsx              # Auth context, routing, role guards
│       ├── api/client.ts        # Typed API client (all endpoints)
│       ├── engine/
│       │   ├── DynamicForm.tsx  # Schema-driven form renderer
│       │   └── DocumentUploader.tsx
│       ├── pages/
│       │   ├── admin/           # Dashboard, service management, AI schema gen
│       │   ├── applicant/       # Service list, Apply wizard, My Applications
│       │   └── reviewer/        # Review queue, review panel
│       └── types/index.ts       # Shared TypeScript types
│
├── mcp/                        # Model Context Protocol servers (Node.js/ESM)
│   └── servers/
│       ├── schema-generator.js  # AI schema generation tool
│       ├── compliance.js        # Hukm compliance checker
│       ├── nashmi-generator.js  # Nashmi cycle management
│       ├── orchestration.js     # Cross-server orchestration
│       ├── quality.js           # Code quality analysis
│       ├── sdlc-security.js     # SDLC security controls
│       └── security.js          # Security audit tools
│
├── schemas/                    # Sample service schemas
│   └── business-license.json   # BL-001 رخصة تجارية
│
├── docs/
│   └── EDA_DECISION_CHAIN.md
├── BUILD_CONTRACT.md
├── REQUIREMENTS.md
└── METHODOLOGY_AUDIT.md
```

---

## Getting Started

### Prerequisites

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`
- Composer 2
- Node.js 20+
- MySQL 8 (or SQLite for development)

### Backend Setup

```bash
cd backend

cp .env.example .env
# Edit .env: set DB_*, ANTHROPIC_API_KEY, NASHMI_*, GSB_*

composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder

php artisan serve --port=8002
```

### Frontend Setup

```bash
cd frontend

cp .env.example .env
# VITE_API_BASE=http://localhost:8002

npm install
npm run dev
# Runs on http://localhost:5173
```

### Demo Accounts

All demo accounts use password `Demo1234!`

| Email | Role | Access |
|-------|------|--------|
| `admin@demo.esp` | admin | Full platform administration |
| `staff@demo.esp` | staff | Review queue, payment confirmation, certificates |
| `auditor@demo.esp` | auditor | Read-only review access |
| `ahmed@demo.esp` | applicant | Apply for services, track applications |

---

## API Reference

All endpoints are versioned under `/api/v1/`. Authentication uses `Authorization: Bearer <token>`.

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Login (returns bearer token) |
| POST | `/api/v1/auth/register` | Register new user |
| GET | `/api/v1/auth/me` | Current user profile |
| POST | `/api/v1/auth/logout` | Invalidate token |
| POST | `/api/v1/auth/password/change` | Change password |

### Service Catalog

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/services` | List active services (public catalog) |
| GET | `/api/v1/services/{code}` | Service detail + schema |
| GET | `/api/v1/admin/services` | All services including drafts (admin) |
| POST | `/api/v1/services` | Create service definition |
| PUT | `/api/v1/services/{id}` | Update service definition |
| PATCH | `/api/v1/services/{id}/status` | Activate / deactivate service |

### Applications

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/applications` | List applications (scoped by role) |
| POST | `/api/v1/applications` | Create draft application |
| GET | `/api/v1/applications/{id}` | Application detail |
| PUT | `/api/v1/applications/{id}` | Update draft data |
| POST | `/api/v1/applications/{id}/submit` | Submit for review |
| POST | `/api/v1/applications/{id}/documents` | Upload document |
| POST | `/api/v1/applications/{id}/claim` | Claim for review |
| POST | `/api/v1/applications/{id}/decide` | approve / reject / request_modifications |
| POST | `/api/v1/applications/{id}/confirm-payment` | Confirm payment |
| POST | `/api/v1/applications/{id}/issue-certificate` | Issue certificate |
| GET | `/api/v1/certificates/verify/{certNumber}` | Public certificate verification |

### AI Schema Generation (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/admin/services/generate-schema` | Generate schema from SRS text |
| POST | `/api/v1/admin/services/generate-schema-from-file` | Generate schema from DOCX/PDF/TXT upload |
| POST | `/api/v1/admin/services/chat-schema` | Natural-language schema editing |

### Admin

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/dashboard` | Aggregate statistics |
| GET | `/api/v1/admin/users` | User list |
| POST | `/api/v1/admin/users` | Create user |
| PUT | `/api/v1/admin/users/{id}` | Update user / role |
| GET | `/api/v1/admin/audit-logs` | Full audit trail |

### Nashmi Integration (X-Integration-Key auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/integration/receive-requirements` | Inbound requirements from Nashmi |
| POST | `/api/integration/receive-feedback` | Inbound review feedback |
| POST | `/api/integration/cycles/{id}/notify-done` | Notify Nashmi of completion |
| GET | `/api/integration/cycles` | List integration cycles |

---

## Roles & Permissions

| Action | applicant | staff | auditor | admin |
|--------|-----------|-------|---------|-------|
| Browse service catalog | ✅ | ✅ | ✅ | ✅ |
| Create / submit application | ✅ | ❌ | ❌ | ❌ |
| View own applications | ✅ | — | — | — |
| View all applications | ❌ | ✅ | ✅ | ✅ |
| Review queue | ❌ | ✅ | ✅ | ✅ |
| Claim + decide applications | ❌ | ✅ | ✅ | ✅ |
| Confirm payment | ❌ | ✅ | ❌ | ✅ |
| Issue certificate | ❌ | ✅ | ❌ | ✅ |
| Manage services + schema gen | ❌ | ❌ | ❌ | ✅ |
| Manage users + audit logs | ❌ | ❌ | ❌ | ✅ |

---

## Schema Format

A service schema is a JSON object with the following top-level keys. Every node must include `requirement_source` for Hukm traceability.

```json
{
  "service_code": "BL-001",
  "name_ar": "رخصة تجارية",
  "name_en": "Business License",
  "version": "1.0",
  "requirement_source": { "requirement_id": "REQ-SVC-001", "section": "SRS §1.1", "quote": "..." },

  "workflow": {
    "stages": [{
      "id": "initial_review",
      "label_ar": "المراجعة الأولية",
      "label_en": "Initial Review",
      "role": "staff",
      "sla_hours": 24,
      "actions": ["approve", "reject", "request_modifications"],
      "requirement_source": { ... }
    }]
  },

  "fee": {
    "type": "fixed",
    "amount": 150,
    "currency": "JOD",
    "requirement_source": { ... }
  },

  "sections": [
    { "id": "applicant_info", "label_ar": "بيانات مقدم الطلب", "label_en": "Applicant Info" }
  ],

  "fields": [{
    "id": "trade_name",
    "label_ar": "الاسم التجاري",
    "label_en": "Trade Name",
    "type": "text",
    "required": true,
    "section": "applicant_info",
    "min_length": 3,
    "max_length": 100,
    "requirement_source": { ... }
  }],

  "documents": [{
    "id": "commercial_register",
    "label_ar": "السجل التجاري",
    "label_en": "Commercial Register",
    "required": true,
    "accept": ["pdf", "jpg", "png"],
    "max_size_mb": 5,
    "acceptance_rule": "Clear scan showing registration number, entity name, and valid expiry date",
    "requirement_source": { ... }
  }],

  "certificate": {
    "validity_months": 12,
    "title_ar": "رخصة تجارية",
    "title_en": "Business License",
    "fields_on_cert": ["trade_name", "business_type"],
    "requirement_source": { ... }
  }
}
```

### Field Types

`text` · `textarea` · `select` · `radio` · `multiselect` · `checkbox_group` · `number` · `date` · `email`

### Workflow Stage Roles

Valid values: `staff` · `auditor` · `admin`

> **Note:** Stage IDs must represent processing steps performed by a human reviewer (`initial_review`, `compliance_check`, `final_approval`). Application status names (`submitted`, `approved`, `rejected`) are not valid stage IDs.

---

## AI Schema Generation

The admin panel provides two modes for generating schemas with Claude:

### From Text (SRS paste)
`POST /api/v1/admin/services/generate-schema`

Paste service requirements text; Claude extracts a fully traceable schema with requirement_source on every node.

### From Files (DOCX / PDF / TXT upload)
`POST /api/v1/admin/services/generate-schema-from-file`

Upload up to two files:
- **Functional SRS** (required) — main service requirements document
- **NFR document** (optional) — non-functional requirements, merged with the SRS automatically

### Generation Modes

| Mode | Arabic | Use Case |
|------|--------|----------|
| `azimah` | عزيمة — إنتاج | Production standard. All Hukm rules strictly enforced. Schema must achieve `sahih` verdict. |
| `rukhsa` | رخصة — نموذج أولي | Prototype mode. Schema generated even with issues; blockers reported but not enforced. |

### Natural Language Editing
`POST /api/v1/admin/services/chat-schema`

Send a message like "add a field for commercial registration number" and Claude updates the existing schema while preserving all requirement_source links.

---

## Hukm Governance Engine

Every generated schema is evaluated and classified:

| Verdict | Meaning | Effect |
|---------|---------|--------|
| `sahih` (صحيح) | Valid — all rules pass | Can be activated |
| `fasid` (فاسد) | Correctable defects | Warned; may be activatable in rukhsa mode |
| `batil` (باطل) | Void — non-waivable violations | Cannot be activated |

### Batil conditions (non-waivable)
- Any node missing `requirement_source`
- `requirement_source.quote` is not verbatim from the SRS
- Workflow stage using a forbidden ID (`submitted`, `approved`, etc.)
- Field referencing a non-existent section
- `select`/`radio` field with no options

### Fasid conditions (correctable)
- Missing `label_ar` or `label_en`
- Document missing `acceptance_rule`
- Fixed fee missing `currency`
- Workflow stage missing `actions`

---

## GSB Integration

The Government Service Bus integration follows **MODEE Annex 4.15** standards.

### OTP Flow (Citizen Data Access)

```
1. POST /api/v1/gsb/otp/request   { national_id }  → OTP sent to citizen
2. POST /api/v1/gsb/otp/verify    { national_id, otp } → otp_token (15 min)
3. GET  /api/v1/gsb/citizen       { otp_token }    → citizen data
```

### Security Controls
- **IP Whitelist** (`GsbIpWhitelist` middleware): Only pre-configured IPs may call GSB endpoints (MODEE §4.5 rule 11)
- **Rate limiting**: 100 req/min per IP (MODEE §4.7)
- **Audit logging**: Every GSB call written to `gsb_call_logs` with full request/response metadata
- **Log pruning**: Automated cleanup via `GsbPruneLogs` Artisan command

---

## Nashmi Integration

Nashmi is the Eqratech requirements management platform. ESP v2 receives structured requirements from Nashmi and sends back completion notifications.

### Inbound (Nashmi → ESP)
```
POST /api/integration/receive-requirements
X-Integration-Key: <configured key>

{
  "cycle_ref": "CYCLE-2026-001",
  "service_code": "BL-001",
  "requirements_meta": { ... }
}
```

### Outbound (ESP → Nashmi)
After schema generation and service activation:
```
POST /api/integration/cycles/{id}/notify-done
```

All inbound requirements are stored as `IntegrationCycle` records and available in the admin panel under **Nashmi** for review and schema generation trigger.

---

## Security Controls

| Control | Implementation |
|---------|---------------|
| Transport security | HSTS header (1 year), forced via `SecurityHeaders` middleware |
| Content Security Policy | `default-src 'none'` for API responses |
| Authentication | Sanctum bearer tokens; no cookie/session auth |
| Session timeout | `TokenInactivityCheck` enforces `SESSION_TIMEOUT_MINUTES` (default 30) |
| Password policy | `EnforcePasswordPolicy`: complexity + 90-day expiry |
| Role enforcement | `CheckRole` middleware on every protected route |
| Organization isolation | All queries scoped by `organization_id`; cross-org access impossible |
| Audit trail | `audit_logs` table; every state mutation recorded with user, timestamp, input snapshot |
| Sensitive field masking | `LogApiAccess` redacts passwords, OTP codes, tokens before writing to logs |
| File upload restrictions | Validated MIME types; 10MB max; stored in private disk (not publicly accessible) |
| Rate limiting | Login: 5/min; Registration: 10/min; GSB: 100/min |
| Clickjacking protection | `X-Frame-Options: DENY` |
| MIME sniffing | `X-Content-Type-Options: nosniff` |

---

## MCP Servers

Seven Model Context Protocol servers are provided for AI-assisted development workflows (Node.js/ESM, port 5050 area):

| Server | Purpose |
|--------|---------|
| `schema-generator.js` | AI schema generation from SRS text |
| `compliance.js` | Hukm compliance checking against the methodology |
| `nashmi-generator.js` | Integration cycle and requirements management |
| `orchestration.js` | Cross-server workflow orchestration |
| `quality.js` | Code quality and test coverage analysis |
| `sdlc-security.js` | SDLC security controls audit |
| `security.js` | Runtime security audit tools |

### Claude Desktop Configuration

See `mcp/claude_desktop_config_snippet.json` for the configuration block to add to your Claude Desktop `claude_desktop_config.json`.

```bash
# Install MCP dependencies
cd mcp && npm install

# Configure environment
cp .env.example .env
# Set ANTHROPIC_API_KEY, ESP_API_BASE, ESP_INTEGRATION_KEY
```

---

## Environment Variables

### Backend (`backend/.env`)

```env
APP_KEY=                        # Generated by artisan key:generate
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=esp_v2
DB_USERNAME=root
DB_PASSWORD=

ANTHROPIC_API_KEY=              # Required for AI schema generation
ANTHROPIC_MODEL=claude-opus-4-8 # Optional override

SESSION_TIMEOUT_MINUTES=30

NASHMI_BASE_URL=
NASHMI_API_KEY=
NASHMI_ORG_CODE=

GSB_BASE_URL=
GSB_CLIENT_ID=
GSB_CLIENT_SECRET=
GSB_ALLOWED_IPS=               # Comma-separated IP whitelist for GSB routes

INTEGRATION_KEY=               # X-Integration-Key for Nashmi webhook auth
```

### Frontend (`frontend/.env`)

```env
VITE_API_BASE=http://localhost:8002
```

---

## Build Contract Compliance

This project was built under a formal Build Contract (`BUILD_CONTRACT.md`) written before line one of code. Key constraints that must never be violated:

- **P-1**: Validation rules are never removed to unblock a flow
- **P-3**: No inline `$request->validate()` — FormRequest classes only
- **P-5**: All queries scoped by `organization_id`
- **WF-001**: State mutations go through `WorkflowEngine` only
- **EDA-10**: Validation failures return 422 with field errors — never silently stripped
- **SEC**: Only `applicant` role users may create applications; staff/admin/auditor are blocked at both frontend route guard and backend controller level

---

## License

Proprietary — Eqratech. All rights reserved.
