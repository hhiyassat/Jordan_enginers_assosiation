/**
 * ESP v2 — Schema Generator MCP Server
 *
 * Converts an SRS document or plain-text service description into a
 * valid ESP v2 JSON schema using Claude AI.
 *
 * Tools:
 *   generate_service_schema  — SRS text → ESP v2 JSON schema (JEA NFR-compliant)
 *   generate_srs             — service concept → structured SRS document
 *   validate_schema          — check a schema for structural errors + JEA NFR compliance
 *   save_schema_to_esp       — POST the generated schema directly to the ESP backend
 *
 * Usage from Claude Desktop:
 *   "Generate a schema for engineer registration from this SRS: <text>"
 *   → Claude calls generate_service_schema → returns JEA-compliant JSON
 *   → Claude calls save_schema_to_esp to activate it immediately
 *
 * JEA NFR compliance (v1.1 — 2026-07-12 meeting):
 *   NFR-007: OTP-only auth (no password)
 *   NFR-008: Transaction number format {YY}{SSSS}{NNNN} — 10 digits, no separators
 *   NFR-009: Autosave to cache, flush on explicit submit
 *   NFR-010: S3 object storage backend
 *   FR-018:  MP4 video uploads supported
 *   FR-019:  Public tracking via reference number + OTP
 *   FR-020:  Admin initiates first workflow step
 *   INT-001: DLS identity provider for public tracking
 *   INT-002: SMS gateway for OTP delivery
 */

import { Server }               from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import Anthropic                from '@anthropic-ai/sdk';

// ── Config ────────────────────────────────────────────────────────────────────

const ANTHROPIC_API_KEY = process.env.ANTHROPIC_API_KEY ?? '';
const ESP_API_BASE      = process.env.ESP_API_BASE      ?? 'http://localhost:8002/api';
const ESP_ADMIN_TOKEN   = process.env.ESP_ADMIN_TOKEN   ?? '';
const MODEL             = process.env.SCHEMA_GEN_MODEL  ?? 'claude-opus-4-8';

// ── JEA Non-Functional Requirements — injected into every generation prompt ──
// Source: REQUIREMENTS.md v1.1 | JEA meeting 2026-07-12
// These are HARD constraints. Every generated schema must comply.

const JEA_NFR_CONSTRAINTS = `
══ JEA Non-Functional Requirements (MANDATORY — v1.1 / 2026-07-12) ══

NFR-007 | OTP-ONLY AUTH: Applicant login is OTP via SMS — never username/password.
          → "auth": { "type": "otp", "otp_channel": "sms" }
          VIOLATION: setting auth.type to anything other than "otp" is a hard error.

NFR-008 | TRANSACTION NUMBER FORMAT: {YY}{ServiceCode:4}{Seq:4} — 10 digits, NO separators, NO prefix.
          Example: 2620010001 (year=26, service=2001, seq=0001)
          → "transaction_number": { "format": "{YY}{service_code}{seq}", "service_code_digits": 4, "seq_digits": 4 }
          The service_code in the schema must be exactly 4 numeric digits (zero-padded).

NFR-009 | AUTOSAVE + CACHE: Form data autosaved to server cache on every field change.
          Only flushed to DB on explicit user submit. Never autosave directly to DB.
          → "autosave": { "enabled": true, "storage": "cache", "flush_on": "submit" }

NFR-010 | S3 OBJECT STORAGE: All uploaded files go to S3-compatible storage. Never local disk.
          → "storage": { "backend": "s3" }

FR-018  | MP4 SUPPORT: Document slots may include "mp4" for video uploads.
          Valid accept values: ["pdf", "jpg", "jpeg", "png", "mp4"]
          Add "mp4" to any document that could involve a video or screen recording.

FR-019  | PUBLIC TRACKING: Citizens can track status by transaction reference + OTP.
          No login required for tracking.
          → "public_tracking": { "enabled": true, "verify_via": "otp", "identity_provider": "dls" }

FR-020  | ADMIN-INITIATED FIRST STEP: Admin Dashboard is sole entry point for triggering
          step 1. Applicants fill the form but cannot self-submit to initiate step 1.
          → "workflow": { "first_step_actor": "admin", ... }

INT-001 | DLS INTEGRATION: Public tracking verifies citizen identity via DLS (Digital Licensing System).
          → public_tracking.identity_provider must be "dls"

INT-002 | SMS GATEWAY: OTP delivered via external SMS gateway (ENV-configured).
          → auth.otp_channel must be "sms"
`;

// ── ESP v2 / JEA schema format — few-shot reference in the system prompt ─────

const SCHEMA_FORMAT_REFERENCE = `
ESP v2 / JEA JSON schema format — produce ONLY valid JSON matching this structure:

{
  "service_code": "STRING — 4-digit numeric code for JEA (e.g. '1001'). Zero-pad to 4 digits.",
  "name_ar": "Arabic service name",
  "name_en": "English service name",
  "version": "1.0",

  "auth": {
    "type": "otp",
    "otp_channel": "sms"
  },

  "transaction_number": {
    "format": "{YY}{service_code}{seq}",
    "service_code_digits": 4,
    "seq_digits": 4,
    "example": "2620010001"
  },

  "autosave": {
    "enabled": true,
    "storage": "cache",
    "flush_on": "submit"
  },

  "storage": {
    "backend": "s3"
  },

  "public_tracking": {
    "enabled": true,
    "verify_via": "otp",
    "identity_provider": "dls"
  },

  "workflow": {
    "first_step_actor": "admin",
    "stages": [
      {
        "id": "snake_case_stage_id",
        "label_ar": "Arabic stage label",
        "label_en": "English stage label",
        "role": "staff | auditor | admin",
        "sla_hours": 24,
        "actions": ["approve", "reject", "request_modifications"]
      }
    ]
  },

  "fee": {
    "type": "fixed | tiered | formula",
    "amount": 100,
    "field": "field_id",
    "tiers": { "value1": 100, "value2": 200 },
    "default": 100,
    "currency": "JOD"
  },

  "fields": [
    {
      "id": "snake_case_field_id",
      "label_ar": "Arabic label",
      "label_en": "English label",
      "type": "text | textarea | select | radio | multiselect | checkbox_group | number | date | email",
      "required": true,
      "section": "section_id",
      "placeholder_ar": "optional",
      "description_ar": "optional help text",
      "pattern": "optional regex",
      "min_length": 0,
      "max_length": 255,
      "min": 0,
      "max": 999,
      "options": [
        { "value": "val", "label_ar": "...", "label_en": "..." }
      ],
      "conditional": { "field": "other_field_id", "value": "trigger_value" }
    }
  ],

  "sections": [
    { "id": "section_id", "label_ar": "Arabic section name", "label_en": "English section name" }
  ],

  "documents": [
    {
      "id": "doc_id",
      "label_ar": "Arabic document name",
      "label_en": "English document name",
      "required": true,
      "accept": ["pdf", "jpg", "jpeg", "png"],
      "max_size_mb": 5,
      "description_ar": "optional",
      "conditional": { "field": "field_id", "value": "trigger_value" }
    }
  ],

  "certificate": {
    "validity_months": 12,
    "title_ar": "Arabic certificate title",
    "title_en": "English certificate title",
    "fields_on_cert": ["field_id_1", "field_id_2"]
  }
}

Hard rules:
- service_code must be exactly 4 numeric digits (zero-padded)
- auth.type must ALWAYS be "otp" — never "password"
- workflow.first_step_actor must ALWAYS be "admin"
- autosave.enabled must ALWAYS be true, storage must be "cache"
- storage.backend must ALWAYS be "s3"
- Every field id and section id must be unique snake_case strings
- Every field must reference a valid section id in its "section" property
- Workflow stages must be ordered — first stage gets applications first
- Role must be: staff (day-to-day reviewer), auditor (legal/compliance), or admin
- For Jordanian government services: currency is JOD, use Arabic as primary language
- Valid document accept values: pdf, jpg, jpeg, png, mp4
- Return ONLY the raw JSON object — no markdown fences, no explanation text
`;

// ── Claude client ─────────────────────────────────────────────────────────────

function getAnthropic() {
  if (!ANTHROPIC_API_KEY) throw new Error('ANTHROPIC_API_KEY not set in environment');
  return new Anthropic({ apiKey: ANTHROPIC_API_KEY });
}

// ── Shared helpers ────────────────────────────────────────────────────────────

/** Extract all text blocks from a Claude response into a single string */
const extractText = r => r.content.filter(b => b.type === 'text').map(b => b.text).join('');

/** Strip markdown code fences Claude sometimes wraps output in despite instructions */
const stripFences = s => s.replace(/^```(?:json)?\n?/m, '').replace(/\n?```\s*$/m, '').trim();

/** Wrap a plain object in the MCP text-content envelope */
const textResult = obj => ({ content: [{ type: 'text', text: JSON.stringify(obj, null, 2) }] });

/** Required top-level keys for a valid ESP v2 schema */
const REQUIRED_SCHEMA_KEYS = ['service_code', 'name_ar', 'name_en', 'workflow', 'fields', 'sections', 'documents', 'fee'];

// ── Tools ─────────────────────────────────────────────────────────────────────

async function generateServiceSchema({ srs_text, service_code, hints }) {
  const anthropic = getAnthropic();

  const userMessage = [
    `Generate an ESP v2 JSON schema for the following government service.`,
    service_code ? `Service code to use: ${service_code}` : '',
    hints        ? `Additional hints: ${hints}` : '',
    `\n--- SRS / Service Description ---\n${srs_text}`,
  ].filter(Boolean).join('\n');

  const response = await anthropic.messages.create({
    model: MODEL,
    max_tokens: 8000,
    system: `You are an expert e-government service designer for Eqratech.
You convert service requirement specifications (SRS) into valid ESP v2 / JEA JSON schemas.
The ESP v2 platform is a schema-driven engine — the JSON schema you produce becomes a fully running e-service with no additional code.

${JEA_NFR_CONSTRAINTS}

${SCHEMA_FORMAT_REFERENCE}`,
    messages: [{ role: 'user', content: userMessage }],
  });

  const cleaned = stripFences(extractText(response));

  let parsed;
  try {
    parsed = JSON.parse(cleaned);
  } catch {
    throw new Error(`Claude returned invalid JSON: ${cleaned.slice(0, 200)}`);
  }

  const missing = REQUIRED_SCHEMA_KEYS.filter(k => !(k in parsed));
  if (missing.length) throw new Error(`Generated schema missing required keys: ${missing.join(', ')}`);

  return { schema: parsed, tokens_used: response.usage.output_tokens };
}

const VALID_DOC_ACCEPT = new Set(['pdf', 'jpg', 'jpeg', 'png', 'mp4']);

function validateSchema({ schema_json }) {
  let parsed;
  try {
    parsed = typeof schema_json === 'string' ? JSON.parse(schema_json) : schema_json;
  } catch {
    return { valid: false, errors: ['Invalid JSON — cannot parse'], nfr_violations: [] };
  }

  const errors        = [];   // structural errors
  const nfrViolations = [];   // JEA NFR compliance failures

  // ── Required top-level keys ───────────────────────────────────────────────
  for (const k of [...REQUIRED_SCHEMA_KEYS, 'certificate']) {
    if (!(k in parsed)) errors.push(`Missing required key: ${k}`);
  }

  // ── JEA NFR-007: OTP-only auth ────────────────────────────────────────────
  if (!parsed.auth) {
    nfrViolations.push(`NFR-007: Missing "auth" block — must be { type: "otp", otp_channel: "sms" }`);
  } else {
    if (parsed.auth.type !== 'otp') {
      nfrViolations.push(`NFR-007: auth.type is "${parsed.auth.type}" — must be "otp"`);
    }
    if (parsed.auth.otp_channel !== 'sms') {
      nfrViolations.push(`INT-002: auth.otp_channel is "${parsed.auth.otp_channel}" — must be "sms"`);
    }
  }

  // ── JEA NFR-008: Transaction number format ────────────────────────────────
  if (!parsed.transaction_number) {
    nfrViolations.push(`NFR-008: Missing "transaction_number" block — must define 10-digit format {YY}{ServiceCode:4}{Seq:4}`);
  } else {
    if (parsed.transaction_number.format !== '{YY}{service_code}{seq}') {
      nfrViolations.push(`NFR-008: transaction_number.format is "${parsed.transaction_number.format}" — must be "{YY}{service_code}{seq}" (10 digits, no separators, e.g. 2620010001)`);
    }
  }
  if (parsed.service_code && !/^\d{4}$/.test(String(parsed.service_code))) {
    nfrViolations.push(`NFR-008: service_code "${parsed.service_code}" must be exactly 4 numeric digits`);
  }

  // ── JEA NFR-009: Autosave ─────────────────────────────────────────────────
  if (!parsed.autosave) {
    nfrViolations.push(`NFR-009: Missing "autosave" block — must be { enabled: true, storage: "cache", flush_on: "submit" }`);
  } else {
    if (parsed.autosave.enabled !== true)       nfrViolations.push(`NFR-009: autosave.enabled must be true`);
    if (parsed.autosave.storage !== 'cache')    nfrViolations.push(`NFR-009: autosave.storage must be "cache"`);
    if (parsed.autosave.flush_on !== 'submit')  nfrViolations.push(`NFR-009: autosave.flush_on must be "submit"`);
  }

  // ── JEA NFR-010: S3 object storage ───────────────────────────────────────
  if (!parsed.storage) {
    nfrViolations.push(`NFR-010: Missing "storage" block — must be { backend: "s3" }`);
  } else if (parsed.storage.backend !== 's3') {
    nfrViolations.push(`NFR-010: storage.backend is "${parsed.storage.backend}" — must be "s3"`);
  }

  // ── JEA FR-019 + INT-001: Public tracking ────────────────────────────────
  if (!parsed.public_tracking) {
    nfrViolations.push(`FR-019: Missing "public_tracking" block — must be { enabled: true, verify_via: "otp", identity_provider: "dls" }`);
  } else {
    if (parsed.public_tracking.verify_via !== 'otp') {
      nfrViolations.push(`FR-019: public_tracking.verify_via must be "otp"`);
    }
    if (parsed.public_tracking.identity_provider !== 'dls') {
      nfrViolations.push(`INT-001: public_tracking.identity_provider must be "dls"`);
    }
  }

  // ── JEA FR-020: Admin-initiated first step ────────────────────────────────
  if (parsed.workflow && parsed.workflow.first_step_actor !== 'admin') {
    nfrViolations.push(`FR-020: workflow.first_step_actor is "${parsed.workflow?.first_step_actor}" — must be "admin"`);
  }

  // ── Workflow stages ───────────────────────────────────────────────────────
  if (parsed.workflow?.stages) {
    for (const [i, s] of parsed.workflow.stages.entries()) {
      if (!s.id)       errors.push(`Stage ${i}: missing id`);
      if (!s.label_ar) errors.push(`Stage ${i}: missing label_ar`);
      if (!s.role)     errors.push(`Stage ${i}: missing role`);
      if (!['staff','auditor','admin'].includes(s.role)) {
        errors.push(`Stage ${i}: invalid role "${s.role}"`);
      }
    }
  }

  // ── Fields → sections cross-reference ────────────────────────────────────
  const sectionIds = new Set((parsed.sections ?? []).map(s => s.id));
  if (parsed.fields) {
    for (const f of parsed.fields) {
      if (!f.id)       errors.push(`Field missing id`);
      if (!f.label_ar) errors.push(`Field ${f.id}: missing label_ar`);
      if (!f.type)     errors.push(`Field ${f.id}: missing type`);
      if (f.section && !sectionIds.has(f.section)) {
        errors.push(`Field ${f.id}: references unknown section "${f.section}"`);
      }
      const selectTypes = ['select','radio','multiselect','checkbox_group'];
      if (selectTypes.includes(f.type) && !f.options?.length) {
        errors.push(`Field ${f.id}: type "${f.type}" requires options array`);
      }
    }
  }

  // ── JEA FR-018: Document accept values ───────────────────────────────────
  if (parsed.documents) {
    for (const doc of parsed.documents) {
      if (doc.accept) {
        const invalid = doc.accept.filter(ext => !VALID_DOC_ACCEPT.has(ext));
        if (invalid.length) {
          errors.push(`Document ${doc.id}: invalid accept value(s): ${invalid.join(', ')} — allowed: pdf, jpg, jpeg, png, mp4`);
        }
      }
    }
  }

  // ── Fee ───────────────────────────────────────────────────────────────────
  if (parsed.fee) {
    if (!['fixed','tiered','formula'].includes(parsed.fee.type)) {
      errors.push(`fee.type must be fixed, tiered, or formula`);
    }
    if (parsed.fee.type === 'tiered' && !parsed.fee.tiers) {
      errors.push(`fee.type "tiered" requires fee.tiers object`);
    }
  }

  const valid = errors.length === 0 && nfrViolations.length === 0;
  return { valid, errors, nfr_violations: nfrViolations };
}

async function generateSrs({ service_concept, service_code, include_nfrs }) {
  const anthropic = getAnthropic();

  const includeNfrs = include_nfrs !== false; // default: true

  const userMessage = [
    `Generate a complete, structured Software Requirements Specification (SRS) document for the following government service.`,
    service_code ? `Proposed service code: JEA-26-${service_code}-0001` : '',
    ``,
    `Service concept:`,
    service_concept,
  ].filter(Boolean).join('\n');

  const nfrSection = includeNfrs ? `
## Non-Functional Requirements (JEA Platform — v1.1 / 2026-07-12)

These NFRs are FIXED for all JEA digital services. Include them verbatim:

| ID | Requirement |
|----|-------------|
| NFR-007 | Authentication for applicants is OTP-only via SMS — no username/password login |
| NFR-008 | Transaction reference number format: {YY}{ServiceCode:4}{Seq:4} — 10 digits, no separators (e.g. 2620010001) |
| NFR-009 | Form data autosaved to server-side cache on each field change; persisted to DB only on explicit user submit |
| NFR-010 | All uploaded files stored on S3-compatible object storage — no local filesystem |
| FR-018  | Document upload slots support PDF, PNG, JPG/JPEG, and MP4 video |
| FR-019  | Any citizen can track application status publicly by entering transaction reference number; identity verified via OTP |
| FR-020  | Admin Dashboard is the sole entry point for initiating the first workflow step of any digital service |
| INT-001 | Public tracking integrates with DLS (Digital Licensing System) for citizen identity verification |
| INT-002 | OTP delivered via external SMS gateway, configurable via environment variables |
` : '';

  const response = await anthropic.messages.create({
    model: MODEL,
    max_tokens: 8000,
    system: `You are a senior software requirements engineer specialising in Jordanian e-government systems.
You produce structured SRS documents following IEEE 830 / Eqratech EDA v1.1 standards.
The document must be in Arabic (primary) with English section headings.
Each requirement must have a unique ID (FR-xxx, NFR-xxx, SEC-xxx, INT-xxx).
Produce a complete SRS ready to feed directly into an ESP v2 schema generator.

Structure every SRS with these sections:
1. مقدمة (Introduction) — purpose, scope, definitions
2. الوصف العام (General Description) — system context, user roles, constraints
3. المتطلبات الوظيفية (Functional Requirements) — FR-xxx numbered list
4. المتطلبات غير الوظيفية (Non-Functional Requirements) — NFR-xxx numbered list (include JEA platform NFRs)
5. متطلبات الأمان (Security Requirements) — SEC-xxx numbered list
6. متطلبات التكامل (Integration Requirements) — INT-xxx numbered list
7. نماذج البيانات (Data Models) — key entities and their attributes
8. مخطط سير العمل (Workflow) — stages, roles, transitions
9. متطلبات الرسوم (Fee Structure) — fee type and amount
10. متطلبات الشهادة (Certificate Requirements) — validity, fields

${nfrSection}`,
    messages: [{ role: 'user', content: userMessage }],
  });

  return { srs: extractText(response), tokens_used: response.usage.output_tokens };
}

async function saveSchemaToEsp({ schema_json, status }) {
  const parsed = typeof schema_json === 'string' ? JSON.parse(schema_json) : schema_json;

  if (!ESP_ADMIN_TOKEN) throw new Error('ESP_ADMIN_TOKEN not set');

  const body = {
    code:            parsed.service_code,
    name_ar:         parsed.name_ar,
    name_en:         parsed.name_en,
    description_ar:  parsed.description_ar ?? '',
    description_en:  parsed.description_en ?? '',
    currency:        parsed.fee?.currency ?? 'JOD',
    schema:          parsed,
    status:          status ?? 'draft',
  };

  const res = await fetch(`${ESP_API_BASE}/v1/services`, {
    method:  'POST',
    headers: {
      'Content-Type':  'application/json',
      'Authorization': `Bearer ${ESP_ADMIN_TOKEN}`,
    },
    body: JSON.stringify(body),
  });

  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json.message ?? `HTTP ${res.status}`);

  return { service_id: json.service.id, code: json.service.code, status: json.service.status };
}

// ── MCP Server ────────────────────────────────────────────────────────────────

const server = new Server(
  { name: 'esp-schema-generator', version: '1.0.0' },
  { capabilities: { tools: {} } },
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name:        'generate_srs',
      description: 'Generate a complete, structured SRS (Software Requirements Specification) document from a plain-language service concept. Produces an IEEE 830 / EDA v1.1 compliant Arabic-primary SRS with all JEA platform NFRs pre-embedded (NFR-007..010, FR-018..020, INT-001..002). Use this BEFORE calling generate_service_schema when starting from scratch.',
      inputSchema: {
        type: 'object',
        properties: {
          service_concept: { type: 'string',  description: 'Plain-language description of the service — what it does, who uses it, rough workflow, fees, documents required.' },
          service_code:    { type: 'string',  description: 'Optional: 4-digit JEA service code (e.g. "1001"). AI will assign one if omitted.' },
          include_nfrs:    { type: 'boolean', description: 'Whether to embed JEA platform NFRs (NFR-007..010 etc.) in the output. Default: true.' },
        },
        required: ['service_concept'],
      },
    },
    {
      name:        'generate_service_schema',
      description: 'Convert an SRS document or service description into a JEA-compliant ESP v2 JSON schema using Claude AI. Automatically enforces all JEA NFRs (OTP auth, transaction number format, autosave, S3 storage, MP4 support, public tracking, admin-first-step). Returns the complete schema ready to paste into the admin panel or activate immediately.',
      inputSchema: {
        type: 'object',
        properties: {
          srs_text:     { type: 'string',  description: 'Full SRS text or plain-language service description' },
          service_code: { type: 'string',  description: 'Optional: 4-digit JEA service code (e.g. "1001"). AI will generate one if omitted.' },
          hints:        { type: 'string',  description: 'Optional: extra instructions, e.g. "fee is 50 JOD fixed" or "add conditional video upload for practical exam"' },
        },
        required: ['srs_text'],
      },
    },
    {
      name:        'validate_schema',
      description: 'Validate an ESP v2 JSON schema for structural correctness AND JEA NFR compliance. Returns separate lists of structural errors and NFR violations (NFR-007..010, FR-018..020, INT-001..002).',
      inputSchema: {
        type: 'object',
        properties: {
          schema_json: { description: 'Schema as JSON object or JSON string' },
        },
        required: ['schema_json'],
      },
    },
    {
      name:        'save_schema_to_esp',
      description: 'POST the generated schema directly to the ESP backend to create a new ServiceDefinition. Use status="draft" for review first, or status="active" to go live immediately.',
      inputSchema: {
        type: 'object',
        properties: {
          schema_json: { description: 'Schema as JSON object or JSON string' },
          status:      { type: 'string', enum: ['draft', 'active'], description: 'Initial status (default: draft)' },
        },
        required: ['schema_json'],
      },
    },
  ],
}));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;

  try {
    if (name === 'generate_srs') {
      const result = await generateSrs(args);
      return textResult({
        success:     true,
        tokens_used: result.tokens_used,
        srs:         result.srs,
        next_steps: [
          'Review the SRS and adjust any service-specific requirements',
          'Call generate_service_schema with the srs text to produce the ESP v2 JSON schema',
          'Call validate_schema to confirm JEA NFR compliance before saving',
        ],
      });
    }

    if (name === 'generate_service_schema') {
      const result = await generateServiceSchema(args);
      return textResult({
        success:     true,
        tokens_used: result.tokens_used,
        schema:      result.schema,
        next_steps: [
          'Call validate_schema to check structural errors and JEA NFR compliance',
          'Call save_schema_to_esp with status="draft" to save, or status="active" to go live',
          'Or copy the schema JSON to the admin New Service page',
        ],
      });
    }

    if (name === 'validate_schema') {
      const result  = validateSchema(args);
      const summary = result.valid
        ? '✅ Schema is valid and JEA NFR-compliant.'
        : [
            result.errors.length         ? `❌ ${result.errors.length} structural error(s)`   : null,
            result.nfr_violations.length ? `⚠️  ${result.nfr_violations.length} JEA NFR violation(s)` : null,
          ].filter(Boolean).join(' | ');
      return textResult({ ...result, summary });
    }

    if (name === 'save_schema_to_esp') {
      const result = await saveSchemaToEsp(args);
      return textResult({
        success: true,
        message: `Service created: ID ${result.service_id}, code ${result.code}, status ${result.status}`,
        ...result,
      });
    }

    throw new Error(`Unknown tool: ${name}`);
  } catch (err) {
    return {
      content: [{ type: 'text', text: JSON.stringify({ success: false, error: err.message }) }],
      isError: true,
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
