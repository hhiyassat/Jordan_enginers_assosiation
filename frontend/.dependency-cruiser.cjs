/**
 * Architecture Enforcement — Workstream 15 (strict promotion).
 *
 * Post-W10 layout: src/ is now cleanly split into
 *   platform/     — domain-neutral
 *   modules/*     — domain (JEA) service modules
 *   integrations/ — external-system adapters
 *   plugins/      — optional capabilities (frontend-only currently empty)
 *   api/, auth/, engine/, i18n/, layout/, types/, test/  — pre-split code
 *
 * These rules enforce the dep-graph direction from §4 of the plan:
 *
 *   platform  ← modules ← integrations ← plugins
 *
 * Modules may read the platform. Platform must never read modules.
 * Same for integrations and plugins.
 *
 * `severity: 'error'` — Workstream 15 promotion. `npm run arch:check`
 * now fails CI on any violation.
 *
 * Composition roots (App.tsx, main.tsx, routes.tsx) are excluded from
 * dep-direction rules because they're where the top-level page
 * registry lives — that's their WHOLE purpose.
 */
module.exports = {
  forbidden: [
    /* ── Cycles ─────────────────────────────────────────────── */
    {
      name: 'no-circular',
      severity: 'error',
      comment:
        'Circular dependencies make it impossible to reason about ' +
        'module load order and quietly break tree-shaking.',
      from: {},
      to:   { circular: true },
    },

    /* ── Dep-direction rules (post-W10) ─────────────────────── */
    {
      name: 'platform-cannot-import-modules',
      severity: 'error',
      comment:
        'Platform code (src/platform/**) is domain-neutral. Importing ' +
        'a specific module means the platform has JEA knowledge and ' +
        'no longer works on another tenant. If a platform file needs ' +
        'a module capability, invert: expose a contract from the ' +
        'platform, have the module implement it.',
      from: { path: '^src/platform/' },
      to:   { path: '^src/(modules|integrations|plugins)/' },
    },
    {
      name: 'platform-cannot-import-domain-pages',
      severity: 'error',
      comment:
        'A platform component reaching into a module page means it ' +
        'depends on a specific JEA screen. Same violation as above ' +
        'stated for the pages/ subtree specifically.',
      from: { path: '^src/platform/' },
      to:   { path: '^src/modules/[^/]+/pages/' },
    },
    {
      name: 'auth-cannot-import-modules',
      severity: 'error',
      comment:
        'Auth (context / guards / login page) is a platform primitive. ' +
        'Guarding a specific JEA page from inside src/auth means the ' +
        'auth layer has domain knowledge.',
      from: { path: '^src/auth/' },
      to:   { path: '^src/(modules|integrations)/' },
    },
    {
      name: 'api-cannot-import-modules',
      severity: 'error',
      comment:
        'Data-fetching layer must not know about screens. If an api ' +
        'client needs a page-specific type, extract the type into ' +
        'src/types/ or src/modules/<M>/types/ instead.',
      from: { path: '^src/api/' },
      to:   { path: '^src/modules/[^/]+/pages/' },
    },
    {
      name: 'i18n-cannot-import-modules',
      severity: 'error',
      comment:
        'i18n framework must remain generic. Per-module keys live in ' +
        'per-module locale files, not in the i18n bootstrap.',
      from: { path: '^src/i18n/' },
      to:   { path: '^src/modules/' },
    },
    {
      name: 'types-cannot-import-runtime',
      severity: 'error',
      comment:
        'The types module is a shape declaration. Importing runtime ' +
        'code from it drags the runtime into every consumer of a ' +
        'type-only import.',
      from: { path: '^src/types/' },
      to:   { path: '^src/(api|auth|components|engine|layout|modules|integrations|platform)/' },
    },

    /* ── Hygiene ────────────────────────────────────────────── */
    {
      name: 'no-orphans',
      severity: 'error',
      comment:
        'Orphan modules (nothing imports them) are usually dead code. ' +
        'The composition root files are excluded because Vite / ' +
        'Vitest / TypeScript load them by convention.',
      from: {
        orphan: true,
        pathNot: [
          '\\.(config|setup|test|spec|d)\\.[tj]sx?$',
          '^src/(main|App|routes)\\.tsx?$',
          '^src/(test|__mocks__)/',
          '^src/vite-env\\.d\\.ts$',
          '^\\.dependency-cruiser\\.cjs$',
          '^(vite|vitest|tailwind|postcss)\\.config\\.[tj]s$',
        ],
      },
      to: {},
    },
  ],

  options: {
    tsPreCompilationDeps: true,
    tsConfig: { fileName: 'tsconfig.json' },
    doNotFollow: {
      path: 'node_modules',
    },
    exclude: {
      path: '(^|/)node_modules/|^dist/|^build/',
    },
    reporterOptions: {
      text: {
        highlightFocused: true,
      },
    },
  },
};
