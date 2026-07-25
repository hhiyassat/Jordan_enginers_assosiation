# Handoff — ESP Core Pluggable Platform: Fork Approach

**Prepared:** 2026-07-25  
**For:** Management review  
**Prepared by:** Development team  
**Decision required by:** Management approval before P0 start

---

## Executive Summary / الملخّص التنفيذي

**EN.** We recommend forking the current JEA production codebase (`esp-v2`) into a new independent repository (`esp-core`) to execute the ESP Core Pluggable Platform transformation. This preserves the JEA production system untouched while allowing a 6-11 month platform-neutralization program to proceed in parallel. The two projects converge later — after the platform is proven and JEA is migrated to consume it as a package.

**AR.** نُوصي بنسخ الكود الحالي الخاص بإنتاج نقابة المهندسين الأردنيين (`esp-v2`) إلى ريبو جديد ومستقل (`esp-core`) لتنفيذ برنامج تحويل ESP إلى منصّة قابلة للتركيب (Pluggable Platform). هذا الحل يُحافظ على النظام الإنتاجي كما هو دون أي مساس، بينما يسير برنامج تحييد المنصّة (6-11 شهراً) بالتوازي. يلتقي المشروعان لاحقاً — بعد أن تُثبت المنصّة نضوجها وننقل نظام النقابة إليها كـ package.

---

## 1. Background — Where we are

The ESP v2 codebase currently:
- Serves JEA production (~90k members, 80k buildings/year target)
- Just completed a 16-workstream architectural refactor (Jul 2026)
- Has physical separation between platform / modules / plugins / integrations
- Has enforcement in CI (dep-cruiser + architecture tests)

The **ESP Core spec v0.1** (received 2026-07-25) proposes transforming this codebase into a **business-neutral, installable, multi-organization service platform** — comparable in ambition to Moodle. This transformation is:
- 46 weeks of dedicated work (10 phases, 30 workstreams)
- Constitutionally required to preserve JEA production throughout (spec §3.2)
- Substantial in blast radius — touches nearly every part of the codebase

**Key question:** how do we do this without risking JEA production?

---

## 2. The Choice — Two Approaches Compared

### Option A: Transform In-Place (single repo)

Continue on `esp-v2` and evolve it into the pluggable platform. JEA production and platform development share one codebase.

| Pros | Cons |
|------|------|
| Single codebase, no divergence to manage | JEA production at continuous risk during transformation |
| Every change immediately visible to JEA users | Every hotfix / feature request slows platform work |
| No merge overhead later | Feature-flags become the only safety net |
| Team focus stays consolidated | Rollback of a bad platform change also affects JEA |

**Risk profile:** HIGH. A production incident on JEA can force reverting weeks of platform work.

### Option B: Fork First, Merge Later (recommended)

Clone `esp-v2` to a new repo `esp-core`. Both projects run independently. Once ESP Core reaches maturity (~P6-P7 milestone), migrate JEA to consume ESP Core as an installed package set.

| Pros | Cons |
|------|------|
| Zero risk to JEA production | Two codebases to maintain during the transition |
| Platform team works with full architectural freedom | Bug fixes may need to be backported (JEA → Core) |
| Clear decoupling of business (JEA) vs. platform (Core) work | Requires disciplined tracking of upstream/downstream drift |
| Enables the "clean core" test — Core boots with zero JEA code | Adds a merge/migration step at the end (~1-2 weeks) |
| Reduces political pressure — platform work isn't blocked by JEA tickets | |

**Risk profile:** LOW-MEDIUM. Isolated from production. Divergence is manageable if scoped.

### Option C: Wait

Postpone the platform transformation. Focus on JEA delivery.

| Pros | Cons |
|------|------|
| Zero disruption | Loses window to build a reusable, marketable platform |
| Team focus on JEA | Every additional JEA feature deepens JEA-specific coupling |
| No new cost | Cost of future transformation grows monotonically |
| | No differentiator — locked into a single customer |

**Risk profile:** LOW technically, HIGH strategically.

---

## 3. Recommendation — Option B (Fork)

### Why fork is the right call

1. **JEA production is contractually critical** — 90k members depend on it. Any risk to that system is unacceptable.
2. **Platform transformation is a long-horizon strategic investment** — 6-11 months of work needs a stable environment, not one interrupted by daily JEA firefighting.
3. **Clean-slate architecture experiments are risk-free** — we can try patterns aggressively in `esp-core` without production consequences.
4. **The convergence path is proven** — once ESP Core is stable, JEA becomes an installation of it (an "Organization Pack" per spec §6.5). This is exactly what the spec envisions.
5. **We already have architecture separation** — the 16-workstream refactor makes forking easy. The two projects start from the same, well-structured baseline.

### The two-project model

```
┌─────────────────────────────────┐         ┌─────────────────────────────────┐
│  esp-v2 (current)                │         │  esp-core (new)                  │
│  ─────────────────────────       │         │  ─────────────────────────       │
│  • JEA production                │         │  • Platform transformation        │
│  • Bug fixes + JEA features       │         │  • Neutralization of Core         │
│  • Deploys to Hostinger          │         │  • Package system + SDK           │
│  • Team: JEA delivery            │         │  • Team: platform architecture    │
│  • Timeline: ongoing              │         │  • Timeline: 6-11 months          │
│                                   │         │                                   │
│  Repo: hhiyassat/code-generation  │         │  Repo: hhiyassat/esp-core (new)   │
│  Deploy: hhiyassat/               │         │  Deploy: none (dev env only       │
│    Jordan_enginers_assosiation    │         │    until Phase 10)                │
└─────────────────────────────────┘         └─────────────────────────────────┘
                │                                            │
                │  Backports (bug fixes flow both ways)      │
                └────────────────────────────────────────────┘
                                    │
                            ┌───────▼────────┐
                            │  Convergence   │  (End of P6, ~Month 7)
                            │  JEA migrates  │
                            │  to esp-core   │
                            │  as a package  │
                            │  installation  │
                            └────────────────┘
```

---

## 4. Effort & Timeline

### Development effort

| Item | esp-v2 (JEA) | esp-core (platform) |
|------|--------------|---------------------|
| Team required | 1-2 engineers | 1-2 engineers |
| Timeline | Ongoing (JEA roadmap) | Months 1-11 (46 weeks) |
| Weekly time investment | Normal (per JEA plan) | 40 hrs/wk dedicated |

**Total peak team size:** 2-4 engineers (depending on how JEA delivery is staffed independently).

### Phase-by-phase (esp-core)

| Phase | What | Duration | Cumulative |
|-------|------|----------|------------|
| P0 | ADR + Gap audit + Approval | 2 weeks | 2 |
| P1 | Package manifest + Loader | 4 weeks | 6 |
| P2 | Extract Core runtimes (Workflow/Forms/etc.) | 6 weeks | 12 |
| P3 | Pilot package (jea-dues as first package) | 3 weeks | 15 |
| P4 | Per-organization activation model | 5 weeks | 20 |
| P5 | Frontend runtime registry | 4 weeks | 24 |
| P6 | Convert remaining modules to packages | 6 weeks | 30 |
| P7 | Developer SDK + CLI + templates | 4 weeks | 34 |
| P8 | Admission controls + signing + security | 5 weeks | 39 |
| P9 | Operations console + observability | 4 weeks | 43 |
| P10 | Reference distribution + JEA migration | 3 weeks | 46 |

### Convergence — JEA migrates to ESP Core

Between P6 and P10 (Months 7-11), the JEA-specific packages become installable on the ESP Core distribution. JEA production is then migrated (via a scheduled maintenance window) to run **on top of** ESP Core rather than as a specialized codebase.

After convergence, `esp-v2` is archived (or kept read-only for historical reference).

---

## 5. Cost Implications

### One-time setup (Week 0)

| Item | Cost |
|------|------|
| Create new GitHub repository | Free (private repo) |
| Clone existing codebase | Zero (script) |
| Rename identity in files (README, composer.json, package.json) | 1 day of engineer time |
| Initial CI setup for new repo | 1 day of engineer time |

### Ongoing (Months 1-11)

| Item | Estimated Cost |
|------|----------------|
| Additional engineer (if platform team is separate from JEA team) | Standard salary × 11 months |
| Cloud infrastructure for esp-core dev/staging | ~USD 100-200/month |
| Additional tooling (SDK signing infrastructure) | ~USD 50/month starting P7 |

### Cost avoidance (Option B vs. Option A)

- Zero production incidents caused by platform work → no JEA revenue disruption
- No emergency rollbacks or expedited deployments needed
- Cleaner completion means no "cleanup phase" after go-live

---

## 6. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Two codebases drift too far apart | HIGH | Weekly reconciliation review; backport bug fixes automatically via cherry-pick |
| JEA needs a Core-side fix during transformation | MEDIUM | Backport process: fix in esp-v2 first, then port to esp-core |
| Platform team gets pulled into JEA firefighting | HIGH | Clear team separation; escalation path via management |
| Convergence (P10) surprises with unforeseen incompatibilities | MEDIUM | Rehearsal migration on a JEA copy at end of P6, refine plan by P10 |
| Boss/stakeholders lose visibility on esp-core progress | MEDIUM | Monthly demo + Handoff document at each phase completion |
| Investment doesn't pay off (no second customer materializes) | LOW-MEDIUM | Even without external adoption, JEA benefits from a cleaner, more maintainable codebase |

---

## 7. Decision Requested from Management

Before development can proceed:

- [ ] **Approve fork approach (Option B)** — clone `esp-v2` to `esp-core` for platform work
- [ ] **Confirm team assignment** — who works on esp-v2 (JEA) vs. esp-core (platform)?
- [ ] **Confirm timeline realism** — 46 weeks acceptable or need reduced-scope options (A/B/C in the plan doc)?
- [ ] **Approve budget** — additional engineer + infra cost for platform track
- [ ] **Confirm strategic intent** — is a marketable, reusable platform (Moodle-style) the goal, or is this an internal-only initiative?
- [ ] **Grant permission to create the new GitHub repository** — `hhiyassat/esp-core` or preferred name?

---

## 8. Immediate Next Steps (If Approved)

1. **Week 1** — Fork execution (1 day)
   - Clone `esp-v2` → `esp-core` (preserves git history)
   - Rename remotes, push to new GitHub repo
   - Remove JEA-specific brand references from new repo (README, package.json name, etc.)

2. **Week 2** — P0 Preparation (start of the platform program)
   - Write ADR: "Adopt ESP Core Pluggable Platform Spec v0.1"
   - Complete full gap audit vs. spec (every MUST/SHOULD → current state → owner)
   - Publish first Phase Handoff document

3. **Week 3+** — P1 Foundation begins (per the plan document)

---

## 9. References

- **Full implementation plan:** `docs/architecture/08-pluggable-platform-implementation-plan.md` (10 phases, 30 workstreams, effort estimates)
- **Original spec:** `Pluggable_Institutional_Service_Platform/doc/ESP_Core_Pluggable_Service_Platform_Implementation_Specification_v0.1.docx`
- **Previous refactor context:** `docs/architecture/01-refactoring-plan.md` + `session_handoff_2026-07-22.md`
- **Current architecture:** `docs/architecture/04-modules.md` + `05-plugins-and-integrations.md`

---

## 10. Summary for Quick Reading

| Question | Answer |
|----------|--------|
| **What are we doing?** | Cloning ESP v2 to a new repo to build a business-neutral platform without risking JEA production |
| **Why fork?** | JEA has 90k members in production. Zero risk tolerance for platform experiments. |
| **How long?** | 6-11 months for full platform program (parallel to JEA delivery) |
| **How much?** | Standard engineer time + ~USD 150-250/month infrastructure |
| **What's the payoff?** | ESP becomes reusable across institutions (JMA, other NGOs, ministries). Not JEA-locked. |
| **What if we don't?** | Every new JEA feature deepens the coupling. Future transformation cost grows. Locked to one customer. |
| **What's the risk of the fork approach?** | Two codebases to maintain during transition. Manageable with discipline. |
| **What happens to esp-v2 at the end?** | Archived (or read-only). JEA now runs on esp-core as a package installation. |
| **What decision is needed today?** | Approval to fork + team + budget + strategic intent |

---

*This handoff is designed to be read in 10 minutes and provides everything needed for a go/no-go decision. Detailed technical plans are in the referenced documents.*
