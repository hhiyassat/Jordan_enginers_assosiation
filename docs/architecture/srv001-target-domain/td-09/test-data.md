# TD-09 · Test-Data Preparation Plan

## Deterministic fixtures needed for UAT

### Organizations
| slug | name_ar | purpose |
|---|---|---|
| `uat-office-a` | مكتب اختبار أ | happy path |
| `uat-office-b` | مكتب اختبار ب | cross-office isolation |
| `uat-office-blocked` | مكتب محظور | mandatory-note block test |

### Users
| email | role | org |
|---|---|---|
| applicant-a@uat.jea | applicant | uat-office-a |
| applicant-b@uat.jea | applicant | uat-office-b |
| staff@uat.jea | staff | JEA |
| admin@uat.jea | admin | JEA |

### Rule definitions (seeded)
- `SRV001_EXPLORATION_MATRIX` — APPROVED (matches production seed)
- `SRV001_WELLS_COUNT` — PROVISIONAL (unchanged)
- `SRV001_NET_DEPTH` — PROVISIONAL (unchanged)

### Application draft fixtures
| id | project_sector | governorate | floor_count | floor_area | expected outcome |
|---|---|---|---|---|---|
| happy-1 | خاص | amman | 5 | 900 | ACCEPTED (min=5) |
| gov-1 | حكومي | amman | 5 | 900 | REJECTED → SRV-006 |
| below-min | خاص | amman | 5 | 900 | REJECTED (actualPts=3 < 5) |
| special-1 | خاص | amman | 10 | 900 | ACCEPTED (SPECIAL_STUDY_REQUIRED) |

### Financial fixtures
**Blocked** — no UAT scenarios execute financial paths until OD-01/10/17/19/35 signed.

### Payment fixtures
**Blocked** — no UAT scenarios execute payment until gateway contract signed.

## Data cleanup

Every UAT run uses `RefreshDatabase` on the UAT DB. No production data touched.
