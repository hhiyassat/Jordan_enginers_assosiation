/**
 * Quality MCP Server
 * Tools: run_backend_tests, run_frontend_lint, check_code_coverage,
 *        check_php_style, check_typescript_types, run_all_checks,
 *        review_backend, review_frontend
 *
 * review_backend / review_frontend adapted from nashmi_v2 A8a/A8b QA agents.
 * They read the actual source files and return them alongside a structured
 * checklist so Claude can perform a thorough code review in one step.
 */
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { execSync } from 'child_process';
import { readFileSync, existsSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const BACKEND  = process.env.BACKEND_PATH  ?? './backend';
const FRONTEND = process.env.FRONTEND_PATH ?? './frontend';

function shell(cmd, cwd) {
  try {
    return { success: true, output: execSync(cmd, { cwd, encoding: 'utf8', timeout: 120000 }) };
  } catch (e) {
    return { success: false, output: e.stdout ?? '', error: e.stderr ?? e.message };
  }
}

const server = new Server(
  { name: 'esp-quality', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'run_backend_tests',
      description: 'Run PHPUnit tests for the Laravel backend. Returns pass/fail counts and any failures.',
      inputSchema: { type: 'object', properties: {
        filter: { type: 'string', description: 'Optional test filter (class or method name)' }
      }}
    },
    {
      name: 'run_frontend_lint',
      description: 'Run ESLint on the React/TypeScript frontend source.',
      inputSchema: { type: 'object', properties: {
        fix: { type: 'boolean', description: 'Auto-fix fixable issues (default false)' }
      }}
    },
    {
      name: 'check_code_coverage',
      description: 'Run PHPUnit with code coverage report. Returns coverage percentage per class.',
      inputSchema: { type: 'object', properties: {} }
    },
    {
      name: 'check_php_style',
      description: 'Check PHP code style using PHP-CS-Fixer (dry-run). Returns list of files with style violations.',
      inputSchema: { type: 'object', properties: {
        fix: { type: 'boolean', description: 'Apply fixes automatically (default false)' }
      }}
    },
    {
      name: 'run_all_checks',
      description: 'Run all quality checks: backend tests, frontend lint, PHP style. Returns a summary report.',
      inputSchema: { type: 'object', properties: {} }
    },
    {
      name: 'check_typescript_types',
      description: 'Run TypeScript type-check (tsc --noEmit) on the frontend.',
      inputSchema: { type: 'object', properties: {} }
    },
    {
      name: 'review_backend',
      description: [
        'Read JEA backend source files and return them with a 12-check Laravel/PHP code review checklist.',
        'Adapted from nashmi_v2 A8a. Use the returned content to perform a thorough review.',
        'Checks: namespaces, service method wiring, request validation, API Resources, filters,',
        'status machine, CORS, routes, migration alignment, auth middleware, relation access, fillable fields.'
      ].join(' '),
      inputSchema: {
        type: 'object',
        properties: {
          focus: { type: 'string', description: 'Optional: focus on a specific controller or service file, e.g. "SubmissionController"' }
        }
      }
    },
    {
      name: 'review_frontend',
      description: [
        'Read JEA frontend source files and return them with a 10-check React/TypeScript review checklist.',
        'Adapted from nashmi_v2 A8b. Checks: API URLs, POST body field names, no auto-generated fields,',
        'element refs, status update format, error handling, Bearer token, RTL compliance,',
        'response field names, TypeScript interface alignment.'
      ].join(' '),
      inputSchema: {
        type: 'object',
        properties: {
          focus: { type: 'string', description: 'Optional: focus on a specific page/component, e.g. "SubmissionForm"' }
        }
      }
    }
  ]
}));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;

  if (name === 'run_backend_tests') {
    const filter = args?.filter ? `--filter "${args.filter}"` : '';
    const r = shell(`php artisan test ${filter} --colors=never`, BACKEND);
    return { content: [{ type: 'text', text: `Backend Tests\n\n${r.output}\n${r.error ?? ''}` }] };
  }

  if (name === 'run_frontend_lint') {
    const fix = args?.fix ? '--fix' : '';
    const r = shell(`npx eslint src ${fix} --ext .ts,.tsx --format compact`, FRONTEND);
    return { content: [{ type: 'text', text: `ESLint\n\n${r.output}\n${r.error ?? ''}` }] };
  }

  if (name === 'check_code_coverage') {
    const r = shell('php artisan test --coverage --colors=never', BACKEND);
    return { content: [{ type: 'text', text: `Code Coverage\n\n${r.output}\n${r.error ?? ''}` }] };
  }

  if (name === 'check_php_style') {
    const fix = args?.fix ? '' : '--dry-run';
    const r = shell(`vendor/bin/php-cs-fixer fix ${fix} app/ --diff 2>&1 || true`, BACKEND);
    return { content: [{ type: 'text', text: `PHP Style\n\n${r.output}` }] };
  }

  if (name === 'check_typescript_types') {
    const r = shell('npx tsc --noEmit', FRONTEND);
    return { content: [{ type: 'text', text: `TypeScript\n\n${r.success ? '✅ No type errors' : r.output + '\n' + r.error}` }] };
  }

  if (name === 'run_all_checks') {
    const tests   = shell('php artisan test --colors=never 2>&1', BACKEND);
    const lint    = shell('npx eslint src --ext .ts,.tsx --format compact 2>&1 || true', FRONTEND);
    const ts      = shell('npx tsc --noEmit 2>&1 || true', FRONTEND);
    const summary = [
      `## Quality Report — ${new Date().toISOString()}`,
      '',
      `### Backend Tests  ${tests.success ? '✅' : '❌'}`,
      tests.output.split('\n').slice(-10).join('\n'),
      '',
      `### Frontend Lint  ${lint.success ? '✅' : '⚠️'}`,
      lint.output.split('\n').slice(0, 20).join('\n'),
      '',
      `### TypeScript     ${ts.output.trim() === '' ? '✅' : '⚠️'}`,
      ts.output.split('\n').slice(0, 10).join('\n'),
    ].join('\n');
    return { content: [{ type: 'text', text: summary }] };
  }

  // ── review_backend ─────────────────────────────────────────────────────────
  if (name === 'review_backend') {
    const snapshot = readBackendFiles(BACKEND, args?.focus);

    const checklist = `
═══ BACKEND CODE REVIEW CHECKLIST (Laravel/PHP — adapted from nashmi_v2 A8a) ════

TOLERANCE RULES — never flag these:
  ✓ Extra comments, docstrings, or logging
  ✓ Extra validation stricter than required
  ✓ Minor variable name differences that don't affect behaviour
  ✓ Extra helper methods not in spec
  ✓ Over-inclusive use statements (unused imports are warnings, not bugs)

Real bugs to find:
  ✗ Wrong namespace/class name in use statements (NameError at runtime)
  ✗ Controller calls a Service method that doesn't exist (MethodNotFoundError)
  ✗ Create request includes auto-generated fields (id, created_at, status) → 422
  ✗ Response returns raw Eloquent model instead of API Resource → exposes hidden fields
  ✗ Missing Sanctum auth middleware on protected routes
  ✗ Status transition not validated → invalid transitions silently accepted
  ✗ Model attribute accessed that is not in $fillable or migration column
  ✗ Missing CORS configuration for the frontend origin

── CHECK 1: NAMESPACES & USE STATEMENTS
   Are all use App\\... paths correct and matching actual file locations?
   Wrong namespace = fatal NameError at first request.

── CHECK 2: CONTROLLER → SERVICE WIRING
   For every method call in Controllers (e.g. $this->service->create(...)):
   Verify the method exists in the injected Service class.
   Missing method = MethodNotFoundError at request time.

── CHECK 3: ARGUMENT MATCHING
   Do Controller method calls pass the right parameter names to Service methods?
   Mismatched named args = TypeError.

── CHECK 4: REQUEST VALIDATION FIELDS
   Do FormRequest rules include only fillable fields?
   Never: 'id', 'created_at', 'updated_at', 'status' (for create requests).
   → These fields in $request->validated() cause unexpected data in DB or 422.

── CHECK 5: API RESOURCE USAGE
   Do all Controller responses return Resource/Collection classes, not raw Model::all()?
   Raw model responses can expose hidden fields and break frontend field name expectations.

── CHECK 6: FILTERS & PAGINATION
   Do list/index methods accept and apply: status, search, date_from, date_to, per_page?
   Missing params = frontend filter controls broken with no error.

── CHECK 7: STATUS STATE MACHINE
   Does the Service validate allowed transitions before updating status?
   Should throw a 422/400 on invalid transitions — not silently accept them.

── CHECK 8: CORS CONFIGURATION
   Is config/cors.php set to allow the frontend origin (localhost:5173)?
   Missing = every browser request fails with CORS error.

── CHECK 9: ROUTES (api.php)
   Does api.php define all expected endpoints?
   Are authenticated routes inside auth:sanctum middleware group?
   Are admin-only routes inside the admin middleware?

── CHECK 10: MIGRATION ↔ MODEL ALIGNMENT
   For each Model's $fillable, verify every field exists as a column in its migration.
   Accessing a non-existent column throws a QueryException or returns null silently.

── CHECK 11: AUTH MIDDLEWARE
   Are all non-public routes protected with auth:sanctum?
   Is the admin routes group protected with an admin/role check middleware?

── CHECK 12: RELATION & ATTRIBUTE ACCESS
   For every ->relation or ->attribute access on an Eloquent model in Services:
   Verify the relation is defined on the Model class (hasMany, belongsTo, etc.)
   and every attribute is either in $fillable, a migration column, or an accessor.

OUTPUT FORMAT:
  BACKEND_QA_STATUS: PASS | FAIL
  For each issue:
    ISSUE[N]: Severity (CRITICAL|HIGH|MEDIUM) | Check N | File | Problem | Fix
  End with: BACKEND_FIX_NEEDED: YES (CRITICAL issues) | NO (HIGH/MEDIUM only)
`;

    return { content: [{ type: 'text', text: `${checklist}\n\n${'─'.repeat(60)}\nBACKEND SOURCE FILES\n${'─'.repeat(60)}\n${snapshot}` }] };
  }

  // ── review_frontend ────────────────────────────────────────────────────────
  if (name === 'review_frontend') {
    const snapshot = readFrontendFiles(FRONTEND, args?.focus);

    const checklist = `
═══ FRONTEND CODE REVIEW CHECKLIST (React/TypeScript — adapted from nashmi_v2 A8b) ════

TOLERANCE RULES — never flag these:
  ✓ Arabic translation differences — any Arabic text is acceptable
  ✓ Extra client-side validation beyond what backend requires
  ✓ Aesthetic/style differences
  ✓ Using textContent vs innerHTML for safe (non-HTML) data
  ✓ Extra console.log() statements
  ✓ Template literals vs string concatenation
  ✓ Extra helper functions

Real bugs to find:
  ✗ Wrong API endpoint URL (e.g. /submissions instead of /engineer/submissions) → 404
  ✗ Wrong HTTP method (POST instead of PATCH for update) → 405
  ✗ Auto-generated fields in create POST body (id, created_at, status) → 422 on every submit
  ✗ Missing required fields in POST body → 422 with validation error
  ✗ Missing Authorization: Bearer token header → 401 Unauthenticated
  ✗ No dir="rtl" on root element → layout broken for Arabic
  ✗ TypeScript interface field names not matching actual API response shape
  ✗ useRef / getElementById targeting an element that doesn't exist in JSX

── CHECK 1: API ENDPOINT URLS
   Does every fetch/axios call URL match an actual route in backend/routes/api.php?
   Quote both: frontend URL and backend route side by side.

── CHECK 2: HTTP METHODS
   Is each operation using the correct method?
   Create → POST, Update → PUT/PATCH, Delete → DELETE, Read → GET.

── CHECK 3: CREATE REQUEST BODY FIELDS
   List every key in the create POST body object.
   Verify all required fields are present and NO auto-generated fields are included:
   (id, created_at, updated_at, status, reference_number, approved_at, approved_by, etc.)

── CHECK 4: AUTH TOKEN HEADER
   Is Authorization: Bearer ${'{token}'} sent on every authenticated request?
   Is the token read from localStorage key 'jea_token'?
   Missing = 401 Unauthenticated on every API call.

── CHECK 5: UPDATE / STATUS CHANGE REQUEST
   Does the status update call use PATCH?
   Does the request body field names match the backend's FormRequest rules?

── CHECK 6: ERROR HANDLING
   Is there a try/catch around every fetch/axios call?
   Is there a user-visible error display (not just console.error)?

── CHECK 7: RTL & ARABIC COMPLIANCE
   Is dir="rtl" set on the root element or <html>?
   Is Arabic text used for labels, buttons, and headings?

── CHECK 8: ELEMENT REFS
   For every document.getElementById() or useRef access,
   verify the target id/ref exists in the JSX of the same component.
   Missing id → TypeError: Cannot read property of null.

── CHECK 9: RESPONSE FIELD NAMES
   For every API response field accessed (e.g. data.submission_id, data.status):
   Verify it matches the actual field name returned by the API Resource.
   Common mistake: accessing data.id when API returns data.submission_id.

── CHECK 10: TYPESCRIPT INTERFACE ALIGNMENT
   For key interfaces (SubmissionData, DashboardStats, ActivityLog, etc.):
   Do field names and types match what the backend API Resource actually returns?
   Wrong types = silent runtime errors or TypeScript errors.

OUTPUT FORMAT:
  FRONTEND_QA_STATUS: PASS | FAIL
  For each issue:
    ISSUE[N]: Severity (CRITICAL|HIGH|MEDIUM) | Check N | File | Problem | Fix
  End with: FRONTEND_FIX_NEEDED: YES (CRITICAL issues) | NO (HIGH/MEDIUM only)
`;

    return { content: [{ type: 'text', text: `${checklist}\n\n${'─'.repeat(60)}\nFRONTEND SOURCE FILES\n${'─'.repeat(60)}\n${snapshot}` }] };
  }

  throw new Error(`Unknown tool: ${name}`);
});

// ── File readers ──────────────────────────────────────────────────────────────

function readBackendFiles(backendPath, focus) {
  const targets = [
    'routes/api.php',
    'app/Http/Controllers/Api/SubmissionController.php',
    'app/Http/Controllers/Api/DashboardController.php',
    'app/Http/Controllers/Api/IntegrationController.php',
    'app/Services/SubmissionService.php',
    'app/Models/Submission.php',
    'app/Models/AuditLog.php',
    'app/Http/Resources/SubmissionResource.php',
    'config/cors.php',
  ];

  // If focus provided, also scan for matching files
  const extra = focus ? findFiles(backendPath, focus) : [];
  const all   = [...new Set([...targets, ...extra])];

  return all.map(rel => {
    const full = join(backendPath, rel);
    if (!existsSync(full)) return null;
    try {
      const content = readFileSync(full, 'utf8');
      return `### ${rel}\n\`\`\`php\n${content}\n\`\`\``;
    } catch { return null; }
  }).filter(Boolean).join('\n\n') || '(no backend files found)';
}

function readFrontendFiles(frontendPath, focus) {
  const targets = [
    'src/api/authApi.ts',
    'src/api/submissionApi.ts',
    'src/api/drawingApi.ts',
    'src/contexts/AuthContext.tsx',
    'src/pages/EngineerDashboard.tsx',
    'src/pages/AuditorDashboard.tsx',
    'src/pages/AdminDashboard.tsx',
    'src/components/SubmissionForm.tsx',
    'src/types/index.ts',
  ];

  const extra = focus ? findFiles(frontendPath + '/src', focus) : [];
  const all   = [...new Set([...targets, ...extra])];

  return all.map(rel => {
    const full = join(frontendPath, rel);
    if (!existsSync(full)) return null;
    try {
      const content = readFileSync(full, 'utf8');
      const ext = rel.split('.').pop();
      return `### ${rel}\n\`\`\`${ext}\n${content}\n\`\`\``;
    } catch { return null; }
  }).filter(Boolean).join('\n\n') || '(no frontend files found)';
}

function findFiles(dir, keyword, results = [], depth = 0) {
  if (!existsSync(dir) || depth > 4) return results;
  for (const f of readdirSync(dir)) {
    const full = join(dir, f);
    if (statSync(full).isDirectory()) findFiles(full, keyword, results, depth + 1);
    else if (f.toLowerCase().includes(keyword.toLowerCase())) {
      // Return relative to the base dir
      results.push(full.replace(dir + '/', ''));
    }
  }
  return results;
}

const transport = new StdioServerTransport();
await server.connect(transport);
