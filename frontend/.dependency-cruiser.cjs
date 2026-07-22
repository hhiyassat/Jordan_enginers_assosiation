/**
 * Architecture Enforcement — Workstream 3 (baseline)
 *
 * Rules are intentionally NARROW to match today's directory shape.
 * They enforce layering that already holds ("data layer doesn't
 * import UI", "utils don't import pages", "no cycles"), not the
 * FUTURE platform/modules/plugins split — that arrives in later
 * workstreams and this file grows to match.
 *
 * Every rule is currently `severity: 'warn'`. Workstream 15
 * promotes them to `error` once the module split is complete.
 * That's why `npm run arch:check` returns exit 0 today.
 *
 * The composition roots (App.tsx, main.tsx, routes.tsx) are
 * excluded from the "can't import pages" rules because they are
 * exactly where the top-level lazy route table lives — that's
 * their whole purpose.
 */
module.exports = {
  forbidden: [
    /* ── Cycles ─────────────────────────────────────────────── */
    {
      name: 'no-circular',
      severity: 'warn',
      comment:
        'Circular dependencies make it impossible to reason about ' +
        'module load order and quietly break tree-shaking.',
      from: {},
      to:   { circular: true },
    },

    /* ── Layer rules — data layer / infrastructure ──────────── */
    {
      name: 'utils-cannot-import-pages',
      severity: 'warn',
      comment:
        'src/utils/* is meant to be dependency-free reusable ' +
        'primitives. Reaching into src/pages/* means the util has ' +
        'a page-specific assumption and belongs in that page.',
      from: { path: '^src/utils/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'api-cannot-import-pages',
      severity: 'warn',
      comment:
        'Data-fetching layer must not know about screens. If an ' +
        'api client needs a page-specific type, extract the type.',
      from: { path: '^src/api/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'components-ui-cannot-import-pages',
      severity: 'warn',
      comment:
        'src/components/ui/* is the design system. Design system ' +
        'primitives must not depend on any specific page.',
      from: { path: '^src/components/ui/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'layout-cannot-import-pages',
      severity: 'warn',
      comment:
        'Layout wraps pages via router outlets. Direct imports of ' +
        'a specific page from the layout mean the layout has ' +
        'domain knowledge that belongs in a route registry.',
      from: { path: '^src/layout/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'auth-cannot-import-pages',
      severity: 'warn',
      comment:
        'Auth (provider, context, guards) is a platform primitive. ' +
        'Guarding a specific page from inside the auth folder ' +
        'reverses the dependency direction.',
      from: { path: '^src/auth/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'i18n-cannot-import-pages',
      severity: 'warn',
      comment:
        'i18n framework must remain generic. Page-specific keys ' +
        'live in per-module locale files, not the i18n bootstrap.',
      from: { path: '^src/i18n/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'engine-cannot-import-pages',
      severity: 'warn',
      comment:
        'DynamicForm / DocumentUploader etc. render schemas — they ' +
        'must not know about the specific pages that host them.',
      from: { path: '^src/engine/' },
      to:   { path: '^src/pages/' },
    },
    {
      name: 'types-cannot-import-runtime',
      severity: 'warn',
      comment:
        'The types module is a shape declaration. Importing runtime ' +
        'code from it drags the runtime into every consumer of a ' +
        'type-only import.',
      from: { path: '^src/types/' },
      to:   { path: '^src/(api|auth|components|engine|layout|pages|utils)/' },
    },

    /* ── Hygiene ────────────────────────────────────────────── */
    {
      name: 'no-orphans',
      severity: 'warn',
      comment:
        'Orphan modules (nothing imports them) are usually dead ' +
        'code. The composition root files are excluded because ' +
        'Vite / Vitest / TypeScript load them by convention.',
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
