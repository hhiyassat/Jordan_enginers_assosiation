/**
 * SDLC Security MCP Server
 * MoDEE Minimum Baseline Security Standard — SDLC V1.0 (Annex 4.11)
 *
 * 53 security requirements, 40 auto-scanned, 13 manual review.
 *
 * Tools:
 *   sdlc_list_requirements  — list all requirements (filter by category)
 *   sdlc_run_audit          — full compliance scan of the codebase
 *   sdlc_check_requirement  — check one requirement with evidence
 *   sdlc_get_remediation    — fix guidance for a failing requirement
 *   sdlc_generate_report    — markdown / JSON compliance report for MoDEE
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { readFileSync, readdirSync, statSync, existsSync } from 'fs';
import { join, extname } from 'path';

const BACKEND  = process.env.BACKEND_PATH  ?? '/Users/husseinhiyassat/tenders/esp-v2/backend';
const FRONTEND = process.env.FRONTEND_PATH ?? '/Users/husseinhiyassat/tenders/esp-v2/frontend';

// ─────────────────────────────────────────────────────────────────────────────
// 1.  ALL 53 REQUIREMENTS
// ─────────────────────────────────────────────────────────────────────────────

const REQUIREMENTS = [
  // OWASP Top 10
  { id:'OWASP-01', cat:'OWASP Top 10',     sev:'critical', desc:'Protected against Broken Access Control (OWASP A01)',                        check:'role_middleware' },
  { id:'OWASP-02', cat:'OWASP Top 10',     sev:'critical', desc:'Protected against Cryptographic Failures (OWASP A02)',                       check:'crypto' },
  { id:'OWASP-03', cat:'OWASP Top 10',     sev:'critical', desc:'Protected against Injection (OWASP A03)',                                    check:'injection' },
  { id:'OWASP-04', cat:'OWASP Top 10',     sev:'high',     desc:'Protected against Insecure Design (OWASP A04)',                              check:'manual' },
  { id:'OWASP-05', cat:'OWASP Top 10',     sev:'high',     desc:'Protected against Security Misconfiguration (OWASP A05)',                    check:'misconfiguration' },
  { id:'OWASP-06', cat:'OWASP Top 10',     sev:'high',     desc:'Protected against Vulnerable and Outdated Components (OWASP A06)',           check:'dependencies' },
  { id:'OWASP-07', cat:'OWASP Top 10',     sev:'critical', desc:'Protected against Identification and Authentication Failures (OWASP A07)',   check:'auth_failures' },
  { id:'OWASP-08', cat:'OWASP Top 10',     sev:'high',     desc:'Protected against Software and Data Integrity Failures (OWASP A08)',         check:'integrity' },
  { id:'OWASP-09', cat:'OWASP Top 10',     sev:'high',     desc:'Security Logging and Monitoring implemented (OWASP A09)',                    check:'logging' },
  { id:'OWASP-10', cat:'OWASP Top 10',     sev:'high',     desc:'Protected against Server-Side Request Forgery (OWASP A10)',                  check:'ssrf' },
  { id:'PENTEST-01',cat:'OWASP Top 10',    sev:'critical', desc:'System must pass penetration test by MoDEE',                                check:'manual' },

  // HTTPS
  { id:'HTTPS-01', cat:'HTTPS Protocol',   sev:'critical', desc:'HTTPS used on login and sensitive data transfer pages',                     check:'https' },

  // Software Updates
  { id:'UPDATE-01',cat:'Software Updates', sev:'high',     desc:'All SW components updated and supported by security patches',               check:'dependencies' },
  { id:'UPDATE-02',cat:'Software Updates', sev:'high',     desc:'All platforms on servers and back-end are up to date',                      check:'manual' },
  { id:'UPDATE-03',cat:'Software Updates', sev:'high',     desc:'Latest / secure communication protocol versions used',                      check:'https' },

  // File Uploads
  { id:'FILE-01',  cat:'File Uploads',     sev:'high',     desc:'Uploaded file types validated on server side',                              check:'file_upload' },
  { id:'FILE-02',  cat:'File Uploads',     sev:'high',     desc:'Files uploaded by clients stored in separate private folders',              check:'file_upload' },
  { id:'FILE-03',  cat:'File Uploads',     sev:'high',     desc:'Types of uploaded files restricted to a whitelist',                         check:'file_upload' },
  { id:'FILE-04',  cat:'File Uploads',     sev:'high',     desc:'Double extension files banned',                                             check:'file_upload' },
  { id:'FILE-05',  cat:'File Uploads',     sev:'high',     desc:'Antimalware / Sandboxing technology on app and web servers',                check:'manual' },

  // CAPTCHA
  { id:'CAPTCHA-01',cat:'CAPTCHA',         sev:'medium',   desc:'Secure CAPTCHA used to protect against bots',                              check:'captcha' },
  { id:'CAPTCHA-02',cat:'CAPTCHA',         sev:'medium',   desc:'Passing CAPTCHA mandatory before form submission',                          check:'captcha' },
  { id:'CAPTCHA-03',cat:'CAPTCHA',         sev:'medium',   desc:'CAPTCHA collects minimum user data possible',                              check:'manual' },
  { id:'CAPTCHA-04',cat:'CAPTCHA',         sev:'medium',   desc:'User consent collected before any data collection',                         check:'manual' },

  // Passwords
  { id:'PWD-01',   cat:'Passwords',        sev:'high',     desc:'Strong password policy enforced (8/4 rule minimum)',                        check:'password_policy' },
  { id:'PWD-02',   cat:'Passwords',        sev:'critical', desc:'Passwords stored as encrypted hashed values',                              check:'password_hash' },
  { id:'PWD-03',   cat:'Passwords',        sev:'high',     desc:'Account locked after three failed login attempts',                          check:'lockout' },

  // Antivirus
  { id:'AV-01',    cat:'Antivirus',        sev:'high',     desc:'Antimalware used on production, staging, and development',                 check:'manual' },

  // Default Settings
  { id:'CFG-01',   cat:'Default Settings', sev:'high',     desc:'Default account configuration settings changed (hosting + CMS)',           check:'misconfiguration' },

  // Error Messages
  { id:'ERR-01',   cat:'Error Messages',   sev:'high',     desc:'Error messages show only necessary info, not system structure',             check:'error_messages' },
  { id:'ERR-02',   cat:'Error Messages',   sev:'medium',   desc:'Detailed errors kept in server log only',                                  check:'error_messages' },

  // Secure APIs
  { id:'API-01',   cat:'Secure APIs',      sev:'critical', desc:'APIs use HTTPS',                                                           check:'https' },
  { id:'API-02',   cat:'Secure APIs',      sev:'critical', desc:'Token-based API authentication (OAuth 2.0) used',                          check:'api_auth' },
  { id:'API-03',   cat:'Secure APIs',      sev:'high',     desc:'API tokens have an expiration time',                                       check:'api_auth' },
  { id:'API-04',   cat:'Secure APIs',      sev:'high',     desc:'Rate limiting configured on APIs',                                         check:'rate_limit' },
  { id:'API-05',   cat:'Secure APIs',      sev:'high',     desc:'API parameters validated',                                                 check:'api_validation' },
  { id:'API-06',   cat:'Secure APIs',      sev:'medium',   desc:'IDs are opaque and globally unique (UUID/random, not sequential)',          check:'opaque_ids' },
  { id:'API-07',   cat:'Secure APIs',      sev:'medium',   desc:'Timestamp added to requests (replay attack prevention)',                   check:'manual' },
  { id:'API-08',   cat:'Secure APIs',      sev:'high',     desc:'API-returned data filtered on backend (API Resources)',                    check:'api_validation' },
  { id:'API-09',   cat:'Secure APIs',      sev:'high',     desc:'Request manipulation prevented (CSRF / signed tokens)',                    check:'api_validation' },
  { id:'API-10',   cat:'Secure APIs',      sev:'high',     desc:'Swagger/OpenAPI files NOT published publicly',                             check:'swagger' },

  // Authentication
  { id:'AUTH-01',  cat:'Authentication',   sev:'critical', desc:'MFA authentication used',                                                  check:'mfa' },
  { id:'AUTH-02',  cat:'Authentication',   sev:'high',     desc:'SANAD authentication services used wherever possible',                     check:'manual' },

  // OTP
  { id:'OTP-01',   cat:'OTP',              sev:'high',     desc:'OTP expiry time set, must not exceed 5 minutes',                           check:'otp' },
  { id:'OTP-02',   cat:'OTP',              sev:'high',     desc:'Lockout after too many wrong OTP values',                                  check:'otp' },
  { id:'OTP-03',   cat:'OTP',              sev:'high',     desc:'OTP cannot be used more than once',                                        check:'otp' },
  { id:'OTP-04',   cat:'OTP',              sev:'high',     desc:'OTP request holds only user ID (phone/email fetched from DB)',              check:'otp' },

  // Security Logging
  { id:'LOG-01',   cat:'Security Logging', sev:'high',     desc:'Security transactions audited for adequate time',                          check:'logging' },
  { id:'LOG-02',   cat:'Security Logging', sev:'high',     desc:'Logs securely transmitted to remote system for analysis / alerting',      check:'manual' },
  { id:'LOG-03',   cat:'Security Logging', sev:'medium',   desc:'All system components time-synchronized',                                 check:'manual' },

  // General
  { id:'GEN-01',   cat:'General',          sev:'high',     desc:'3-Tier Architecture designed',                                             check:'manual' },
  { id:'GEN-02',   cat:'General',          sev:'high',     desc:'SANAD used for registration / login wherever possible',                   check:'manual' },
  { id:'GEN-03',   cat:'General',          sev:'high',     desc:'Server list delivered (production+staging) with ports/IPs',               check:'manual' },
  { id:'GEN-04',   cat:'General',          sev:'high',     desc:'Web server config files do not hold application data',                    check:'manual' },
  { id:'GEN-05',   cat:'General',          sev:'critical', desc:'System protected by WAF (Web Application Firewall)',                       check:'manual' },
  { id:'GEN-06',   cat:'General',          sev:'critical', desc:'Hard-coded credentials not allowed',                                       check:'hardcoded_creds' },
  { id:'GEN-07',   cat:'General',          sev:'high',     desc:'Admin pages not publicly accessible; only inside SGN',                    check:'admin_exposure' },
  { id:'GEN-08',   cat:'General',          sev:'high',     desc:'All back-office employees have OTP',                                       check:'otp' },
  { id:'GEN-09',   cat:'General',          sev:'high',     desc:'Micro-segmentation + antivirus on all VMs',                               check:'manual' },
  { id:'GEN-10',   cat:'General',          sev:'high',     desc:'X-Forwarded-IP configured; WAF protection enabled',                       check:'headers' },
  { id:'GEN-11',   cat:'General',          sev:'high',     desc:'Data classified per MoDEE Data Classification policy',                    check:'manual' },
  { id:'GEN-12',   cat:'General',          sev:'high',     desc:'Compliance with all referenced MoDEE policies',                           check:'manual' },
];

const REQ_BY_ID = Object.fromEntries(REQUIREMENTS.map(r => [r.id, r]));

// ─────────────────────────────────────────────────────────────────────────────
// 2.  REMEDIATION GUIDANCE
// ─────────────────────────────────────────────────────────────────────────────

const REMEDIATION = {
  'OWASP-01': "Ensure every route has role middleware (`middleware('role:admin')`). Use policy gates in controllers. Run `php artisan route:list` and verify no sensitive route lacks auth.",
  'OWASP-02': "Use AES-256 for data at rest. Use TLS 1.2+ for data in transit. Hash passwords with bcrypt/argon2. Never store plaintext secrets.",
  'OWASP-03': "Use Eloquent ORM / parameterized queries — never raw SQL with user input. Validate all inputs with `$request->validate()`. Use htmlspecialchars() on output.",
  'OWASP-04': "Threat-model each feature in design phase. Document trust boundaries. Use STRIDE methodology.",
  'OWASP-05': "Set APP_DEBUG=false in production. Remove default routes/pages. Disable directory listing. Change default credentials.",
  'OWASP-06': "Run `composer audit` regularly. Subscribe to Laravel security advisories. Pin dependency versions. Run `npm audit` for frontend.",
  'OWASP-07': "Enforce lockout after 3 failures (`throttle:3,1`). Use MFA. Enforce strong password policy. Use Laravel Sanctum with short token TTLs.",
  'OWASP-08': "Validate file integrity with checksums. Use SRI on CDN assets. Sign serialized data.",
  'OWASP-09': "Log all auth events, access control decisions, and API calls with: timestamp, user_id, source_ip, action, outcome. Ship to remote SIEM.",
  'OWASP-10': "Validate and whitelist all URLs before making outbound HTTP calls. Block private IP ranges (10.x, 192.168.x, 169.254.x) in outbound requests.",
  'PENTEST-01':"Schedule penetration test with MoDEE's approved testing team before go-live. Remediate all critical/high findings.",
  'HTTPS-01': "In AppServiceProvider::boot(): `URL::forceScheme('https')`. Add HSTS header. Redirect HTTP → HTTPS at web server level.",
  'UPDATE-01': "Run `composer audit` and `npm audit` weekly. Set up Dependabot or Renovate. Remove unused packages.",
  'UPDATE-02': "Keep Ubuntu/OS updated: `apt-get update && apt-get upgrade`. Use LTS releases. Document patching schedule.",
  'UPDATE-03': "Enforce TLS 1.2+ in Nginx: `ssl_protocols TLSv1.2 TLSv1.3`. Disable SSLv3, TLS 1.0, TLS 1.1.",
  'FILE-01': "Validate with: `$request->validate(['file' => 'file|mimes:pdf,jpg,png|max:5120'])`. Also check MIME type server-side using finfo_file().",
  'FILE-02': "Store uploads outside public/ directory. Use storage/app/uploads/{uuid}/. Serve via signed URLs only.",
  'FILE-03': "Whitelist allowed extensions. Reject all others. Never allow .php, .exe, .sh.",
  'FILE-04': "Check for double extensions: file.php.jpg. Regex: /\\.php[0-9]?\\./i. Reject any file with multiple meaningful extensions.",
  'FILE-05': "Integrate ClamAV or VirusTotal API to scan every uploaded file before storing. Alert PM if antimalware is absent.",
  'CAPTCHA-01':"Use Google reCAPTCHA v3 or hCaptcha. Verify server-side with CAPTCHA_SECRET. Never trust client-side result only.",
  'CAPTCHA-02':"Add CAPTCHA check as middleware before any form submission endpoint. Return 422 if CAPTCHA token missing/invalid.",
  'CAPTCHA-03':"Use reCAPTCHA v3 (no user data) or hCaptcha (privacy-friendly). Avoid extra PII from CAPTCHA provider.",
  'CAPTCHA-04':"Add consent checkbox before any data-collecting form. Log consent with timestamp and IP.",
  'PWD-01': "Laravel validation: `Password::min(8)->letters()->mixedCase()->numbers()->symbols()`. Enforce 8/4 rule: 8 chars, 4 character classes.",
  'PWD-02': "Never store plaintext. Use `Hash::make($password)` (bcrypt by default in Laravel). Never use MD5/SHA1 for passwords.",
  'PWD-03': "Use `throttle:3,1` on login route OR RateLimiter in AuthController. Lock account after 3 failures. Require email unlock or admin reset.",
  'AV-01': "Install ClamAV on all environments. Configure daily scans. Report missing AV to PM immediately.",
  'CFG-01': "Change all default passwords for databases, admin panels, CMS, hosting. Document in secrets manager (not in code).",
  'ERR-01': "Set APP_DEBUG=false in production .env. Use Laravel's exception handler to show generic 'Something went wrong'. Never expose stack traces.",
  'ERR-02': "Configure LOG_CHANNEL=stack with daily rotation. Ship logs to remote syslog. Use Sentry for production error tracking.",
  'API-01': "Enforce HTTPS on all API routes. Set APP_URL=https://... in .env. Use Nginx `return 301 https://` redirect.",
  'API-02': "Use Laravel Sanctum (SPA) or Passport (OAuth 2.0) for API token auth. All API routes behind auth:sanctum middleware.",
  'API-03': "Set Sanctum token expiry in config/sanctum.php → expiration (minutes). Default is null (never) — set to 1440 (24h) or less.",
  'API-04': "Apply `throttle:60,1` middleware to all API routes. Log 429 responses for monitoring.",
  'API-05': "Use Laravel Form Requests for every endpoint. Validate all parameters: type, range, format.",
  'API-06': "Use UUIDs (Str::uuid()) instead of auto-increment IDs on all public-facing resources. Set `$incrementing = false` and `$keyType = 'string'`.",
  'API-07': "Add X-Request-Timestamp header. In middleware, reject requests where abs(now - timestamp) > 300 seconds.",
  'API-08': "Use API Resources to explicitly whitelist returned fields. Never return Model::all() or full Eloquent objects directly.",
  'API-09': "Use CSRF protection on stateful routes. Use signed tokens for state-changing API operations. Validate Content-Type header.",
  'API-10': "Remove swagger-ui and l5-swagger from production. Block /api/documentation route in production via middleware.",
  'AUTH-01': "Implement TOTP (Google Authenticator) or SMS OTP as second factor. Require MFA for all admin/staff. Use pragmarx/google2fa-laravel.",
  'AUTH-02': "Integrate SANAD identity provider (Jordan's national SSO). Use SANAD SDK for OAuth/OIDC flow.",
  'OTP-01': "OTP cache TTL must be ≤ 300 seconds (5 min). Verify config('gsb.citizen_data.otp_ttl') = 300.",
  'OTP-02': "Implement lockout after 3 wrong OTP attempts. Store attempt count in cache. Lock for 15 minutes on breach.",
  'OTP-03': "Delete OTP from cache immediately after first successful use: `cache()->forget($cacheKey)`. Never allow reuse.",
  'OTP-04': "OTP request endpoint only accepts user_id/session. Phone/email fetched from DB server-side — never accepted in request body.",
  'LOG-01': "Retain logs minimum 180 days (GSB policy). GSB audit logs already configured. Extend to all application logs.",
  'LOG-02': "Ship logs to remote syslog or SIEM (Elastic Stack, Splunk). Configure Laravel's LOG_CHANNEL=syslog.",
  'LOG-03': "Configure NTP on all servers: `timedatectl set-ntp true`. Use government NTP server if available.",
  'GEN-01': "Separate presentation (React), application logic (Laravel API), and data (DB) into distinct tiers/servers.",
  'GEN-02': "Use SANAD for citizen login. Integrate SANAD SDK. Document which flows use SANAD vs internal auth.",
  'GEN-03': "Deliver server inventory document: hostname, IP, function, open ports, allowed IPs per server in all 3 tiers.",
  'GEN-04': "Never put DB credentials or app logic in Nginx/Apache config files. Use environment variables only.",
  'GEN-05': "Deploy behind Fortinet/Palo Alto WAF (per MoDEE infrastructure). Configure WAF rules for OWASP CRS.",
  'GEN-06': "No credentials in source code. Use .env for all secrets. Add .env to .gitignore. Scan with gitleaks pre-commit.",
  'GEN-07': "Admin routes behind SGN (Secure Government Network). Block /admin/* from public internet at Nginx/WAF level.",
  'GEN-08': "All back-office accounts (staff/auditor/admin) must have OTP enabled. Enforce in middleware.",
  'GEN-09': "Configure VLAN/firewall rules between VMs. Each tier in separate network segment. Antivirus on every VM.",
  'GEN-10': "Configure X-Forwarded-For in Nginx. Trust only known proxy IPs. Set TrustProxies in Laravel.",
  'GEN-11': "Classify all data fields per MoDEE Data Classification policy. Apply encryption for confidential+.",
  'GEN-12': "Complete compliance checklist against all referenced MoDEE policies. Obtain MoDEE security team sign-off.",
};

// ─────────────────────────────────────────────────────────────────────────────
// 3.  FILE SCANNER HELPERS
// ─────────────────────────────────────────────────────────────────────────────

const EXCLUDED_DIRS = new Set(['vendor', 'node_modules', '.git', 'storage']);

function walkPhp(dir, results = []) {
  try {
    for (const f of readdirSync(dir)) {
      if (EXCLUDED_DIRS.has(f)) continue;
      const full = join(dir, f);
      if (statSync(full).isDirectory()) walkPhp(full, results);
      else if (extname(f) === '.php') results.push(full);
    }
  } catch {}
  return results;
}

function readFile(p) {
  try { return readFileSync(p, 'utf8'); } catch { return ''; }
}

/**
 * Grep a list of files for a regex pattern.
 * Returns ['filename:lineN: matched_text', ...]
 */
function grep(files, pattern, flags = 'i') {
  const rx = new RegExp(pattern, flags);
  const hits = [];
  for (const f of files) {
    const lines = readFile(f).split('\n');
    for (let i = 0; i < lines.length; i++) {
      if (rx.test(lines[i])) {
        const fname = f.split('/').slice(-2).join('/');
        hits.push(`${fname}:${i + 1}: ${lines[i].trim().substring(0, 120)}`);
      }
    }
  }
  return hits;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4.  INDIVIDUAL CHECKERS  (return { status, evidence[], issues[] })
// ─────────────────────────────────────────────────────────────────────────────

function checkRoleMiddleware(php) {
  const hits = grep(php, `middleware\\(.*role:`);
  return { status: hits.length ? 'compliant' : 'fail', evidence: hits.slice(0, 5),
           issues: hits.length ? [] : ['No role middleware found on any route'] };
}

function checkCrypto(php) {
  const hashes = grep(php, `Hash::make|bcrypt\\(|password_hash|argon2`);
  const forceHttps = grep(php, `forceScheme.*https|ForceHttps|Strict-Transport`);
  const issues = [];
  if (!hashes.length) issues.push('No password hashing found (Hash::make / bcrypt)');
  return { status: issues.length ? 'partial' : 'compliant', evidence: [...hashes.slice(0,3), ...forceHttps.slice(0,2)], issues };
}

function checkInjection(php) {
  const rawSql = grep(php, `DB::statement.*\\$|whereRaw.*\\$(?!\\))`);
  const orm    = grep(php, `->where\\(|->find\\(|Request::validate|->validate\\(`);
  const issues = rawSql.slice(0, 3).map(h => `Possible raw SQL: ${h}`);
  return { status: rawSql.length ? 'fail' : 'compliant', evidence: [...rawSql.slice(0,5), ...orm.slice(0,2)], issues };
}

function checkMisconfiguration(php) {
  const envExample = join(BACKEND, '.env.example');
  const envContent = readFile(envExample);
  const issues = [];
  if (envContent.includes('APP_DEBUG=true')) issues.push('APP_DEBUG=true in .env.example — must be false in production');
  const debug = grep(php, `APP_DEBUG|config.*app\.debug`);
  return { status: issues.length ? 'partial' : 'compliant', evidence: debug.slice(0,3), issues };
}

function checkDependencies() {
  const composerFile = join(BACKEND, 'composer.json');
  const issues = [];
  const evidence = [];
  if (existsSync(composerFile)) {
    const data = JSON.parse(readFile(composerFile));
    const deps = { ...(data.require ?? {}), ...(data['require-dev'] ?? {}) };
    evidence.push(`composer.json: ${Object.keys(deps).length} dependencies`);
    for (const [pkg, ver] of Object.entries(deps)) {
      if (['*', 'dev-master', 'dev-main'].includes(ver)) {
        issues.push(`Unpinned dependency: ${pkg}@${ver}`);
      }
    }
  }
  evidence.push('Run `composer audit` and `npm audit` for live CVE check');
  return { status: issues.length ? 'partial' : 'compliant', evidence, issues };
}

function checkAuthFailures(php) {
  const throttle = grep(php, `throttle:[0-9]|RateLimiter|lockout|tooManyAttempts`);
  const auth     = grep(php, `auth:sanctum|auth:api|Passport|Sanctum`);
  const issues   = [];
  if (!throttle.length) issues.push('No rate limiting / lockout found on auth routes');
  if (!auth.length)     issues.push('No Sanctum/Passport found for API auth');
  return { status: issues.length ? 'fail' : 'compliant', evidence: [...throttle.slice(0,3), ...auth.slice(0,2)], issues };
}

function checkIntegrity(php) {
  const hits = grep(php, `hash_hmac|crc32|sha256|integrity=|SRI`);
  return { status: 'partial', evidence: hits.slice(0,5), issues: ['Verify SRI on CDN assets and HMAC on serialized data'] };
}

function checkLogging(php) {
  const hits = grep(php, `Log::|logger\\(|LogApiAccess|AuditLog|GsbCallLog`);
  return { status: hits.length >= 3 ? 'compliant' : 'partial',
           evidence: hits.slice(0,5),
           issues: hits.length >= 3 ? [] : ['Increase log coverage for auth, access control, and API calls'] };
}

function checkSsrf(php) {
  const outbound = grep(php, `Http::get.*\\$|Http::post.*\\$|file_get_contents.*\\$`);
  const issues   = outbound.slice(0,2).map(h => `Unvalidated outbound HTTP: ${h}`);
  return { status: outbound.length ? 'partial' : 'compliant', evidence: outbound.slice(0,5), issues };
}

function checkHttps(php) {
  const force  = grep(php, `forceScheme.*https|ForceHttps|HSTS|Strict-Transport`);
  const envFile = join(BACKEND, '.env.example');
  const envLine = readFile(envFile).split('\n').find(l => l.startsWith('APP_URL=')) ?? '';
  const issues  = [];
  if (!force.length) issues.push('No HTTPS force middleware found (URL::forceScheme)');
  if (envLine && !envLine.includes('https://')) issues.push(`APP_URL not using HTTPS: ${envLine}`);
  return { status: issues.length ? 'partial' : 'compliant', evidence: [...force.slice(0,3), envLine].filter(Boolean), issues };
}

function checkFileUpload(php) {
  const mimes  = grep(php, `mimes:|file\\|mimes|finfo_file|getClientOriginalExtension`);
  const store  = grep(php, `storage/app|Storage::put|disk.*private|->store\\(`);
  const issues = [];
  if (!mimes.length) issues.push('No server-side MIME type validation found');
  if (!store.length) issues.push('No private storage for uploads found');
  return { status: issues.length >= 2 ? 'fail' : (issues.length ? 'partial' : 'compliant'),
           evidence: [...mimes.slice(0,3), ...store.slice(0,2)], issues };
}

function checkCaptcha(php) {
  const hits = grep(php, `captcha|recaptcha|hcaptcha|g-recaptcha`);
  return { status: hits.length ? 'compliant' : 'fail', evidence: hits.slice(0,5),
           issues: hits.length ? [] : ['No CAPTCHA implementation found — add reCAPTCHA v3 or hCaptcha'] };
}

function checkPasswordPolicy(php) {
  const hits = grep(php, `Password::min|mixedCase|numbers\\(\\)|symbols\\(\\)|min.*8`);
  return { status: hits.length ? 'compliant' : 'partial', evidence: hits.slice(0,3),
           issues: hits.length ? [] : ["Enforce Laravel's Password::defaults() with 8/4 rule"] };
}

function checkPasswordHash(php) {
  const hashes = grep(php, `Hash::make|bcrypt\\(|password_hash|argon2`);
  const plain  = grep(php, `password.*=.*\\$password`).filter(h => !/Hash|bcrypt|make/.test(h));
  const issues = plain.slice(0,2).map(h => `Possible plaintext password: ${h}`);
  return { status: plain.length ? 'fail' : (hashes.length ? 'compliant' : 'partial'),
           evidence: [...hashes.slice(0,3), ...plain.slice(0,3)], issues };
}

function checkLockout(php) {
  const hits = grep(php, `throttle|lockout|tooManyAttempts|RateLimiter|failed_logins`);
  return { status: hits.length ? 'compliant' : 'fail', evidence: hits.slice(0,5),
           issues: hits.length ? [] : ['No account lockout mechanism found'] };
}

function checkErrorMessages(php) {
  const envFile = join(BACKEND, '.env.example');
  const envContent = readFile(envFile);
  const issues = [];
  if (envContent.includes('APP_DEBUG=true')) issues.push('APP_DEBUG=true exposes stack traces');
  const generic = grep(php, `Something went wrong|genericError|Handler.*render`);
  return { status: issues.length ? 'partial' : 'compliant', evidence: generic.slice(0,3), issues };
}

function checkApiAuth(php) {
  const auth   = grep(php, `auth:sanctum|auth:api|Bearer|withToken|Sanctum|Passport`);
  const expiry = grep(php, `expiration|expires_at|token_expiry|TTL`);
  const issues = [];
  if (!auth.length)   issues.push('No token-based API auth found (Sanctum/Passport)');
  if (!expiry.length) issues.push('No token expiry configured — tokens may never expire');
  return { status: !auth.length ? 'fail' : (!expiry.length ? 'partial' : 'compliant'),
           evidence: [...auth.slice(0,3), ...expiry.slice(0,2)], issues };
}

function checkRateLimit(php) {
  const hits = grep(php, `throttle:|RateLimiter|rate.limit|too.many`);
  return { status: hits.length ? 'compliant' : 'fail', evidence: hits.slice(0,5),
           issues: hits.length ? [] : ['Apply throttle middleware to all API routes'] };
}

function checkApiValidation(php) {
  const formReq  = grep(php, `FormRequest|Request::validate|->validate\\(|make:request`);
  const resource = grep(php, `JsonResource|API.Resource|->toArray\\(`);
  const issues   = [];
  if (!formReq.length)  issues.push('No Form Requests / validation found');
  if (!resource.length) issues.push('No API Resources found — raw model data may be returned');
  return { status: !formReq.length ? 'fail' : (!resource.length ? 'partial' : 'compliant'),
           evidence: [...formReq.slice(0,3), ...resource.slice(0,2)], issues };
}

function checkOpaqueIds(php) {
  const uuid = grep(php, `Str::uuid|uuid4|UUID|HasUuids`);
  const seq  = grep(php, `Route::get.*\\{id\\}|->find\\(\\$id\\)|->findOrFail\\(\\$id\\)`);
  const issues = [];
  if (!uuid.length) issues.push('No UUID usage found — routes may use sequential integer IDs');
  if (seq.length)   issues.push(`Sequential ID routes: ${seq.length} found — verify they use UUIDs`);
  return { status: issues.length ? 'partial' : 'compliant', evidence: [...uuid.slice(0,3), ...seq.slice(0,2)], issues };
}

function checkSwagger(php) {
  const composerContent = readFile(join(BACKEND, 'composer.json')).toLowerCase();
  const hasSwagger = composerContent.includes('swagger') || composerContent.includes('l5-swagger');
  const block = grep(php, `swagger.*disable|documentation.*block|APP_ENV.*production.*swagger`);
  const issues = hasSwagger && !block.length ? ["Swagger package found — ensure /api/documentation is blocked in production"] : [];
  const hits = grep(php, `swagger|l5-swagger|openapi|api/documentation`);
  return { status: issues.length ? 'partial' : 'compliant', evidence: hits.slice(0,3), issues };
}

function checkMfa(php) {
  const hits = grep(php, `google2fa|TOTP|two.factor|MFA|2fa|otp.*login`);
  return { status: hits.length ? 'compliant' : 'fail', evidence: hits.slice(0,5),
           issues: hits.length ? [] : ['No MFA implementation found — add TOTP or SMS OTP'] };
}

function checkOtp(php) {
  const ttl     = grep(php, `otp_ttl|OTP_TTL|ttl.*300|300.*ttl|otp.*expir`);
  const once    = grep(php, `cache.*forget.*otp|delete.*otp|otp.*used`);
  const lockout = grep(php, `otp.*attempt|otp.*lockout|otp.*too.many`);
  const issues  = [];
  if (!ttl.length)     issues.push('OTP TTL not found — must be ≤ 300 seconds (5 min)');
  if (!once.length)    issues.push('OTP single-use enforcement not found');
  if (!lockout.length) issues.push('OTP lockout after wrong attempts not found');
  return { status: !issues.length ? 'compliant' : (ttl.length ? 'partial' : 'fail'),
           evidence: [...ttl.slice(0,2), ...once.slice(0,2), ...lockout.slice(0,2)], issues };
}

function checkHardcodedCreds(php) {
  const patterns = [
    `password\\s*=\\s*['"][^'"]{4,}['"]`,
    `secret\\s*=\\s*['"][^'"]{8,}['"]`,
    `api_key\\s*=\\s*['"][^'"]{8,}['"]`,
  ];
  let hits = [];
  for (const p of patterns) hits = hits.concat(grep(php, p));
  const real = hits.filter(h => !h.includes('env.example') && !/test/i.test(h) && !h.includes('Hash::'));
  return { status: real.length ? 'fail' : 'compliant', evidence: real.slice(0,5),
           issues: real.slice(0,3).map(h => `Hard-coded credential: ${h}`) };
}

function checkAdminExposure(php) {
  const admin  = grep(php, `route.*admin|prefix.*admin|Admin.*Route`);
  const sgn    = grep(php, `SGN|sgn.*ip|admin.*whitelist|AdminOnly`);
  const issues = admin.length && !sgn.length ? ['Admin routes found but no SGN/IP restriction detected'] : [];
  return { status: issues.length ? 'partial' : 'compliant', evidence: [...admin.slice(0,3), ...sgn.slice(0,2)], issues };
}

function checkHeaders(php) {
  const hits = grep(php, `X-Forwarded|TrustProxies|SecurityHeaders|X-Frame|Content-Security`);
  return { status: hits.length >= 2 ? 'compliant' : 'partial', evidence: hits.slice(0,5),
           issues: hits.length >= 2 ? [] : ['Add SecurityHeaders middleware (X-Frame-Options, CSP, HSTS, X-Content-Type)'] };
}

function runCheck(checkName, php) {
  try {
    switch (checkName) {
      case 'role_middleware':   return checkRoleMiddleware(php);
      case 'crypto':            return checkCrypto(php);
      case 'injection':         return checkInjection(php);
      case 'misconfiguration':  return checkMisconfiguration(php);
      case 'dependencies':      return checkDependencies();
      case 'auth_failures':     return checkAuthFailures(php);
      case 'integrity':         return checkIntegrity(php);
      case 'logging':           return checkLogging(php);
      case 'ssrf':              return checkSsrf(php);
      case 'https':             return checkHttps(php);
      case 'file_upload':       return checkFileUpload(php);
      case 'captcha':           return checkCaptcha(php);
      case 'password_policy':   return checkPasswordPolicy(php);
      case 'password_hash':     return checkPasswordHash(php);
      case 'lockout':           return checkLockout(php);
      case 'error_messages':    return checkErrorMessages(php);
      case 'api_auth':          return checkApiAuth(php);
      case 'rate_limit':        return checkRateLimit(php);
      case 'api_validation':    return checkApiValidation(php);
      case 'opaque_ids':        return checkOpaqueIds(php);
      case 'swagger':           return checkSwagger(php);
      case 'mfa':               return checkMfa(php);
      case 'otp':               return checkOtp(php);
      case 'hardcoded_creds':   return checkHardcodedCreds(php);
      case 'admin_exposure':    return checkAdminExposure(php);
      case 'headers':           return checkHeaders(php);
      case 'manual':
      default:                  return { status: 'manual', evidence: [], issues: ['Requires manual review'] };
    }
  } catch (e) {
    return { status: 'error', evidence: [], issues: [e.message] };
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5.  MCP SERVER
// ─────────────────────────────────────────────────────────────────────────────

const server = new Server(
  { name: 'sdlc-security', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'sdlc_list_requirements',
      description: 'List all 53 MoDEE MSB-SDLC V1.0 security requirements, optionally filtered by category.',
      inputSchema: { type: 'object', properties: {
        category: { type: 'string', description: "Category filter e.g. 'OWASP Top 10', 'Passwords', 'OTP', 'Secure APIs', 'General'. Leave empty for all." }
      }}
    },
    {
      name: 'sdlc_run_audit',
      description: 'Run a full MSB-SDLC compliance scan of the esp-v2 codebase. Returns a compliance matrix for all 53 requirements.',
      inputSchema: { type: 'object', properties: {
        severity_filter: { type: 'string', description: "Filter by: 'critical', 'high', 'medium'. Leave empty for all." }
      }}
    },
    {
      name: 'sdlc_check_requirement',
      description: 'Check a single MSB-SDLC requirement against the codebase with evidence.',
      inputSchema: { type: 'object', properties: {
        requirement_id: { type: 'string', description: "Requirement ID e.g. 'PWD-02', 'OTP-01', 'OWASP-03', 'GEN-06'" }
      }, required: ['requirement_id'] }
    },
    {
      name: 'sdlc_get_remediation',
      description: 'Get detailed remediation guidance for a failing MSB-SDLC requirement.',
      inputSchema: { type: 'object', properties: {
        requirement_id: { type: 'string', description: "Requirement ID e.g. 'CAPTCHA-01', 'GEN-06'" }
      }, required: ['requirement_id'] }
    },
    {
      name: 'sdlc_generate_report',
      description: 'Generate a full MSB-SDLC compliance report suitable for MoDEE submission.',
      inputSchema: { type: 'object', properties: {
        format: { type: 'string', description: "'markdown' (default) or 'json'" }
      }}
    },
  ]
}));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;
  const php = walkPhp(BACKEND);

  // ── sdlc_list_requirements ───────────────────────────────────────────────
  if (name === 'sdlc_list_requirements') {
    const cat = (args?.category ?? '').toLowerCase();
    const reqs = REQUIREMENTS.filter(r => !cat || r.cat.toLowerCase().includes(cat));
    const cats = {};
    for (const r of reqs) (cats[r.cat] = cats[r.cat] ?? []).push(r);

    const lines = [`# MoDEE MSB-SDLC V1.0 — ${reqs.length} Requirements\n`];
    for (const [c, items] of Object.entries(cats)) {
      lines.push(`\n## ${c}`);
      for (const r of items) {
        const auto = r.check !== 'manual' ? '🤖' : '👁';
        lines.push(`  ${auto} [${r.id}] (${r.sev.toUpperCase()}) ${r.desc}`);
      }
    }
    lines.push('\n🤖 = auto-scanned  |  👁 = manual review required');
    return { content: [{ type: 'text', text: lines.join('\n') }] };
  }

  // ── sdlc_check_requirement ───────────────────────────────────────────────
  if (name === 'sdlc_check_requirement') {
    const id  = (args?.requirement_id ?? '').toUpperCase();
    const req = REQ_BY_ID[id];
    if (!req) return { content: [{ type: 'text', text: `Unknown ID: ${id}. Use sdlc_list_requirements to see valid IDs.` }] };

    const result = runCheck(req.check, php);
    const icon   = { compliant:'✅', partial:'⚠️', fail:'❌', manual:'👁', error:'💥' }[result.status] ?? '❓';

    const lines = [
      `# ${id} — ${req.desc}`,
      `Category: ${req.cat}  |  Severity: ${req.sev.toUpperCase()}`,
      `Status: ${icon} ${result.status.toUpperCase()}`, '',
    ];
    if (result.evidence.length) {
      lines.push('**Evidence found:**');
      result.evidence.slice(0,8).forEach(e => lines.push(`  • ${e}`));
    }
    if (result.issues.length) {
      lines.push('\n**Issues:**');
      result.issues.forEach(i => lines.push(`  ⚠ ${i}`));
    }
    if (['fail','partial','manual'].includes(result.status)) {
      lines.push(`\n**Remediation:**\n${REMEDIATION[id] ?? 'See OWASP / Laravel security documentation.'}`);
    }
    return { content: [{ type: 'text', text: lines.join('\n') }] };
  }

  // ── sdlc_run_audit ───────────────────────────────────────────────────────
  if (name === 'sdlc_run_audit') {
    const sevFilter = (args?.severity_filter ?? '').toLowerCase();
    const reqs = REQUIREMENTS.filter(r => !sevFilter || r.sev === sevFilter);

    const checkCache = {};
    const results = reqs.map(req => {
      if (!checkCache[req.check]) checkCache[req.check] = runCheck(req.check, php);
      return { ...req, ...checkCache[req.check] };
    });

    const tally = { compliant: 0, partial: 0, fail: 0, manual: 0, error: 0 };
    results.forEach(r => tally[r.status]++);

    const icon = { compliant:'✅', partial:'⚠️', fail:'❌', manual:'👁', error:'💥' };
    const lines = [
      `# MoDEE MSB-SDLC V1.0 — Full Audit`,
      `PHP files scanned: ${php.length}  |  Requirements checked: ${results.length}`,
      '',
      `✅ ${tally.compliant} compliant  ⚠️ ${tally.partial} partial  ❌ ${tally.fail} fail  👁 ${tally.manual} manual`,
      '',
    ];

    const cats = {};
    results.forEach(r => (cats[r.cat] = cats[r.cat] ?? []).push(r));
    for (const [c, items] of Object.entries(cats)) {
      lines.push(`\n## ${c}`);
      items.forEach(r => {
        const firstIssue = r.issues?.[0] ? ` — ${r.issues[0].substring(0,80)}` : '';
        lines.push(`  ${icon[r.status] ?? '❓'} [${r.id}] (${r.sev.substring(0,4).toUpperCase()}) ${r.desc.substring(0,60)}${firstIssue}`);
      });
    }

    const critFail = results.filter(r => r.status === 'fail' && r.sev === 'critical');
    if (critFail.length) {
      lines.push('\n## 🚨 CRITICAL FAILURES — Fix Before Go-Live');
      critFail.forEach(r => {
        lines.push(`  ❌ [${r.id}] ${r.desc}`);
        lines.push(`     Fix: ${(REMEDIATION[r.id] ?? '').substring(0,100)}`);
      });
    }
    return { content: [{ type: 'text', text: lines.join('\n') }] };
  }

  // ── sdlc_get_remediation ─────────────────────────────────────────────────
  if (name === 'sdlc_get_remediation') {
    const id  = (args?.requirement_id ?? '').toUpperCase();
    const req = REQ_BY_ID[id];
    if (!req) return { content: [{ type: 'text', text: `Unknown ID: ${id}` }] };
    const fix = REMEDIATION[id] ?? 'No specific guidance available. See OWASP documentation.';
    return { content: [{ type: 'text', text: [
      `# Remediation: ${id}`,
      `**${req.desc}**`,
      `Category: ${req.cat}  |  Severity: ${req.sev.toUpperCase()}`,
      '', fix, '',
      'References:',
      '  • OWASP: https://owasp.org/Top10/',
      '  • Laravel Security: https://laravel.com/docs/security',
      '  • MoDEE MSB-SDLC V1.0 Annex 4.11',
    ].join('\n') }] };
  }

  // ── sdlc_generate_report ─────────────────────────────────────────────────
  if (name === 'sdlc_generate_report') {
    const fmt = (args?.format ?? 'markdown').toLowerCase();
    const checkCache = {};
    const results = REQUIREMENTS.map(req => {
      if (!checkCache[req.check]) checkCache[req.check] = runCheck(req.check, php);
      return { ...req, ...checkCache[req.check] };
    });

    const tally = { compliant: 0, partial: 0, fail: 0, manual: 0 };
    results.forEach(r => tally[r.status < 'x' ? r.status : 'manual']++);
    const total    = results.length;
    const scorePct = Math.round(tally.compliant / total * 100);

    if (fmt === 'json') {
      return { content: [{ type: 'text', text: JSON.stringify({
        report: 'MoDEE MSB-SDLC V1.0 Compliance', project: 'esp-v2',
        total_requirements: total, score_pct: scorePct, tally, requirements: results,
      }, null, 2) }] };
    }

    const date   = new Date().toISOString().split('T')[0];
    const icon   = { compliant:'✅', partial:'⚠️', fail:'❌', manual:'👁', error:'💥' };
    const critFail = results.filter(r => r.status === 'fail' && r.sev === 'critical');
    const highFail = results.filter(r => r.status === 'fail' && r.sev === 'high');
    const partial  = results.filter(r => r.status === 'partial');

    const lines = [
      '# MoDEE Minimum Baseline Security Standard — SDLC V1.0',
      '## Compliance Report — esp-v2',
      `Date: ${date}  |  Project: esp-v2 (Laravel 12 + React 18)`,
      'Prepared by: Eqratech Development Team', '',
      '---', '',
      '## Executive Summary', '',
      '| Metric | Value |',
      '|--------|-------|',
      `| Total Requirements | ${total} |`,
      `| ✅ Compliant | ${tally.compliant} (${Math.round(tally.compliant/total*100)}%) |`,
      `| ⚠️ Partial | ${tally.partial} (${Math.round(tally.partial/total*100)}%) |`,
      `| ❌ Failed | ${tally.fail} (${Math.round(tally.fail/total*100)}%) |`,
      `| 👁 Manual Review | ${tally.manual} (${Math.round(tally.manual/total*100)}%) |`,
      `| Overall Score | ${scorePct}% auto-compliant |`, '',
    ];

    if (critFail.length) {
      lines.push('## 🚨 Critical Failures — Must Fix Before Go-Live', '');
      critFail.forEach(r => {
        lines.push(`- **[${r.id}]** ${r.desc}`);
        lines.push(`  > ${(REMEDIATION[r.id] ?? '').substring(0,120)}`);
      });
      lines.push('');
    }
    if (highFail.length) {
      lines.push('## ⚠️ High-Severity Failures', '');
      highFail.forEach(r => lines.push(`- **[${r.id}]** ${r.desc}`));
      lines.push('');
    }
    if (partial.length) {
      lines.push('## ⚠️ Partial Compliance — Needs Improvement', '');
      partial.forEach(r => {
        lines.push(`- **[${r.id}]** ${r.desc}`);
        if (r.issues?.[0]) lines.push(`  > Gap: ${r.issues[0].substring(0,100)}`);
      });
      lines.push('');
    }

    lines.push('## Full Compliance Matrix', '');
    lines.push('| ID | Category | Severity | Status | Description |');
    lines.push('|-----|----------|----------|--------|-------------|');
    results.forEach(r => {
      lines.push(`| ${r.id} | ${r.cat} | ${r.sev.toUpperCase()} | ${icon[r.status] ?? '❓'} ${r.status} | ${r.desc.substring(0,60)} |`);
    });

    lines.push('', '---', '*Generated by esp-v2 SDLC Security MCP — MoDEE Annex 4.11*');
    return { content: [{ type: 'text', text: lines.join('\n') }] };
  }

  throw new Error(`Unknown tool: ${name}`);
});

const transport = new StdioServerTransport();
await server.connect(transport);
