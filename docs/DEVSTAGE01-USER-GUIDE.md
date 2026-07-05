# DevStage01 — User Guide

**Audience:** analysts, PMs, reviewers, clients. Demo logins (password `password`): `admin@ursb.test`, `pm@ursb.test` (Acme ERP), `pm2@ursb.test` (Petron).

## The idea in one paragraph

DevStage01 walks a project from **why** (Objective) through **what** (BRS → URS → SRS) and **how** (SDS → detailed design) to **experience** (Prototype) and **proof** (Validation). Everything you create is a permanently-identified object (`BRS-REQ-0001`) with version history and trace links, so any design decision can be walked back to a requirement, the requirement to its evidence, and the evidence to the project objective.

## 1. Start a project (guided setup)

1. **Portfolio → New project** (admins/directors) — name, code, tenant.
2. You land in **Step 2: Capture the objective** — the statement, business problem, scope, out-of-scope, success measures, constraints. Only statement + problem are mandatory; fill the rest as it becomes known.
3. Have an approver open **Objective → Approve** — the BRS gate checks for this.
4. **Step 3:** grant your team access (Team page / admin ACL console), then add the first **module** (it auto-creates the nine lifecycle stages).

Revising an objective later creates a new version and sends it back for re-approval — the anchor is never silently changed.

## 2. Work a stage (the session loop)

1. Open the stage → **create a session** (a workshop/analysis unit).
2. **Add evidence** — paste text or upload PDF/DOCX/images. Evidence is immutable source material.
3. **Run AI pre-analysis** — drafts findings + requirements from each evidence item, trace-linked and marked *needs confirmation*. (Works offline with a deterministic fallback.)
4. **Quality firewall** — a reviewer with `validate` rights either passes the drafts or **sends them back** with a recorded reason.
5. **Start session → capture** — for every item: **Confirm / Revise (correct-complete) / Decide**. High-impact low-confidence items can't be quick-confirmed. **Scan for conflicts** flags duplicate-intent requirements.
6. **Consolidate → Approve** — approval is blocked while any item is unresolved.

## 3. Baseline at the gate

Open the **stage gate**. Checks: approved session · all items resolved · not already baselined · previous stage baselined · approved objective (BRS) · no open blocking discussions. Hard failures block; overridable ones can be passed by ticking **Baseline with exceptions** and recording a reason (frozen into the document). A baseline freezes object versions and renders as a document (HTML/PDF) and review deck (HTML/PPTX).

**Reopen:** the gate page of a baselined stage offers *Reopen baseline* (reason required) — members return to refinement, sessions come back alive, history stays intact.

## 4. Refine anything, anytime

- **Discussions** — on the project, a stage, a session or any object: start a thread, reply, assign, resolve. Mark *blocking* to hold the gate. Use **Convert to…** on a comment to mint a requirement, risk, decision, issue or change request from it. *Internal notes are invisible to client users; client-visible threads are shared.*
- **AI refinement** (object page): *Improve wording* (side-by-side proposal — accept, edit, or reject), *Challenge this*, *Generate acceptance criteria* (accepting mints AC-… objects), *Find missing information*, *Check against objective*. Nothing applies until you accept.
- **Change requests** — the only way to edit baselined content. Raising one auto-computes impact and asks the **Objective Guardian** for an alignment verdict; approving a *direct conflict* demands a recorded override reason. Applying versions the target and flags every downstream object **review required** — work them off on the CR page (*no impact / update required / invalidated*).

## 5. Know where you stand

- **Project page**: objective banner, **Next best actions** (what to do next and why), lifecycle band, register links.
- **Registers**: Objects browser, Design, Prototype, Verification (+defects), **RTM** (traced/unsupported/unverified/broken per requirement, CSV/PDF), Coverage, Risks, Decisions, Issues, Changes, Activity.
- **Inbox** (sidebar): firewall reviews, session approvals, CR approvals and discussions assigned to you.
- **Dashboards**: portfolio health cards + stage Gantt; *What's blocked* view.
- **Chat** (`/portfolio/chat`, when AI enabled): grounded answers over your accessible projects with citations and live status/search tools.

## 6. For client reviewers

You see only projects you're granted, only client-visible discussions, and confidential fields are redacted. You can read documents/registers, comment on client-visible threads, and open ones of your own — your threads are always client-visible.
