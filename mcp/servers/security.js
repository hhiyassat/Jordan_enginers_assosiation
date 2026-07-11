/**
 * Security MCP Server
 * Tools: scan_composer_vulnerabilities, scan_npm_vulnerabilities,
 *        find_hardcoded_secrets, audit_auth_routes, check_env_exposure,
 *        generate_security_report
 */
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { execSync } from 'child_process';
import { readFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const BACKEND  = process.env.BACKEND_PATH  ?? './backend';
const FRONTEND = process.env.FRONTEND_PATH ?? './frontend';

function shell(cmd, cwd) {
  try { return { success: true, output: execSync(cmd, { cwd, encoding: 'utf8', timeout: 60000 }) }; }
  catch (e) { return { success: false, output: e.stdout ?? '', error: e.stderr ?? e.message }; }
}

function walkFiles(dir, ext = [], results = []) {
  try {
    for (const f of readdirSync(dir)) {
      const full = join(dir, f);
      if (['node_modules','vendor','.git','storage'].includes(f)) continue;
      if (statSync(full).isDirectory()) walkFiles(full, ext, results);
      else if (ext.some(e => full.endsWith(e))) results.push(full);
    }
  } catch {}
  return results;
}

const SECRET_PATTERNS = [
  { name: 'Hardcoded password', pattern: /password\s*=\s*['"][^'"]{4,}['"]/gi },
  { name: 'API key literal',    pattern: /api[_-]?key\s*[:=]\s*['"][A-Za-z0-9]{16,}['"]/gi },
  { name: 'Bearer token',       pattern: /Bearer\s+[A-Za-z0-9_\-\.]{20,}/g },
  { name: 'Private key',        pattern: /-----BEGIN (RSA |EC )?PRIVATE KEY-----/g },
  { name: 'AWS key',            pattern: /AKIA[0-9A-Z]{16}/g },
  { name: 'DB password in code',pattern: /DB_PASSWORD\s*=\s*[^\s]{4,}/g },
];

const server = new Server(
  { name: 'esp-security', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    { name: 'scan_composer_vulnerabilities', description: 'Run composer audit to find known CVEs in PHP dependencies.', inputSchema: { type: 'object', properties: {} } },
    { name: 'scan_npm_vulnerabilities',      description: 'Run npm audit to find known CVEs in JS dependencies.',     inputSchema: { type: 'object', properties: {} } },
    {
      name: 'find_hardcoded_secrets',
      description: 'Scan all source files for hardcoded passwords, API keys, tokens, and secrets.',
      inputSchema: { type: 'object', properties: {
        path: { type: 'string', description: 'Directory to scan (default: both backend and frontend src)' }
      }}
    },
    { name: 'audit_auth_routes',   description: 'Parse routes/api.php and flag any routes that lack authentication middleware.', inputSchema: { type: 'object', properties: {} } },
    { name: 'check_env_exposure',  description: 'Check that .env is gitignored and no secrets appear in committed files.', inputSchema: { type: 'object', properties: {} } },
    { name: 'check_cors_config',   description: 'Review CORS config and flag overly permissive settings.', inputSchema: { type: 'object', properties: {} } },
    { name: 'generate_security_report', description: 'Run all security checks and produce a full security report.', inputSchema: { type: 'object', properties: {} } },
  ]
}));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;

  if (name === 'scan_composer_vulnerabilities') {
    const r = shell('composer audit --format=json 2>&1 || true', BACKEND);
    try {
      const data = JSON.parse(r.output);
      const advisories = data.advisories ?? {};
      const count = Object.keys(advisories).length;
      if (count === 0) return { content: [{ type: 'text', text: '✅ No known PHP vulnerabilities found.' }] };
      const lines = Object.entries(advisories).map(([pkg, list]) =>
        list.map(a => `❌ ${pkg}: ${a.title} (${a.cve ?? 'no CVE'}) — ${a.link}`).join('\n')
      ).flat();
      return { content: [{ type: 'text', text: `Found ${count} vulnerable package(s):\n\n${lines.join('\n')}` }] };
    } catch {
      return { content: [{ type: 'text', text: r.output }] };
    }
  }

  if (name === 'scan_npm_vulnerabilities') {
    const r = shell('npm audit --json 2>&1 || true', FRONTEND);
    try {
      const data = JSON.parse(r.output);
      const total = data.metadata?.vulnerabilities;
      if (!total) return { content: [{ type: 'text', text: r.output }] };
      const summary = Object.entries(total).map(([sev, n]) => `${sev}: ${n}`).join('  ');
      return { content: [{ type: 'text', text: `NPM Audit — ${summary}\n\nRun: npm audit fix` }] };
    } catch {
      return { content: [{ type: 'text', text: r.output }] };
    }
  }

  if (name === 'find_hardcoded_secrets') {
    const scanDir = args?.path ?? null;
    const files = [
      ...walkFiles(scanDir ?? `${BACKEND}/app`, ['.php']),
      ...walkFiles(scanDir ?? `${FRONTEND}/src`, ['.ts', '.tsx', '.js']),
    ];
    const findings = [];
    for (const file of files) {
      const content = readFileSync(file, 'utf8');
      for (const { name: patName, pattern } of SECRET_PATTERNS) {
        const matches = content.match(pattern);
        if (matches) findings.push(`${file}: [${patName}] ${matches[0].substring(0, 60)}`);
      }
    }
    if (findings.length === 0) return { content: [{ type: 'text', text: '✅ No hardcoded secrets found.' }] };
    return { content: [{ type: 'text', text: `⚠️ Potential secrets found:\n\n${findings.join('\n')}` }] };
  }

  if (name === 'audit_auth_routes') {
    const routesFile = `${BACKEND}/routes/api.php`;
    try {
      const content = readFileSync(routesFile, 'utf8');
      const lines = content.split('\n');
      const unprotected = [];
      let inAuth = false;
      lines.forEach((line, i) => {
        if (line.includes("auth:sanctum")) inAuth = true;
        if (line.includes("Route::post") || line.includes("Route::get")) {
          if (!inAuth && !line.includes("'public'") && !line.includes('login') &&
              !line.includes('active') && !line.includes('receive-') &&
              !line.includes('qr-verify') && !line.includes('platform/config')) {
            unprotected.push(`Line ${i + 1}: ${line.trim()}`);
          }
        }
        if (line.trim() === '});') inAuth = false;
      });
      if (unprotected.length === 0) return { content: [{ type: 'text', text: '✅ All routes appear to have proper auth.' }] };
      return { content: [{ type: 'text', text: `⚠️ Potentially unprotected routes:\n\n${unprotected.join('\n')}` }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error reading routes: ${e.message}` }] };
    }
  }

  if (name === 'check_env_exposure') {
    const gitignore = shell('cat .gitignore 2>/dev/null || true', BACKEND);
    const envInGit  = shell('git log --all --oneline -- .env 2>/dev/null | head -5', BACKEND);
    const lines = [
      gitignore.output.includes('.env') ? '✅ .env is in .gitignore' : '❌ .env NOT in .gitignore!',
      envInGit.output.trim() ? `⚠️ .env appears in git history:\n${envInGit.output}` : '✅ .env has no git history',
    ];
    return { content: [{ type: 'text', text: lines.join('\n') }] };
  }

  if (name === 'check_cors_config') {
    try {
      const cors = readFileSync(`${BACKEND}/config/cors.php`, 'utf8');
      const issues = [];
      if (cors.includes("'*'")) issues.push('⚠️ Wildcard (*) in CORS allowed_origins');
      if (!cors.includes('localhost:5173') && !cors.includes('127.0.0.1')) issues.push('ℹ️ No localhost origin configured');
      return { content: [{ type: 'text', text: issues.length ? issues.join('\n') : '✅ CORS config looks reasonable.' }] };
    } catch (e) {
      return { content: [{ type: 'text', text: `Error: ${e.message}` }] };
    }
  }

  if (name === 'generate_security_report') {
    const composer = shell('composer audit 2>&1 | tail -5', BACKEND);
    const npm      = shell('npm audit --json 2>&1 || true', FRONTEND);
    const gitignore= shell('grep -l ".env" .gitignore 2>/dev/null | wc -l', BACKEND);
    let npmSummary = 'unavailable';
    try { const d = JSON.parse(npm.output); npmSummary = JSON.stringify(d.metadata?.vulnerabilities ?? {}); } catch {}
    const report = [
      `# Security Report — ${new Date().toISOString()}`,
      '',
      `## PHP Dependencies`,
      composer.success ? '✅ No critical issues' : `⚠️ ${composer.output.split('\n').slice(-3).join('\n')}`,
      '',
      `## NPM Dependencies`,
      `Vulnerabilities: ${npmSummary}`,
      '',
      `## .env Protection`,
      gitignore.output.trim() === '1' ? '✅ .env gitignored' : '❌ Check .gitignore!',
      '',
      `## Recommendations`,
      '- Rotate INTEGRATION_KEY and NASHMI_INTEGRATION_KEY before production',
      '- Enable HTTPS and set APP_ENV=production',
      '- Set SANCTUM_STATEFUL_DOMAINS to production domain only',
      '- Enable rate limiting on /api/auth/login',
    ].join('\n');
    return { content: [{ type: 'text', text: report }] };
  }

  throw new Error(`Unknown tool: ${name}`);
});

const transport = new StdioServerTransport();
await server.connect(transport);
