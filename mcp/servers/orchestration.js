/**
 * Orchestration MCP Server (Nashmi AI Manager)
 * Tools: list_cycles, get_cycle, push_service, notify_code_done,
 *        check_pending_feedback, get_pipeline_status, list_notifications
 */
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';

const API_BASE   = process.env.ESP_API_BASE   ?? 'http://localhost:8002/api';
const ADMIN_TOKEN= process.env.ESP_ADMIN_TOKEN ?? '';
const NASHMI_BASE= process.env.NASHMI_BASE_URL ?? 'https://nashmi.manager.eqratech.com';
const NASHMI_KEY = process.env.NASHMI_INTEGRATION_KEY ?? '';

async function jeaGet(path) {
  const r = await fetch(`${API_BASE}${path}`, {
    headers: { Authorization: `Bearer ${ADMIN_TOKEN}`, Accept: 'application/json' }
  });
  return r.json();
}

async function jeaPost(path, body) {
  const r = await fetch(`${API_BASE}${path}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${ADMIN_TOKEN}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  });
  return r.json();
}

const server = new Server(
  { name: 'esp-orchestration', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'list_cycles',
      description: 'List all Nashmi integration cycles with their current status.',
      inputSchema: { type: 'object', properties: {
        status: { type: 'string', description: 'Filter by status (e.g. requirements_received, code_done, closed)' }
      }}
    },
    {
      name: 'get_cycle',
      description: 'Get full details of a specific integration cycle by ID.',
      inputSchema: { type: 'object', required: ['id'], properties: {
        id: { type: 'number', description: 'Cycle ID' }
      }}
    },
    {
      name: 'push_service_to_nashmi',
      description: 'Push a service from JEA to Nashmi AI Manager to start the AI pipeline.',
      inputSchema: { type: 'object', required: ['service_id'], properties: {
        service_id: { type: 'number', description: 'Service ID from the services table' }
      }}
    },
    {
      name: 'notify_code_done',
      description: 'Notify nashmi-ai-manager that code for a cycle is complete and ready for review/test/QA.',
      inputSchema: { type: 'object', required: ['cycle_id'], properties: {
        cycle_id:       { type: 'number', description: 'Cycle ID' },
        git_branch:     { type: 'string' },
        git_commit:     { type: 'string' },
        api_endpoints:  { type: 'array', items: { type: 'string' } },
        frontend_pages: { type: 'array', items: { type: 'string' } },
        db_tables:      { type: 'array', items: { type: 'string' } },
        notes:          { type: 'string' }
      }}
    },
    {
      name: 'check_pending_feedback',
      description: 'List all cycles that are in code_done status (awaiting feedback from nashmi-ai-manager).',
      inputSchema: { type: 'object', properties: {} }
    },
    {
      name: 'get_pipeline_status',
      description: 'Get a summary of the full Nashmi pipeline: cycles by status, pending actions.',
      inputSchema: { type: 'object', properties: {} }
    },
    {
      name: 'list_notifications',
      description: 'List recent JEA notifications (inbound from nashmi-ai-manager and nashmi-requirement-ai).',
      inputSchema: { type: 'object', properties: {
        unread_only: { type: 'boolean', description: 'Show only unread notifications' }
      }}
    },
    {
      name: 'simulate_inbound_requirements',
      description: 'Simulate nashmi-requirement-ai sending a requirements document to JEA (for testing).',
      inputSchema: { type: 'object', required: ['service_name'], properties: {
        service_name:        { type: 'string' },
        project_description: { type: 'string' },
        source_system:       { type: 'string' }
      }}
    },
    {
      name: 'estimate_complexity',
      description: [
        'Analyse a service requirements description and return a complexity score',
        '(SIMPLE / MODERATE / COMPLEX / VERY_COMPLEX) plus per-flag breakdown.',
        'Adapted from nashmi_v2 A1b. Keyword-based — no LLM call needed.',
        'Use before starting development to gauge scope and set realistic estimates.'
      ].join(' '),
      inputSchema: {
        type: 'object',
        required: ['description'],
        properties: {
          description: { type: 'string', description: 'Plain-text service requirements description' }
        }
      }
    }
  ]
}));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;

  if (name === 'list_cycles') {
    try {
      const data = await jeaGet('/admin/integration/cycles');
      let cycles = data.data ?? [];
      if (args?.status) cycles = cycles.filter(c => c.status === args.status);
      const lines = cycles.map(c =>
        `#${c.id} [${c.status.padEnd(22)}] ${c.cycle_ref} — ${c.service_name}`
      );
      return { content: [{ type: 'text', text: `Integration Cycles (${cycles.length}):\n\n${lines.join('\n')}` }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'get_cycle') {
    try {
      const data = await jeaGet(`/admin/integration/cycles/${args.id}`);
      return { content: [{ type: 'text', text: JSON.stringify(data.data, null, 2) }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'push_service_to_nashmi') {
    try {
      const data = await jeaPost(`/admin/nashmi/push/${args.service_id}`, {});
      return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'notify_code_done') {
    try {
      const data = await jeaPost(`/admin/integration/cycles/${args.cycle_id}/notify-code-done`, {
        git_branch:     args.git_branch     ?? 'main',
        git_commit:     args.git_commit,
        api_endpoints:  args.api_endpoints  ?? [],
        frontend_pages: args.frontend_pages ?? [],
        db_tables:      args.db_tables      ?? [],
        notes:          args.notes,
      });
      return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'check_pending_feedback') {
    try {
      const data = await jeaGet('/admin/integration/cycles');
      const pending = (data.data ?? []).filter(c => c.status === 'code_done');
      const lines = pending.map(c => `#${c.id} ${c.cycle_ref} — ${c.service_name} (notified: ${c.code_done_notified_at})`);
      return { content: [{ type: 'text', text: pending.length ? `Awaiting feedback (${pending.length}):\n\n${lines.join('\n')}` : '✅ No cycles awaiting feedback.' }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'get_pipeline_status') {
    try {
      const data = await jeaGet('/admin/integration/cycles');
      const cycles = data.data ?? [];
      const byStatus = {};
      for (const c of cycles) byStatus[c.status] = (byStatus[c.status] ?? 0) + 1;
      const lines = [
        `## Nashmi Pipeline Status — ${new Date().toLocaleString()}`,
        '',
        `Total cycles: ${cycles.length}`,
        '',
        ...Object.entries(byStatus).map(([s, n]) => `  ${s.padEnd(25)} ${n}`),
        '',
        cycles.filter(c => c.status === 'requirements_received').length
          ? `⚡ Action needed: ${cycles.filter(c => c.status === 'requirements_received').length} cycle(s) awaiting development`
          : '✅ No pending development work',
        cycles.filter(c => c.status === 'code_done').length
          ? `⏳ Awaiting feedback: ${cycles.filter(c => c.status === 'code_done').length} cycle(s)`
          : '',
      ];
      return { content: [{ type: 'text', text: lines.filter(Boolean).join('\n') }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'list_notifications') {
    try {
      const data = await jeaGet('/notifications');
      let notifs = data.data ?? [];
      if (args?.unread_only) notifs = notifs.filter(n => !n.is_read);
      const lines = notifs.map(n =>
        `[${n.is_read ? ' ' : '●'}] ${n.type.padEnd(22)} ${n.title.substring(0, 60)}`
      );
      return { content: [{ type: 'text', text: `Notifications (${notifs.length}):\n\n${lines.join('\n')}` }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'simulate_inbound_requirements') {
    try {
      const r = await fetch(`${API_BASE}/integration/receive-requirements`, {
        method: 'POST',
        headers: { 'X-Integration-Key': NASHMI_KEY, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          service_name: args.service_name,
          project_description: args.project_description ?? 'Simulated requirements from MCP orchestration tool.',
          source_system: args.source_system ?? 'nashmi-requirement-ai',
          meta: { simulated: true, via: 'orchestration-mcp' }
        }),
      });
      const data = await r.json();
      return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  // ── estimate_complexity ────────────────────────────────────────────────────
  // Adapted from nashmi_v2 A1b — keyword-based scoring, no LLM needed.
  if (name === 'estimate_complexity') {
    const text = (args?.description ?? '').toLowerCase();

    const flags = {
      has_audit_trail:      /audit|activity log|history|trail|who changed|timestamp/i.test(text),
      has_state_machine:    /status|workflow|transition|pending|approved|rejected|under.review|needs.correction/i.test(text),
      has_file_upload:      /upload|attachment|document|pdf|file|scan/i.test(text),
      has_pagination:       /filter|search|paginate|per.page|date.range|sort/i.test(text),
      has_cert_generation:  /certificate|cert|generate|reference.number|qr|stamp|official/i.test(text),
      has_dashboard_stats:  /dashboard|stat|count|summary|total|chart|metric/i.test(text),
      has_notifications:    /notif|email|sms|alert|bell|message/i.test(text),
      has_multi_role:       /role|engineer|auditor|admin|officer|citizen|manager|reviewer/i.test(text),
      has_payment:          /payment|fee|invoice|billing|charge/i.test(text),
      has_external_api:     /integration|api|webhook|nashmi|sanad|third.party|external/i.test(text),
    };

    // Estimate entity count from keywords
    const entityKeywords = ['submission', 'service', 'user', 'document', 'log', 'notification', 'certificate', 'drawing', 'payment', 'comment', 'attachment'];
    const entityCount = entityKeywords.filter(k => text.includes(k)).length;

    // Estimate endpoint count
    const actionKeywords = ['create', 'submit', 'list', 'get', 'update', 'delete', 'approve', 'reject', 'upload', 'download', 'search', 'filter', 'export', 'notify'];
    const endpointEstimate = actionKeywords.filter(k => text.includes(k)).length * 1.5;

    // Score
    const flagCount = Object.values(flags).filter(Boolean).length;
    let score;
    if (entityCount <= 2 && endpointEstimate <= 5 && flagCount <= 2)       score = 'SIMPLE';
    else if (entityCount <= 3 && endpointEstimate <= 8 && flagCount <= 4)  score = 'MODERATE';
    else if (entityCount <= 5 && endpointEstimate <= 14 && flagCount <= 7) score = 'COMPLEX';
    else                                                                    score = 'VERY_COMPLEX';

    const timeEstimate = { SIMPLE: '1–2 weeks', MODERATE: '2–4 weeks', COMPLEX: '4–8 weeks', VERY_COMPLEX: '8–16 weeks' };
    const sprintEstimate = { SIMPLE: '1', MODERATE: '2–3', COMPLEX: '4–6', VERY_COMPLEX: '8–12' };

    const flagLines = Object.entries(flags)
      .map(([k, v]) => `  ${v ? '✅' : '☐'} ${k.replace(/_/g, ' ')}`)
      .join('\n');

    return { content: [{ type: 'text', text: [
      `## Service Complexity Estimate`,
      ``,
      `COMPLEXITY_SCORE : ${score}`,
      `Estimated time   : ${timeEstimate[score]}`,
      `Sprints          : ${sprintEstimate[score]}`,
      ``,
      `── Detection flags ──────────────────────────────────`,
      flagLines,
      ``,
      `── Rough counts ─────────────────────────────────────`,
      `  Detected entities  : ~${entityCount}`,
      `  Estimated endpoints: ~${Math.round(endpointEstimate)}`,
      `  Feature flags set  : ${flagCount} / ${Object.keys(flags).length}`,
      ``,
      `── Scoring guide ─────────────────────────────────────`,
      `  SIMPLE       : 1–2 entities, <5 endpoints, ≤2 flags`,
      `  MODERATE     : 2–3 entities, 5–8 endpoints, ≤4 flags`,
      `  COMPLEX      : 3–5 entities, 8–14 endpoints, ≤7 flags`,
      `  VERY_COMPLEX : 5+ entities, 14+ endpoints, 8+ flags`,
      ``,
      `── Recommended team size ─────────────────────────────`,
      score === 'SIMPLE'       ? `  1 backend + 1 frontend developer` :
      score === 'MODERATE'     ? `  1 backend + 1 frontend + 1 QA` :
      score === 'COMPLEX'      ? `  2 backend + 1 frontend + 1 QA + PM` :
                                 `  2 backend + 2 frontend + 1 QA + 1 DevOps + PM`,
    ].join('\n') }] };
  }

  throw new Error(`Unknown tool: ${name}`);
});

const transport = new StdioServerTransport();
await server.connect(transport);
