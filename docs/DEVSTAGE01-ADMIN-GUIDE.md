# DevStage01 — Admin Guide

**Audience:** platform admins (`system_role=admin`). Console lives under **Admin** in the sidebar.

## 1. Access control (`/admin/acl`)

- **Grant / revoke** scope bindings: user × role × scope (tenant or project). Roles are seeded (admin, director, project_pm, project_pe, project_member, client) × 8 actions (view, edit, retrieve, validate, approve, baseline, assign, export). Explicit deny beats permit; everything is deny-by-default through the PolicyDecisionPoint.
- **Delegation**: time-bound hand-offs — the delegator must hold covering access; delegations auto-expire (hourly `acl:expire`, scheduled) and can be revoked early.
- **Access audit** (`/admin/acl/audit`): immutable permit/deny log with user, action, scope, IP, request-id, reason; filterable; CSV export (formula-injection safe).
- Object/field rules: `restricted` objects are denied to scoped users; `confidential` bodies are redacted (admin bypasses). Rules are seeded via `AclProvisioner::seedRules`; re-provision with `app(AclProvisioner::class)->provision()`.

## 2. AI settings (`/admin/settings`)

- Paste **Anthropic** + **Voyage** API keys (stored encrypted, never rendered back; submit blank to keep) and tick **Enable AI/RAG**.
- Off-states are safe: pre-analysis falls back to the deterministic analyst, chat 404s, refinement buttons disable (objective-check degrades honestly).
- Cost visibility: `ai_usage_log` table records feature/model/token spend per call.

## 3. Feedback triage (`/admin/feedback`)

User-submitted feedback with attachments; resolve/reopen; downloads are content-type-clamped.

## 4. Releases ("What's New")

Conventional commits → `php artisan releases:generate` → `releases:sync` (or `composer release`). Commit the updated `database/changelog.json`. Users see the hub modal badge until they open it.

## 5. Operations

| Task | Command |
|---|---|
| Fresh install | `composer setup` |
| Demo data (2 tenants, full lifecycle) | `php artisan migrate:fresh --seed` |
| Tests | `php artisan test` (sqlite in-memory) |
| Formatting | `vendor/bin/pint --test` |
| Re-index evidence embeddings | `php artisan rag:index-evidence [--project=]` |
| Scheduler (only `acl:expire` hourly) | ensure `php artisan schedule:run` cron on the host |

**Static analysis caveat:** PHPStan crashes silently (exit 1, no output) on ZTS Windows PHP builds — run `vendor/bin/phpstan analyse` on CI/Linux instead; do not trust a silent exit as a pass.

**No queue worker is required** — nothing dispatches jobs; notifications are in-app rows.

## 6. Deploy

`deploy.sh` targets Hostinger multi-env via GitHub webhook: composer install, optional npm build (compiled assets are committed), config/route/view caching, `releases:sync`. `.env` is never committed.
