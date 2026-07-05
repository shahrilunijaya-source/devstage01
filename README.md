# DevStage01 — URSB Requirement-to-Prototype Platform

DevStage01 guides a software project from its original business idea through **Objective → BRS → URS → SRS → SDS → Prototype → Validation**, producing a connected chain of evidence: every design decision traces back to a system requirement, a user requirement, a business requirement, and the original project objective.

## What it does

- **Evidence-driven requirements**: paste or upload source material (text/PDF/DOCX); AI pre-analysis drafts findings and requirements which humans confirm, correct, complete or decide through a quality firewall.
- **Canonical object graph**: every artefact (evidence, requirement, risk, decision, design component, prototype element, test case, defect…) is a versioned, permanently-identified object (`BRS-REQ-0001`) with typed trace relationships.
- **Stage gates & baselines**: stages baseline into frozen, approvable snapshots that render as documents (HTML/PDF) and review decks (HTML/PPTX).
- **Change management**: baselined content is only editable through change requests with impact analysis and separation of duties.
- **Registers**: RTM, verification & validation, design, prototype, issues, risks, decisions, coverage — all derived live from the graph.
- **ACL**: deny-by-default policy decision point with tenant/project/module/stage/session scoping, delegation, field-level redaction and an immutable access audit.
- **AI assistant**: project-scoped RAG chat and refinement actions, gated behind admin-configured API keys (Anthropic + Voyage), with deterministic fallbacks.

## Stack

Laravel 13 · PHP 8.3 · MySQL (dev) / sqlite (tests) · Tailwind v3 + Vite + Alpine (compiled assets committed) · Anthropic + Voyage APIs (optional).

## Run it

```bash
composer setup                     # install, .env, key, migrate
php artisan migrate:fresh --seed   # full two-tenant demo data
php artisan serve                  # http://127.0.0.1:8000
```

Demo logins (password `password`): `admin@ursb.test` (admin), `pm@ursb.test` (PM, Acme ERP), `pm2@ursb.test` (PM, Petron Retail Ops).

To enable AI features: log in as admin → Settings → paste Anthropic + Voyage keys → tick *Enable AI/RAG*.

## Tests

```bash
php artisan test        # full suite, sqlite :memory:
vendor/bin/pint --test  # formatting
```

## Documentation

| Doc | Purpose |
|---|---|
| [docs/DEVSTAGE01-CURRENT-STATE-AUDIT.md](docs/DEVSTAGE01-CURRENT-STATE-AUDIT.md) | As-found system audit |
| [docs/DEVSTAGE01-PRODUCT-BLUEPRINT.md](docs/DEVSTAGE01-PRODUCT-BLUEPRINT.md) | Target platform design |
| [docs/DEVSTAGE01-IMPLEMENTATION-PLAN.md](docs/DEVSTAGE01-IMPLEMENTATION-PLAN.md) | Prioritised build plan |
| [docs/DEVSTAGE01-DATA-MODEL.md](docs/DEVSTAGE01-DATA-MODEL.md) | Schema & object graph |
| [docs/DEVSTAGE01-AI-ARCHITECTURE.md](docs/DEVSTAGE01-AI-ARCHITECTURE.md) | LLM integration design |
| [docs/DEVSTAGE01-USER-GUIDE.md](docs/DEVSTAGE01-USER-GUIDE.md) | End-user walkthrough |
| [docs/DEVSTAGE01-ADMIN-GUIDE.md](docs/DEVSTAGE01-ADMIN-GUIDE.md) | Admin console guide |

Historical extraction notes live in `docs/archive/`.
