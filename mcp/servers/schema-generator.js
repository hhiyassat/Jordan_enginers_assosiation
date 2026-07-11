/**
 * ESP v2 — Schema Generator MCP Server
 *
 * Converts an SRS document or plain-text service description into a
 * valid ESP v2 JSON schema using Claude AI.
 *
 * Tools:
 *   generate_service_schema  — SRS text → ESP v2 JSON schema
 *   validate_schema          — check a schema for structural errors
 *   save_schema_to_esp       — POST the generated schema directly to the ESP backend
 *
 * Usage from Claude Desktop:
 *   "Generate a schema for engineer registration from this SRS: <text>"
 *   → Claude calls generate_service_schema → returns ready-to-use JSON
 *   → Claude calls save_schema_to_esp to activate it immediately
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

// ── ESP v2 schema format — used as few-shot reference in the system prompt ───

const SCHEMA_FORMAT_REFERENCE = `
ESP v2 JSON schema format — produce ONLY valid JSON matching this structure:

{
  "service_code": "STRING — unique code like ENG-REG-001",
  "name_ar": "Arabic service name",
  "name_en": "English service name",
  "version": "1.0",

  "workflow": {
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
    "amount": 100,                          // for fixed
    "field": "field_id",                    // for tiered — which field drives the fee
    "tiers": { "value1": 100, "value2": 200 }, // for tiered
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
      "options": [                           // only for select / radio / multiselect / checkbox_group
        { "value": "val", "label_ar": "...", "label_en": "..." }
      ],
      "conditional": { "field": "other_field_id", "value": "trigger_value" }  // optional
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

Rules:
- Every field id and section id must be unique snake_case strings
- Every field must reference a valid section id in its "section" property
- Workflow stages must be ordered — first stage gets applications first
- Role must be: staff (day-to-day reviewer), auditor (legal/compliance), or admin
- For Jordanian government services: currency is JOD, use Arabic as primary language
- Return ONLY the raw JSON object — no markdown fences, no explanation text
`;

// ── Claude client ─────────────────────────────────────────────────────────────

function getAnthropic() {
  if (!ANTHROPIC_API_KEY) throw new Error('ANTHROPIC_API_KEY not set in environment');
  return new Anthropic({ apiKey: ANTHROPIC_API_KEY });
}

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
You convert service requirement specifications (SRS) into valid ESP v2 JSON schemas.
The ESP v2 platform is a schema-driven engine — the JSON schema you produce becomes a fully running e-service with no additional code.

${SCHEMA_FORMAT_REFERENCE}`,
    messages: [{ role: 'user', content: userMessage }],
  });

  const text = response.content
    .filter(b => b.type === 'text')
    .map(b => b.text)
    .join('');

  // Strip markdown fences if Claude wrapped it anyway
  const cleaned = text.replace(/^```(?:json)?\n?/m, '').replace(/\n?```\s*$/m, '').trim();

  // Validate it parses
  let parsed;
  try {
    parsed = JSON.parse(cleaned);
  } catch {
    throw new Error(`Claude returned invalid JSON: ${cleaned.slice(0, 200)}`);
  }

  // Basic structure check
  const required = ['service_code', 'name_ar', 'name_en', 'workflow', 'fields', 'sections', 'documents', 'fee'];
  const missing  = required.filter(k => !(k in parsed));
  if (missing.length) {
    throw new Error(`Generated schema missing required keys: ${missing.join(', ')}`);
  }

  return { schema: parsed, tokens_used: response.usage.output_tokens };
}

function validateSchema({ schema_json }) {
  let parsed;
  try {
    parsed = typeof schema_json === 'string' ? JSON.parse(schema_json) : schema_json;
  } catch {
    return { valid: false, errors: ['Invalid JSON — cannot parse'] };
  }

  const errors = [];

  // Required top-level keys
  for (const k of ['service_code', 'name_ar', 'name_en', 'workflow', 'fields', 'sections', 'documents', 'fee', 'certificate']) {
    if (!(k in parsed)) errors.push(`Missing required key: ${k}`);
  }

  // Workflow stages
  if (parsed.workflow?.stages) {
    for (const [i, s] of parsed.workflow.stages.entries()) {
      if (!s.id)       errors.push(`Stage ${i}: missing id`);
      if (!s.label_ar) errors.push(`Stage ${i}: missing label_ar`);
      if (!s.role)     errors.push(`Stage ${i}: missing role`);
      if (!['staff','auditor','admin'].includes(s.role)) errors.push(`Stage ${i}: invalid role "${s.role}"`);
    }
  }

  // Fields → sections cross-reference
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

  // Fee
  if (parsed.fee) {
    if (!['fixed','tiered','formula'].includes(parsed.fee.type)) {
      errors.push(`fee.type must be fixed, tiered, or formula`);
    }
    if (parsed.fee.type === 'tiered' && !parsed.fee.tiers) {
      errors.push(`fee.type "tiered" requires fee.tiers object`);
    }
  }

  return { valid: errors.length === 0, errors };
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
      name:        'generate_service_schema',
      description: 'Convert an SRS document or service description into a valid ESP v2 JSON schema using Claude AI. Returns the complete schema ready to paste into the admin panel or activate immediately.',
      inputSchema: {
        type: 'object',
        properties: {
          srs_text:     { type: 'string',  description: 'Full SRS text or plain-language service description' },
          service_code: { type: 'string',  description: 'Optional: desired service code (e.g. ENG-REG-001). AI will generate one if omitted.' },
          hints:        { type: 'string',  description: 'Optional: extra instructions, e.g. "fee is 50 JOD fixed" or "add conditional health certificate for food businesses"' },
        },
        required: ['srs_text'],
      },
    },
    {
      name:        'validate_schema',
      description: 'Validate an ESP v2 JSON schema for structural correctness before saving. Returns a list of errors or confirms the schema is valid.',
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
    let result;

    if (name === 'generate_service_schema') {
      result = await generateServiceSchema(args);
      return {
        content: [{
          type: 'text',
          text: JSON.stringify({
            success:      true,
            tokens_used:  result.tokens_used,
            schema:       result.schema,
            next_steps:   [
              'Call validate_schema to check for errors',
              'Call save_schema_to_esp with status="draft" to save, or status="active" to go live',
              'Or copy the schema JSON to the admin New Service page',
            ],
          }, null, 2),
        }],
      };
    }

    if (name === 'validate_schema') {
      result = validateSchema(args);
      return {
        content: [{
          type: 'text',
          text: JSON.stringify(result, null, 2),
        }],
      };
    }

    if (name === 'save_schema_to_esp') {
      result = await saveSchemaToEsp(args);
      return {
        content: [{
          type: 'text',
          text: JSON.stringify({
            success: true,
            message: `Service created: ID ${result.service_id}, code ${result.code}, status ${result.status}`,
            ...result,
          }, null, 2),
        }],
      };
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
