# DevStage01 — Test Plan

**Date:** 2026-07-05. Suite: **299 tests / 810 assertions, all green** (`php artisan test`, sqlite `:memory:`; dev DB is MySQL — enum/JSON semantics verified by `migrate:fresh --seed`).

## 1. What is protected (spec §27 map)

| Spec area | Where |
|---|---|
| Tenant isolation | AccessControlTest, PortfolioWebTest, RtmTest (trace boundary), SearchTest, PortfolioChatTest (tool scoping) |
| Role permissions / ACL layers | 5 AccessControl suites + AclAdminTest (deny-by-default, delegation expiry, field redaction, PDP cache busting, audit immutability) |
| Project creation + **objective baseline** | ObjectiveBaselineTest (wizard routing, versioned capture, re-approval on revise, sign-off record, gate flag, objective→requirement trace, ACL) |
| Requirement creation & versioning | ObjectGraphTest (permanent IDs, immutable versions) |
| Traceability | ObjectDetailTest, RtmTest (incl. ≤2-query scaling + cross-project boundary), CoverageTest |
| **Stage transitions & gate enforcement** | GateEnforcementTest (server-side gate, override recording, stage-order, phase-guard regressions), StageGateTest, SessionEngineTest |
| Approval workflow | SessionApprovalTest, ApprovalRecordTest |
| **Reopening baselines** | BaselineReopenTest (unlock semantics, re-baseline, active-only, ACL) |
| **Comments/discussions** | DiscussionTest (client visibility isolation, blocking gate, convert→risk/CR, inbox, ACL) |
| Change requests + SoD + **Guardian + disposition** | ChangeManagementTest, ObjectiveGuardianTest (override-required, review_required→disposition, invalidated) |
| **AI suggestion accept/reject** | RefinementTest (proposal-not-applied, accept versioning, accept-with-edits, reject, AC minting) |
| **Invalid AI responses** | RefinementTest (retry→honest refusal), LlmAnalystTest (malformed JSON, client failure, fallback) |
| Conflict detection | ConflictDetectionTest (blocks approval) |
| Document generation | SessionApprovalTest (PDF), DeckTest, PptxExportTest |
| Prototype mapping | PrototypeTest |
| Uploads | EvidenceIntakeTest, SecurityHardeningTest (executable rejection), EvidenceRagIndexTest (PDF/DOCX extraction) |
| Search | SearchTest, ObjectBrowserTest (wildcard neutralised) |
| Export | Risk/Decision/Verification/RTM/audit CSV (+ formula-injection), RTM/baseline PDF, deck PPTX |
| Audit logs | AccessControlTest (immutability), PdpCacheTest (allows vs can) |
| **Chat stack** | PortfolioChatTest (container resolution regression, grounded ask, tool round-trip, scope refusal) |
| **Next Best Action / guidance** | NextBestActionTest |
| Whole surface | SmokeTest (admin renders every page on seeded demo data) |

## 2. End-to-end scenarios (spec §27) — how they are covered

1. **Analyst workflow**: evidence→pre-analysis→firewall→capture→consolidate→approve→baseline — SessionApprovalTest full loop + UrsbDemoSeeder drives it for real on `migrate:fresh --seed`.
2. **PM review**: gate readiness + exceptions + Next Best Actions — GateEnforcementTest, NextBestActionTest.
3. **Client reviewer**: visibility isolation — DiscussionTest + field-redaction tests.
4. **Approver**: session exit gate, CR approval with SoD and Guardian override — SessionApprovalTest, ObjectiveGuardianTest.
5. **Change to an approved requirement**: CR→apply→downstream review_required→disposition — ObjectiveGuardianTest.
6. **Conflict with the objective**: direct_conflict requires recorded override — ObjectiveGuardianTest.
7. **BRS→prototype flow**: demo seed + PrototypeTest + RtmTest (traced status needs the full chain).

## 3. Gaps & policy

- Coverage-% metrics are not enforced (no pcov/xdebug on the dev box); the suite is behaviour-first. Add coverage in CI (recommended next phase, with Linux PHPStan).
- Inherited Track modules (workload, calendar, feedback UI) remain untested legacy surface.
- Conventions that bite: test helpers must not be named `session()`; two projects both mint `FIND-0001` (assert on titles cross-project); keep example refs out of form placeholders.
