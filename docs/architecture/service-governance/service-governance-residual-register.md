# Service Governance Residual Register

Every residual raised by a judgment record, tracked to closure.

| RESIDUAL_ID | Raised by | OWNER | RISK | BLOCKS_WHAT | STATUS | CLOSURE_EVIDENCE |
|---|---|---|---|---|---|---|
| RES-SG00-01 | JDG-SG00-01 | SG-03 | HIGH | SG-03 completion | OPEN | To be closed by SG-03 judgment record on version-snapshot scope |
| RES-SG00-02 | JDG-SG00-02 | Product owner / JEA | HIGH | Migration of SRV-001 from LEGACY_PILOT to canonical | OPEN | Signed calculator source attached to service UAT |
| RES-SG00-03 | JDG-SG00-03 | Product / JEA | MEDIUM | Publication of every service currently classified `_UNAPPROVED` | OPEN | Signed JEA decision per service |
| RES-SG00-04 | JDG-SG00-04 | SG-06 | LOW | SG-06 completion | OPEN | SG-06 characterization tests + refactor commit |
| RES-SG01-01 | JDG-SG01-01 | (out of program scope) | LOW | Legacy `status` column cleanup | OPEN | Future cleanup once every consumer migrated to `publication_status` |
| RES-SG01-02 | JDG-SG01-02 | SG-02 | MEDIUM | SG-02 completion | CLOSED | Closed by JDG-SG02-01: LENIENT default with legacy fallback |
| RES-SG02-01 | JDG-SG02-01 | ops | LOW | (observability only) | OPEN | Dashboard counter for AVAIL_LEGACY_STATUS_FALLBACK |
| RES-SG02-02 | JDG-SG02-02 | follow-up | MEDIUM | Complete SG-02 enforcement surface | OPEN | Wire ApplicationController::{store,submit}, PaymentsController::initiate, CertificatesController::download* to consult the verdict |
