// ESP v2 — TypeScript Types (barrel)
// NFR-003: Bilingual (Arabic/English) throughout.
//
// This file used to be a 334-line god module. Workstream 6 split it
// into per-domain files. This barrel re-exports both so existing
// `import { User, Application } from '../../types'` calls keep
// working unchanged — no consumer needs to move.
//
// Directly importing the domain file is now the preferred style:
//   `import type { User } from '../../types/platform';`
//   `import type { Application } from '../../types/jea';`
// Prefer that for new code; the barrel below is the back-compat.

export * from './platform';
export * from './jea';
