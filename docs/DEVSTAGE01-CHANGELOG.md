# DevStage01 — Changelog (refinement pass 2026-07-04 → 05)

Baseline before this pass: commit `88b8ec2`, 246 tests. After: **299 tests**, all green. In-app user-facing feed stays in `database/changelog.json` (`composer release`).

| Commit | Change |
|---|---|
| `3970cec` | docs: current-state audit, product blueprint, implementation plan |
| `0f10fdf` | fix: remove 27 dead Track PMS files; bind ToolRegistry (chat 502 fixed); URSB-native chat tools (project_status, object_search); product README; archive stale extraction docs; chat regression tests |
| `1b47a17` | feat: server-side stage-gate enforcement via StageGateService (single authority), approve-with-recorded-exceptions, session phase guards (preAnalyze/capture), firewall send-back with reason, baseline **reopen** lifecycle |
| `7e94875` | feat: **Project Objective Baseline** — OBJECTIVE graph type (OBJ-NNNN), guided setup wizard, approval + re-approval on revise, BRS gate check, objective→requirement auto-tracing, demo objective |
| `98cd208` | feat: **discussions** at every level — internal/client visibility (query-enforced), blocking-the-gate, assign/due/resolve/reopen, comment **convert-to** requirement/risk/decision/issue/CR, inbox integration |
| `f87298d` | feat: **AI Refinement Engine** — 5 actions producing stored proposals (accept / accept-edit / reject), acceptance-criteria minting (REFINES), versioned prompts (config/prompts.php), strict-JSON retry→honest refusal, **ai_usage_log** token accounting |
| `7faba5d` | feat: **Objective Guardian** on change requests (6-way alignment classification, override-reason required on direct conflict) + downstream **impact disposition** (review_required → no_impact / update_required / invalidated) + before/after CR comparison |
| `974a664` | feat: **Next Best Action** panel (derived, deep-linked, reasoned) + per-stage contextual **guidance** cards (config/guidance.php) |
| (this commit) | docs: data model, AI architecture, user guide, admin guide, test plan, changelog, assumptions & decisions |

### Migrations added
1. `2026_07_04_100000` — stage_baselines reopen columns + status enum value; requirement_sessions firewall-reject columns
2. `2026_07_04_110000` — objects.type enum re-declared (adds `objective`)
3. `2026_07_04_120000` — discussions + discussion_comments
4. `2026_07_04_130000` — ai_suggestions + ai_usage_log
5. `2026_07_04_140000` — change_requests guardian_assessment + override_reason
