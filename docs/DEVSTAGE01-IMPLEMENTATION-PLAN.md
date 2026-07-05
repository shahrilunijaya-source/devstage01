# DevStage01 — Implementation Plan

**Date:** 2026-07-04. Executes the [blueprint](DEVSTAGE01-PRODUCT-BLUEPRINT.md). Each group = smallest complete slice (migration + service + controller + view + tests), committed atomically, suite green before moving on. Baseline: 246/246 tests.

## Group 0 — Truth & hygiene (CRITICAL fixes)
- [ ] 0.1 Remove dead Track PMS code: SourceTextBuilder, RagIndexer, Sentiment* (+ command), MonthlyReportService, ProjectInsightService, dead chat tools, DriveIngestService chain, orphan ProjectComment/IssueComment policies, InitialDataSeeder, scripts/phpstan.sh. Fix/remove broken project-chat routes.
- [ ] 0.2 Rebuild chat wiring: bind ToolRegistry (URSB-native tools: project summary + object search), fix ProjectSummaryTool refs, remove `/chat` (project chat) in favour of working `/portfolio/chat`; add feature tests for container resolution + ask flow (Http::fake).
- [ ] 0.3 Rewrite README.md (product), delete/archive stale EXTRACTION-COMPLETE.md + SETUP.md claims.
- Exit: suite green, `grep` proves no references to the 9 ghost classes.

## Group 1 — Gate integrity + phase guards + reopen (F-05/06/11)
- [ ] 1.1 `StageGateService` (single readiness authority; hard/soft checks incl. previous-stage-baselined, approved-objective placeholder, blocking-discussions placeholder). Gate page + POST baseline both consume it.
- [ ] 1.2 Approve-with-exception: baseline POST accepts `exceptions_ack` + reason when only soft checks fail; recorded in `snapshot_meta`, rendered on document.
- [ ] 1.3 Session phase guards on preAnalyze/capture; `rejected`/refine path: firewall can send back to pre_analysis with reason.
- [ ] 1.4 `BaselineService::reopen()` + route + UI + notifications (status `reopened`, objects unlocked, audit).
- [ ] 1.5 Tests: direct-POST bypass blocked; replay blocked; reopen roundtrip; exception recorded.

## Group 2 — Project Objective Baseline (F-07/12)
- [ ] 2.1 `ObjectType::OBJECTIVE` (+`OBJ` prefix, label); structured attribute schema.
- [ ] 2.2 Guided setup wizard: project create → objective form → creator scope binding (project_pm) → optional first module. Objective editable via dedicated page; versioned via ObjectGraphService.
- [ ] 2.3 Objective approval action (validate right) minting APPROVAL + APPROVES edge; BRS gate hard-check "approved objective exists".
- [ ] 2.4 Auto-link BRS-family requirements `DERIVED_FROM → OBJ` at materialize/capture time.
- [ ] 2.5 Objective panel on project page + coverage of OBJ in RTM upstream column.
- [ ] 2.6 Tests: wizard flow, binding created, gate blocks without approved objective, auto-link, ACL.

## Group 3 — Discussions (F-08)
- [ ] 3.1 Migration `discussions` + `discussion_comments` (polymorphic; visibility internal|client; blocking; assignee; due; resolved_by/at).
- [ ] 3.2 `DiscussionService` (open/comment/resolve/reopen/convert). Convert-to: risk, decision, requirement, issue, CR — mints graph object + trace, stamps origin.
- [ ] 3.3 Controller + partial `_discussions.blade.php` embedded on object detail, session, stage gate, project pages. Client-visibility filter at query layer.
- [ ] 3.4 Gate hard-check: no open *blocking* discussions. Inbox: discussions assigned to me.
- [ ] 3.5 Tests: visibility (client cannot see internal), convert paths, blocking gate, resolve/reopen, ACL.

## Group 4 — AI Refinement Engine (F-09) + usage log (F-27 slice)
- [ ] 4.1 Migration `ai_suggestions` + `ai_usage_log`. `config/prompts.php` versioned templates.
- [ ] 4.2 `RefinementService` with 5 actions (improve, challenge, generate_ac, find_missing, check_objective); strict JSON + one retry-with-repair; deterministic refusal fallback; usage logged.
- [ ] 4.3 Accept / accept-edit / reject flow → versioned update via ObjectGraphService; generate_ac mints ACCEPTANCE_CRITERION `REFINES→req`.
- [ ] 4.4 UI: "AI actions" menu on object detail; suggestions panel with diff-style proposal, rationale, sources, confidence; decision buttons.
- [ ] 4.5 Tests: suggestion lifecycle, never-overwrite guarantee, malformed-JSON retry→refusal, AC minting, throttle, ACL.

## Group 5 — Objective Guardian (F-10) + CR impact classes (F-18 slice)
- [ ] 5.1 `ObjectiveGuardianService::assess()` (LLM + deterministic fallback); columns on change_requests (`guardian_assessment` JSON, `override_reason`).
- [ ] 5.2 Wire into CR open (assessment shown to approver; override reason required for approve when direct_conflict) and refinement check_objective action.
- [ ] 5.3 Downstream impact marking on CR apply: per-object `attributes.impact = review_required` + register UI to disposition (no_impact / update_required / invalidated); before/after diff on CR page.
- [ ] 5.4 Tests: classification storage, override-required path, impact disposition, diff render.

## Group 6 — Guided UX (F-12 slice, spec §14)
- [ ] 6.1 `NextBestActionService` (rule-derived) + panel on project page.
- [ ] 6.2 Stage guidance cards from Knowledge Book (`guidance` item type + seeder content for 9 stages).
- [ ] 6.3 Quality-indicator tooltips ("how computed") on metrics/coverage/RTM.
- [ ] 6.4 Tests: NBA rules, guidance render.

## Group 7 — Versioning depth (F-16) — if time allows
- [ ] 7.1 Snapshot completeness (add missing fields); version content viewer + side-by-side compare.
- [ ] 7.2 RTM gap-class upgrades (design-coverage affects status; AC gap class) (F-17).

## Group 8 — Docs & wrap-up
- [ ] DATA-MODEL, AI-ARCHITECTURE, USER-GUIDE, ADMIN-GUIDE, TEST-PLAN, CHANGELOG, ASSUMPTIONS-AND-DECISIONS docs; final completion report.

**Deferred (recorded):** role-management UI, configurable templates, semantic contradiction detection, CI pipeline (recommended next phase), queue/mail infra, template designer, prototype screen-state matrix, evidence file download endpoint.
