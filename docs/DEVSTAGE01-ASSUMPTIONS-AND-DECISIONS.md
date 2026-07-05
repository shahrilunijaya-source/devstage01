# DevStage01 — Assumptions & Decisions

**Date:** 2026-07-05. Material assumptions and product decisions taken autonomously during the refinement pass (spec §3.3), with reasoning. Override any of these and the affected code is localised.

## Product decisions

| # | Decision | Reasoning |
|---|---|---|
| D1 | **Keep the existing ID format** `{PREFIX}-{NNNN}` (e.g. `BRS-REQ-0001`) instead of the spec's example style (`BRS-BR-001`) | Established, tested, unique per (project,type); changing identifiers on live data breaks the permanence guarantee the spec itself demands. |
| D2 | **Objective = a graph object**, not a new table | Inherits versioning, tracing, ACL, redaction and approval for free; keeps schema unfragmented (spec §22). |
| D3 | Gate checks split **hard vs overridable**: approved-session and not-already-baselined can never be overridden; unresolved items, stage order, objective, blocking discussions can — with a recorded reason frozen into `snapshot_meta` | Spec §9 requires approve-with-exception; but an empty baseline or double-freeze is a data-integrity fault, not an exception. |
| D4 | **Reopen** returns members to `NEEDS_CONFIRMATION` (except immutable EVIDENCE) and flips approved sessions back to `in_session` | A reopened anchor must not keep its approved standing; sessions must be live again or the items would be unresolvable (capture is phase-guarded now). |
| D5 | Stage-order gating = **nearest mandatory predecessor baselined** (SPECIALIST optional), overridable | Enforces the BRS→…→VALIDATION chain without hard-blocking legitimate parallel work. |
| D6 | Client isolation for discussions at the **query layer** (`visibleTo`) + 404 on direct access, keyed off `system_role=client` | Never trust the view layer (spec §18); one authority for “is this user a client”. |
| D7 | CR conversion from a comment requires the discussion to sit on an EngObject | A change request without a target is meaningless in the existing CM engine. |
| D8 | Guardian is **advisory**; only `direct_conflict` hard-requires an override reason at approval | Spec §7: AI must advise, not decide; the one classification that contradicts the anchor demands recorded accountability. |
| D9 | Guardian failures **never block CR intake** (degrade to `insufficient_information`) | Raising a change is a human right; an LLM outage must not stop governance workflow. |
| D10 | Downstream impact classes stored in `attributes.impact` / `attributes.impact_from` rather than a new table | Reuses the versioned object store; disposition history is preserved in object_versions. |
| D11 | `improve` and `generate_ac` are the only refinement actions that mutate on accept; challenge/find-missing/check-objective are advisory acknowledgements | Applying "issues found" automatically would fabricate content — humans convert insights via discussions/CRs. |
| D12 | Stage guidance lives in `config/guidance.php`, not the Knowledge Book | Fast, versioned-in-git, adequate for spec §14.2; migrate into KB items when per-client configurability is actually needed. |
| D13 | Removed (not fixed) the Track PMS sentiment/insight/monthly-report/Drive-ingest features and per-project chat | They referenced nine nonexistent classes (fatal if executed) and no URSB requirement needs them; portfolio chat covers the chat need. Tables kept — dropping data needs an explicit owner decision. |
| D14 | Kept Workload + Calendar modules untouched | Routed and functioning; whether they are wanted product surface is a business call (flagged in audit §7). |

## Assumptions

| # | Assumption |
|---|---|
| A1 | No queue worker or mail transport exists in any environment — everything runs request-time; notifications stay in-app. (Held true: no live code dispatches jobs after the dead-code removal.) |
| A2 | PHPStan cannot run on this Windows ZTS PHP build (silent crash) — "static analysis green" claims require a Linux/CI run. The false-green `scripts/phpstan.sh` was removed rather than patched. |
| A3 | MySQL dev DB may be offline during development; sqlite tests + `migrate:fresh --seed` (when up) are the schema verification. Two G2+ migrations still need to run against the dev MySQL once it is back (`php artisan migrate`). |
| A4 | The `google/apiclient` composer dependency is now unused (Drive ingest removed) but was left in place — removing it means a large committed-vendor diff; do it in a dedicated housekeeping commit. |
| A5 | Existing demo/seed accounts and the two-tenant demo remain the acceptance environment; UrsbDemoSeeder was extended (objective) rather than rewritten. |
| A6 | Client users hold `system_role=client` AND a `client` ACL binding — both are seeded; custom setups must keep the two in sync. |

## Deferred (explicitly not built, spec-mapped)

Role-management UI beyond seeded roles (§17) · configurable per-client document templates (§15) · semantic contradiction detection (§10) · version content viewer/diff/restore (§22, F-16) · RTM design-coverage/AC gap classes (F-17) · personal workspace beyond the inbox (§19) · CI pipeline (F-26) · Eloquent global tenant scopes as structural backstop (F-20) · evidence file download endpoint · per-tenant AI spend quotas.
