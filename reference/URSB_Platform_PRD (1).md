# URSB Requirement-to-Prototype Platform - Product Requirements Document (PRD)

**Version:** 2026.1
**Status:** Draft for Build
**Model:** AI-First, Evidence-Led, Risk-Weighted, Model-Centric, Multi-Project, Traceable from inputs to all KRISA deliverables

> **Platform principle.** Evidence enters as immutable source. AI creates traceable hypotheses and structured objects. Humans validate and approve. Documents, diagrams, prototypes and tests are generated views. Changes update the model through controlled requests and regenerate every affected view.

> **Build note on ACL.** The Identity, Role and Access Control module (RBAC / ACL) is captured here as a first-class module (Module 3) and a dedicated section (Section 6). It was previously only implied by scattered controls (role-based review, role-based slide views, tenant and project isolation, approval authority). It is now explicit in the module list and technical architecture.

## Table of Contents

1. Overview
2. Users, Roles and Channels
3. Architecture (layers and planes)
4. Application Module List
5. Portfolio and Multi-Project Management Layer
6. Identity, Role and Access Control Module (RBAC / ACL)
7. Knowledge and Standards Layer
8. AI Orchestration and Reasoning Layer (model-neutral)
9. Core Platform and Session Engine (the shared engine)
10. Per-Stage Session Models (BRS, URS, SRS, SDS, SLD/DBD/IFD, Prototype)
11. End-to-End User Flow
12. Canonical Engineering Object Graph (data model)
13. Deck and Presentation Generation System
14. KRISA Deliverables and Document Generation
15. Functional Requirements
16. Non-Functional and Security Requirements
17. Metrics, Red Flags and AI vs Human Line
18. Roadmap, Assumptions and Open Items
19. Appendix A: Glossary

---

## 1. Overview

### 1.1 Summary

The URSB Requirement-to-Prototype Platform is a multi-tenant, multi-project system that runs the full requirements and engineering lifecycle (Business Requirements through to Prototype, Validation and Handover) as a series of structured, evidence-led sessions. AI does the heavy analytical work before each session and prepares the likely answer. URSB presents the evidence. The client verifies documented truth against system behaviour, controls and measurable requirements. Humans validate, decide and approve.

The platform does not treat documents as the deliverable. Every requirement, design element, rule, interface, prototype screen and test case is a structured object in a canonical engineering object graph. Documents, slides, matrices, diagrams, prototypes and tests are generated views of that model. This makes everything traceable, versioned, owned and regenerable when something changes.

### 1.2 Problem statement

- Document-centric chaos: requirements live in disconnected Word, Excel and PowerPoint files with no single source of truth and no reliable trace from evidence to test.
- Sessions that miss the truth: workshops capture opinions, not documented system reality. High-impact items get confirmed without scrutiny and rubber-stamping passes unnoticed.
- No traceability or governance: teams cannot show every requirement has evidence, acceptance criteria and a test. Baselines drift and audit trails are incomplete.
- Slow, repetitive preparation: analysts re-key content across stages, and AI is used ad hoc with no evidence binding, scope control or audit, which is unsafe for government work.

### 1.3 Product goals

- Run every lifecycle stage on one engine with stage-specific settings drawn from the Knowledge Book.
- Make the canonical object graph the single source of truth, with every artefact generated as a view of it.
- Bind every AI hypothesis to evidence (source, section, page, date, confidence) so no answer is unattributable.
- Enforce risk-weighted validation so high-impact, low-confidence items cannot be quick-confirmed.
- Give full traceability from evidence to baseline to test, with controlled change and impact analysis.
- Enforce tenant, project, object and field-level access through a dedicated access-control module, never by convention.
- Generate brand-consistent, governed documents and decks instead of hand-built files.
- Push the approved prototype build pack to Claude Code / repository for generation and version control.

### 1.4 Success metrics (target outcomes)

| Metric | Target |
|---|---|
| Requirements with linked evidence | 100% |
| Requirements with acceptance criteria | 100% |
| Post-approval change rate | Decrease |
| Stage baseline churn | Decrease |
| Traceability completeness | Increase |
| Average issue resolution time | Decrease |
| Evidence-to-baseline elapsed time | Decrease |
| Cross-project data leaks | 0 |

### 1.5 Guiding principles

- Evidence in, objects out, views generated.
- The canonical model is the source of truth.
- One stage may have many sessions that roll up to one stage baseline.
- AI provides hypotheses, humans decide.
- Every object is traceable, versioned and owned.
- Changes are controlled and impact is known.
- Security and isolation are never bypassed.
- AI should reduce unnecessary questioning, not reduce scrutiny.

---

## 2. Users, Roles and Channels

A role describes what a person does. It does not by itself grant access to a project, object or field. The Access Control module (Section 6) maps roles to scope and enforces every action.

### 2.1 URSB delivery roles

| Role | Primary responsibility |
|---|---|
| URSB Project Manager | Creates projects and systems, plans stages and sessions, manages portfolio, milestones, baselines, handover. |
| URSB Business Analyst | Configures projects, runs Claude pre-analysis, captures requirements, builds matrices, resolves comments. |
| URSB System Analyst (Claude as SA) | AI reasoning role. Reads, analyses, extracts, drafts hypotheses, detects gaps and conflicts, regenerates outputs. Always reviewed by a capable human. |
| URSB Architect | Critical technical reviewer for SDS and design-detail stages. Approves architecture and design decisions. |
| URSB Dev Lead | Owns prototype build readiness, build packs, interfaces and code generation handover. |
| URSB QA / Tester | Owns test cases, acceptance-criteria linkage, validation results and defect tracking. |

### 2.2 Client roles

| Role | Primary responsibility |
|---|---|
| Client Project Manager | Coordinates client participation, scope confirmation and stage progression. |
| Business Owner | Confirms business ownership and business rules, provides business-level approval. |
| Process Owner | Validates real operational practice against documented truth. |
| Champion / SME (Module Owner) | Owns a module or domain, attends sessions, provides detail, confirms current practice. |
| End User / Stakeholder | Provides user reality, validates journeys, confirms operational needs. |
| Approver / Signatory | Reviews deliverables, grants final or stage approval, authorises baselines. |

### 2.3 Channels

Web Application, Email / Notifications, Upload / Download, Reports / Dashboards, AI Chat (evidence-backed), API / Integration.

---

## 3. Architecture (layers and planes)

The platform is organised into layers. Two cross-cutting planes (Security and Governance, and Operations) apply to every layer.

| Layer | Description |
|---|---|
| 0. Portfolio and Multi-Project Management | All clients and projects. One client equals one tenant. One project equals one system. Many modules per system, many stages per module, many sessions per stage rolling up to a stage baseline. |
| 1. Inputs / Evidence | Tender documents, contracts, policies, SOPs, forms, as-is data, existing systems, organisation info, stakeholder inputs, historical projects, other evidence. Ingested as immutable evidence. |
| 2. Knowledge and Standards | KRISA Knowledge Book (shared/global), Methodology Library, Project Knowledge Base (tenant-isolated), Applicability and Mapping Service, version registry. Projects pinned to a Knowledge Book version. |
| 3. Core Platform and Session | Session Engine (Pre/During/Post), Workflow and Gate Engine, Change Management Engine, Document and View Engine, Metrics and Quality Analytics Engine. |
| 4. Canonical Engineering Object Graph | The source of truth. All artefacts are structured, linked, versioned, traceable objects, not files. |
| A. AI Orchestration and Reasoning (model-neutral) | Document understanding, reasoning, classification, embedding, diagram and code generation providers behind a registry and policy engine. Pluggable providers, same workflow. |

**Security and Governance Plane (enforced across all layers):** tenant and project isolation, role-based access control, object-level and document-level permissions, evidence-level access control, data classification and redaction, prevent cross-project retrieval, prompt-injection protection, approved model and data routing, encryption at rest and in transit, data residency and key management, AI training opt-out, audit trails (user, AI, data, admin), immutable approval and audit records, retention, legal hold and deletion policies.

**Operations and Monitoring Plane:** system and performance monitoring, usage analytics and dashboards, alerts and notifications, health checks and logging, SLA monitoring.

**Knowledge retrieval flow (secure and scoped):** User Identity and Role -> Authorisation Check (ACL decision point) -> Eligible Scope (Tenant / Project / Module / Stage / Session) -> Semantic Retrieval within scope only -> AI Processing -> Results and Explanations. No retrieval, AI call or write happens before the scope is resolved.

---

## 4. Application Module List

| # | Module | Type | Description |
|---|---|---|---|
| 1 | Portfolio and Multi-Project Management | Core | Portfolio dashboard, tenants, projects, milestones and Gantt, risks and issues, resource allocation, cross-project analytics. |
| 2 | Knowledge and Standards (KRISA) | Core | Knowledge Book, Methodology Library, Project Knowledge Base, Applicability and Mapping, version registry and project pinning. |
| **3** | **Identity, Role and Access Control (RBAC / ACL)** | **New / Security** | **Authentication confirms who the user is. ACL determines exactly what that user can access and do, down to project, module, session, object, document, slide, evidence, matrix and field level. See Section 6.** |
| 4 | Session Engine (Pre / During / Post) | Core | AI pre-analysis, evidence ingestion, draft hypotheses, facilitation and capture, confirm/correct/complete/decide, parking lot, consolidation, quality firewall. |
| 5 | Workflow and Gate Engine | Core | Stage-specific gates, quality firewall enforcement, validation rules by stage, readiness and exit criteria, approvals and sign-offs, baseline creation. |
| 6 | Change Management Engine | Core | Change request intake, impact analysis with auto-trace, affected object identification, cost and schedule impact, approvals, supersession, regeneration of affected outputs. |
| 7 | Document and View Engine | Core | KRISA document generation, custom reports, dashboards, matrix views, diagrams and visualisations, export to Word, PDF, PPTX and Excel. |
| 8 | Metrics and Quality Analytics Engine | Core | Session metrics, quality metrics, traceability metrics, red-flag anomaly detection (advisory, not judgment), trend analysis, predictive insights. |
| 9 | AI Orchestration and Reasoning (model-neutral) | AI | Provider registry and policy engine, document understanding, reasoning, classification, embedding, diagram and prototype/code generation, private/on-prem routing. |
| 10 | Canonical Object Graph and Data Services | Core | Object store, canonical object DB, version and baseline DB, traceability graph DB, session and activity DB, audit and approval DB, document store, knowledge store. |
| 11 | Deck and Presentation Generation (Design System) | Core | PowerPoint master template, machine-readable design system, slide-generation engine, render pipeline, approved layout and component library, governance controls. See Section 13. |
| 12 | Operations, Monitoring and Admin | Core | System and performance monitoring, usage analytics, alerts, health checks, SLA monitoring, platform administration. |

**Why the ACL module was added.** The architecture already showed role-based review, role-based slide views, tenant and project isolation, user roles and permissions, and approval authority. These were presented as features and controls, not as a single module that owns the access decision. The platform needs one authoritative place where every access question is answered and enforced. That is Module 3.

---

## 5. Portfolio and Multi-Project Management Layer

**Hierarchy:** One Client / Agency = One Tenant. One Project = One System. One System = Many Modules. One Module processes all stages. One Stage = Many Sessions (Stage dimension = Module / Champion / Process / Domain / Location). Approved session outputs roll up to one Stage Baseline.

**Portfolio dashboard:** all clients and projects, deliverable status, milestones and Gantt, risks and issues, resource allocation, workload and capacity, cross-project analytics.

**Project context:** one client/agency equals one tenant, one project equals one system, many modules and domains, each module goes through all stages, each stage may have many sessions rolling up to a stage baseline. Lifecycle stages: BRS, URS, SRS, SDS, Design, Specialist (optional), Prototype.

**Project view:** Gantt and milestones, team and roles, deliverables, risks and issues, baselines and versions.

---

## 6. Identity, Role and Access Control Module (RBAC / ACL)

**Architectural definition.** Authentication confirms who the user is. The Access Control module determines exactly what that user can access and do. Authentication is the door. ACL is everything beyond it.

The platform already implies access control through role-based review, role-based slide views, tenant and project isolation, user roles and permissions, and approval authority. Those are correct but scattered. This module makes access control a real component with its own data model, decision logic, enforcement points and audit. It sits inside the Security and Governance Plane and is invoked by every engine before any read, edit, validate, approve or baseline operation. It does not invalidate any existing diagram. It is the component those diagrams already depend on.

### 6.1 What this module controls

- **Scope access:** who can access each tenant, project, system, module, stage and session.
- **Object action permissions:** who can view, edit, validate, approve or baseline each object in the canonical graph.
- **Artefact permissions:** document, slide, evidence and matrix level permissions on generated and source artefacts.
- **Field-level and sensitive-data restrictions:** restrict specific fields or classified data within an object, including redaction for unauthorised viewers.
- **Role assignment:** assign roles by tenant and project, so the same person can hold different rights in different projects.
- **Temporary access and delegation:** grant time-bounded access and delegate authority, with automatic expiry and revocation.
- **Separation of duties:** prevent one person holding conflicting rights, for example authoring and approving the same baseline.
- **Access audit history:** immutable record of every grant, change, use and denial of access, queryable for review and compliance.

### 6.2 Decision and enforcement

1. Authenticate: confirm identity (SSO / IdP).
2. Resolve role: by tenant and project.
3. Evaluate policy: scope + object + field + action.
4. Decide: permit or deny (Policy Decision Point).
5. Enforce: at every engine (Policy Enforcement Point) including Session Engine, Document Engine, Change Management Engine, AI retrieval and exports.
6. Audit: record the decision.

### 6.3 Permission model (recommended)

A layered model combining role-based access control with attribute and relationship rules:

- **Roles** define the action set a person can perform (view, edit, validate, approve, baseline, admin).
- **Scope bindings** attach a role to a tenant, project, module, stage or session.
- **Object and field rules** refine rights by object type, status, classification and confidence.
- **Constraints** add separation-of-duties, time limits, delegation and approval thresholds.

Default posture is deny. Access must be explicitly granted, and cross-project retrieval is prevented by design.

### 6.4 Functional requirements (Access Control)

| ID | Requirement | Priority |
|---|---|---|
| ACL-01 | Authenticate every user through the configured identity provider before any access decision. | Must |
| ACL-02 | Control access at tenant, project, system, module, stage and session level. | Must |
| ACL-03 | Control, per object, whether a user can view, edit, validate, approve or baseline it. | Must |
| ACL-04 | Apply document, slide, evidence and matrix level permissions to source and generated artefacts. | Must |
| ACL-05 | Support field-level and sensitive-data restrictions, including redaction for unauthorised viewers. | Must |
| ACL-06 | Assign roles by tenant and project so a user may hold different rights in different projects. | Must |
| ACL-07 | Support temporary, time-bounded access and delegation with automatic expiry and revocation. | Should |
| ACL-08 | Enforce separation of duties, preventing conflicting rights such as authoring and approving the same baseline. | Must |
| ACL-09 | Maintain an immutable access audit history of every grant, change, use and denial. | Must |
| ACL-10 | Default to deny, require explicit grants, and prevent cross-project retrieval by design. | Must |
| ACL-11 | Expose access decisions to AI retrieval so semantic search and AI processing operate only within the user's eligible scope. | Must |
| ACL-12 | Provide an administration interface for roles, scope bindings, delegation and access review. | Should |

The existing posters do not need to be revised immediately. The existing role, security and approval components already imply this module. Capturing it now in the module list and technical architecture is enough to proceed to development.

---

## 7. Knowledge and Standards Layer (the Knowledge Base)

### 7.1 KRISA Knowledge Book (shared / global)

KRISA deliverables (D01-D19), document structures and sections, question banks (BRS, URS, SRS, SDS, etc.), matrices and model definitions, coverage rules and validation logic, stage gates and checklists, requirement quality rules, terminology, glossary and guidelines. Version controlled through a Knowledge Version Registry.

### 7.2 Methodology Library (shared / global)

Bengkel methodology, session framework (Pre/During/Post), role guidance and responsibilities, templates and instructions, best practices and playbooks, risk and impact framework, quality and acceptance criteria.

### 7.3 Project Knowledge Base (tenant / project isolated)

Project context and scope, tailored question sets, tailored matrices and rules, approved assumptions, reference documents, lessons learned (project specific), AI learnings (project specific).

### 7.4 Applicability and Mapping Service (Project to Knowledge Book pin)

Each project is pinned to a specific Knowledge Book version, with stage-specific applicability, mappings, overrides and extensions, rules, thresholds and parameters per stage. Prevents retroactive changes and applies tenant permissions. Example pin: `KB v2026.1`.

### 7.5 Supporting stores

Vector index / retrieval store, semantic search engine, rules and inference engine, prompt library and patterns, knowledge version registry, applicability and mapping service.

---

## 8. AI Orchestration and Reasoning Layer (model-neutral)

Providers behind a registry and policy engine, pluggable without changing the workflow:

- Document Understanding Provider
- Reasoning Provider
- Classification Provider
- Embedding Provider
- Diagram / Generation Provider
- Prototype / Code Generation Provider
- Private / On-Prem Model Provider
- Provider Registry and Policy Engine

Supported provider families are model-neutral (for example Claude, OpenAI, Gemini, Azure, Llama, on-prem). Pluggable providers, same workflow. Every AI output is bound to evidence (source, section, page, date, confidence) and restricted to the user's eligible scope.

---

## 9. Core Platform and Session Engine (the shared engine)

The same engine runs every stage with different settings from the Knowledge Book. AI prepares the likely answer, URSB presents the evidence, the client verifies documented truth against system behaviour, controls and measurable requirements.

### 9.1 Engine sub-modules

- **3.1 Session Engine (per session scope = Stage x Module x Champion / Domain):** PRE (AI pre-analysis, evidence ingestion, information extraction, draft hypotheses, pre-session checklist), DURING (facilitation and capture, confirm/correct/complete/decide, live validation, parking lot capture, conflict identification), POST (consolidation, quality firewall review, matrix auto-population, metrics calculation, session outputs).
- **3.2 Workflow and Gate Engine:** stage-specific gates, quality firewall enforcement, validation rules by stage, readiness and exit criteria, approvals and sign-offs, baseline creation.
- **3.3 Change Management Engine:** change request intake, impact analysis (auto trace), affected object identification, cost and schedule impact, risk and compliance impact, approvals and decision, new versions and supersession, regenerate affected outputs.
- **3.4 Document and View Engine:** KRISA document generation, custom reports, dashboards, matrix views, diagrams and visualisations, export (Word, PDF, PPTX, Excel).
- **3.5 Metrics and Quality Analytics Engine:** session metrics, quality metrics, traceability metrics, red-flag anomaly detection (advisory, not judgment), trend analysis, predictive insights.

### 9.2 Shared session enforcement contract (applies to every session, every stage)

- Confidence and impact are tagged on every item.
- No quick confirm on high-impact items.
- Every item must be Confirmed, Corrected, Completed or Decided.
- Conflict resolution is required for conflicting evidence.
- Parking lot workflow captures unresolved items with owner and due date.
- Documented truth is validated against actual system behaviour.
- Re-convene rules apply when too many critical items are parked.
- Session metrics and a rubber-stamping anomaly signal are computed.

### 9.3 The five phases of every session

1. **AI Pre-Analysis (before session):** ingest evidence, extract content, draft hypotheses, build the pre-session pack and PPTX.
2. **Quality Firewall (mandatory human review):** AI preliminary output, preparation review, critical technical review by a capable reviewer, approved for client presentation. A weak SA cannot be the only reviewer. High-impact items must be checked by capable technical reviewers. The PPTX cannot be marked "Ready" until review is approved.
3. **Session Preparation (by URSB):** review AI findings and citations, validate scope and objective, review gaps/conflicts/assumptions, confirm participants, confirm agenda and timing, confirm decision authorities, review facilitator script and PPTX, ensure matrices and templates are ready, readiness check, then "Ready for Session".
4. **Evidence-Led Session (with client):** Present AI Findings (PPTX) -> Client Verifies -> Capture Response -> Lightweight Live AI Capture -> Next Item.
5. **Post-Session (heavy AI regeneration + URSB review):** AI Consolidation -> Heavy Regeneration -> URSB Review -> Missing Info Requests -> Draft Outputs.

**Client response options:** Confirm, Amend/Correct, Reject/Not Correct, Decision Required, Park/To Be Reviewed.

**Question taxonomy:** Confirm (validate low-risk, evidence-backed items), Correct (resolve conflicts and clarify documented detail), Complete (fill missing information), Decide (make scope, technical, policy or target decisions). High-risk or low-confidence items cannot use quick confirm.

### 9.4 Risk-weighted validation rules

| Category | Validation approach | Purpose |
|---|---|---|
| High confidence, low impact | Quick confirmation | Efficient validation |
| High confidence, high impact | Explicit verification | Protect critical items |
| Low confidence | Active probing | Increase confidence |
| Conflicting evidence | Structured resolution | Find the truth |
| Missing information | Open elicitation | Complete the picture |
| Decision required | Decision workflow | Obtain authority |
| Operational practice uncertain | Process owner validation | Validate real practice |

### 9.5 Status classification (used throughout)

Confirmed by Evidence, Needs Confirmation, Conflict Detected, Missing / Unknown, Decision Required, Not Applicable.

### 9.6 Parking lot / resolution loop

Parked Item -> Assign Owner -> Set Due Date -> Resolution Route -> Resolve -> Update affected matrices -> Revalidate. Re-convene is required if too many critical items are parked or major conflicts remain. Resolution routes vary by stage (document submission, management decision, policy clarification, technical/architecture/security/data review, cross-team consultation, follow-up session).

### 9.7 Approval and baseline

Approval -> Sign-off -> Baseline. Required approvals depend on the stage. Freeze baseline, version lock, create baseline record. No baseline is changed without a controlled change request.

### 9.8 AI vs human dividing line

**AI does (thinking and analysis):** read, analyse, extract and compare evidence; detect gaps and conflicts; draft requirements, matrices and models; prepare PPTX and script; regenerate outputs after the session.

**Human does (judgment and authority):** present and manage the room; verify documented truth against the real system; correct, complete and decide; approve, prioritise and sign off; manage risks, trade-offs, constraints, relationships and escalation.

### 9.9 Footnote principles

AI should reduce unnecessary questioning, not reduce scrutiny. Every AI finding must show source, section/page, date and confidence level.

---

## 10. Per-Stage Session Models

Every stage uses the shared engine (Section 9) with stage-specific inputs, AI capabilities, matrices, high-impact examples, outputs, exit gates and handover. BRS follows the same pattern as the earliest stage; URS through Prototype are detailed below.

### 10.1 BRS (Business Requirements Session)

First stage. Establishes business scope, objectives, stakeholders and opportunities. Same engine and enforcement contract. Outputs roll up to a BRS stage baseline that feeds URS. BRS requires more Complete and Decide questions than later confirm-heavy stages because little prior baseline exists.

### 10.2 URS (User Requirements Session)

**Inputs / evidence:** Approved BRS Baseline, Stakeholder Matrix, User Lists, Job Descriptions, Current Forms/Screens, SOPs, Complaints/Usability Feedback, Reports, Policies, Existing Manuals.

**AI capabilities (pre-analysis):** Document Ingestion and OCR, User-Role Extraction, User-Journey Discovery (draft), Task and Scenario Extraction, Role-Permission Inference, Notification/Reporting Analysis, Gap and Conflict Detection, Summary and Insights.

**AI pre-output package:** PPTX package, Draft User Roles and Personas, Draft User Goals and Journeys, Draft User Tasks/Use Cases, Access and Permission Hypothesis, Approval and Notification Hypothesis, Report and Exception Catalogue, Preliminary User Requirements, Questions for Confirmation Only, Facilitator Script, Required Decision List.

**Quality firewall:** AI Preliminary URS -> BA/SA Preparation Review -> BA Lead/Architect Critical Review -> Approved for Client Presentation.

**Question framing:** Is this how users actually work, or only what the documents and current screens suggest?

**Examples of high-impact URS items:** role permissions, approval authority, user journeys, user exceptions, notification rules, report needs, accessibility needs, user acceptance conditions.

**URS matrices:** User Role Catalogue, User Persona/Role Matrix, User Goal Matrix, User Journey Matrix, User Task/Use Case Matrix, Role-Permission Matrix, Approval Authority Matrix, Notification Matrix, Report Requirement Matrix, User Exception Matrix, Accessibility and Usability Matrix, User Acceptance Matrix, BRS-to-URS Traceability Matrix.

**Outputs:** Validated User Roles and Personas, User Goals and Journeys, User Requirement Catalogue, Role-Permission Model, Approval and Notification Rules, Report Requirements, User Exception Register, Accessibility and Usability Requirements, User Acceptance Criteria, All Supporting Matrices, URS Document, URS Baseline.

**Exit gate:** all user groups represented, roles validated, journeys completed, every UR linked to a BR, permissions and approvals confirmed, acceptance conditions defined, user representatives validate, URS baseline frozen.

**Handover to SRS:** user requirements, role and permission data, journeys and tasks, exception scenarios, report and notification needs, acceptance conditions.

**Note:** URS is user-truth and may require more Complete and Decide questions than BRS.

### 10.3 SRS (System Requirements Session)

**Inputs / evidence:** Approved BRS and URS Baselines, User Journeys and Process Maps, Business Rules and Policies, Report Catalogue and Output Lists, Data Inventory and Data Dictionary, Integration Inventory and Interface Catalogue, ICT Policies and Governance, Security Standards and Compliance, Existing System Documentation, Performance/Volume Data and Benchmarks, Operations and User Manuals.

**AI capabilities (pre-analysis):** Document Ingestion and OCR, Function/Actor Extraction, System-Boundary Draft, Use-Case Discovery, FR/NFR Extraction, Data and Interface Extraction, Security/Audit Extraction, Gap and Conflict Detection.

**AI pre-output package:** PPTX package, Draft Actor/Function Map, Draft Use Cases, Draft Functional Requirements, Draft Non-Functional Requirements, Draft Data Requirement Matrix, Draft Integration Inventory, Security/Audit Hypothesis, Error and Exception Catalogue, Preliminary Acceptance Criteria, Questions for Confirmation Only, Facilitator Script, Required Decision List.

**Quality firewall:** AI Preliminary SRS -> SA/BA Preparation Review -> BA Lead/Architect Technical Critical Review -> Approved for Client Presentation.

**Question framing:** Is this what the system must actually do, or only what the documents imply? Separate documented truth from actual system behaviour and controls.

**Examples of high-impact SRS items:** security controls, NFR thresholds (for example performance), approval logic and business rules, validation rules and calculations, integration requirements, data retention and privacy handling, audit trail and logging, system boundary and exclusions, acceptance criteria.

**SRS matrices:** Actor Catalogue, Function Hierarchy, Actor-Function Matrix, Use-Case Catalogue, Functional Requirement Matrix, Non-Functional Requirement Matrix, Data Requirement Matrix, CRUD Matrix, Interface Requirement Matrix, Security Requirement Matrix, Audit Trail Matrix, Error and Exception Matrix, Reporting Matrix, Acceptance Criteria Matrix, BRS-URS-SRS Traceability Matrix.

**Outputs:** Validated Actor/Function Model, Use Cases, Functional Requirement Catalogue, Non-Functional Requirements, Validation and Business Rules, Data Requirements, Integration Requirements, Security and Audit Requirements, Error and Exception Handling, Acceptance Criteria, All Supporting Matrices, SRS Document (D03), SRS Baseline.

**Exit gate:** all critical URs mapped to SRS, FRs clear and testable, NFRs measurable, data and integration requirements defined, security and audit covered, acceptance criteria complete, no unresolved critical contradictions, SRS baseline frozen.

**Handover to SDS:** approved FR/NFR set, data and interface requirements, security and audit controls, error scenarios, acceptance criteria, traceability.

**Note:** SRS requires more Complete and Decide questions than BRS.

### 10.4 SDS (System Design Session)

**Inputs / evidence:** Approved SRS Baseline, Use Cases, FR/NFR Set, Data Requirements, Integration Requirements, Security Requirements, Infrastructure Standards, Deployment Constraints, Approved Stack, Coding Standards, Current Architecture (where relevant).

**AI capabilities (pre-analysis):** Document Ingestion and OCR, Architecture Option Draft, Component/Function Mapping, Workflow/State Design Draft, Database Design Draft, API/Interface Draft, Security Design Draft, Deployment/Infrastructure Draft, Gap and Conflict Detection, Summary and Insights.

**AI pre-output package:** PPTX package, Draft Architecture View, Draft Component/Module Map, Draft Workflow/State Model, Draft Database/ERD Hypothesis, Draft API/Integration Design, Draft Security Design, Draft Deployment Model, Draft Screen/Navigation Structure, Prototype Build Readiness List, Questions for Confirmation Only, Facilitator Script, Required Decision List.

**Quality firewall:** AI Preliminary SDS -> SA/Architect Preparation Review -> Architect/Tech Lead/Data Lead Critical Review -> Approved for Client Presentation.

**Question framing:** Is this the design we actually intend to build, or only the most likely design inferred from the requirements?

**Examples of high-impact SDS items:** architecture decisions, schema keys and relationships, API contracts, state transitions, security controls, migration mappings, deployment topology, backup/recovery design, integrations, prototype build readiness.

**SDS matrices:** Architecture Decision Register, Module-Component Matrix, SRS-to-Component Traceability Matrix, Workflow/State Matrix, Physical Data Model/ERD, Table and Column Catalogue, API Contract Catalogue, Integration Mapping Matrix, Security Control Matrix, Screen Inventory, Navigation Model, Deployment Matrix, Infrastructure Matrix, Prototype Build Pack Checklist.

**Outputs:** Approved Architecture, Module and Component Design, Workflow/State Design, Database Design, API and Integration Design, Security Design, UI/Navigation Structure, Deployment/Infrastructure Design, Prototype Build Pack, All Supporting Matrices, SDS Document, SDS Baseline.

**Exit gate:** critical SRS requirements mapped to design, architecture approved, data model validated, API contracts defined, security design reviewed, UI and workflows defined, prototype build pack approved, SDS baseline frozen.

**Handover to SLD/DBD/IFD or Prototype:** component design details, DB objects and mappings, interface/API details, state models, screen structure, build constraints.

**Note:** SDS requires strong Decide and Correct activity. Metrics include architectural change per round.

### 10.5 SLD / DBD / IFD (Design Detail Session)

**Inputs / evidence:** Approved SDS Baseline, Architecture Decisions, Component/Module Design, Data Model, Interface Requirements, Security Design, Deployment Constraints, Standards and Guidelines, Prototype Findings (if available).

**AI capabilities (pre-analysis):** Document Ingestion and OCR, Component-Detail Extraction, DB Object and Table-Detail (draft), Interface/API Contract Detail (draft), Sequence/Data-Structure Extraction, Field Mapping Extraction, Validation-Rule Detail Extraction, Gap and Conflict Detection, Summary and Insights.

**AI pre-output package:** PPTX package, Draft Component Design Detail Sheets, Draft DB Objects/Tables/Views, Draft Interface/API Contracts, Draft Data Structures and Mappings, Draft Sequence or Interaction Detail, Draft Validation and Error Handling Detail, Questions for Confirmation (only what we need to ask), Facilitator Script, Required Decision List.

**Quality firewall:** AI Preliminary SLD/DBD/IFD -> Design-Team Preparation Review -> Architect/Dev Lead/Data Lead Critical Review -> Approved for Client Presentation.

**Question framing:** Is this build-ready detail correct, or only the most likely detail inferred from the design and requirements?

**Examples of high-impact items:** primary/foreign keys, field mappings, API request/response contracts, state transition detail, sequence behaviour, validation rules, error codes, migration mappings, security fields, audit fields.

**SLD/DBD/IFD matrices:** Component Detail Matrix, Database Object Catalogue, Table/Column Detail Matrix, Field Mapping Matrix, Interface/API Contract Matrix, Sequence/Interaction Matrix, Data Structure Catalogue, Validation Rule Matrix, Error Code Matrix, Security and Audit Field Matrix, SDS-to-Design-Detail Traceability Matrix, Policy-Practice Gap Register.

**Outputs:** Component Design Sheets, Database Object Details, Interface/API Specifications, Data Structures, Field Mappings, Validation and Error Detail, All Supporting Matrices, SLD/DBD/IFD Documents (PDF + Source), Baseline.

**Exit gate:** critical SDS outputs mapped to design detail, DB objects validated, interface contracts defined, mappings confirmed, security and audit detail covered, build-ready detail approved, baseline frozen.

**Handover to Prototype/Build:** build-ready objects, schema details, interfaces, mappings, validation rules, error handling, traceability.

**Note:** highly detail-sensitive; requires capable technical reviewers and decision makers. Metrics include operationally-documented gap found.

### 10.6 Prototype / Validation Session

**Inputs / evidence:** Approved SRS, Approved SDS, Approved SLD/DBD/IFD Baselines, Prototype Build Pack (Screens, Flows, Assets), Repository/Build Notes (change logs, commits), Acceptance Criteria (functional and non-functional), User Journeys/Personas, Test Scenarios/Test Data, Prior Prototype Findings (issues, feedback, decisions).

**AI capabilities (pre-analysis):** Document Ingestion and OCR, Build-Pack Verification, Prototype Coverage Analysis, Screen/Flow Extraction, Requirement-to-Prototype Mapping, Acceptance-Criteria Extraction, Gap and Conflict Detection, Summary and Insights.

**AI pre-output package:** PPTX package, Prototype Walkthrough Agenda, Coverage Map (requirements vs prototype), Requirement-to-Screen/Function Map, User-Flow and Screen Summary, Gap List (with evidence), Issue Candidates (High/Med/Low), Questions for Confirmation Only, Facilitator Script, Required Decision List, References and Citations, Traceability Snapshot, Assumptions Noted.

**Quality firewall:** AI Preliminary Validation Pack -> BA/SA/Dev Preparation Review -> Architect/Product/Technical Critical Review -> Approved for Client Presentation. High-impact prototype findings must be checked by capable reviewers.

**Session flow:** Present Prototype and Findings (PPTX / Demo) -> Client Verifies -> Capture Response -> Lightweight Live AI Capture -> Next Item.

**Question framing:** Does the prototype reflect the approved requirements and actual user needs, or only a likely interpretation? Separate documented truth from actual operational practice.

**Examples of high-impact items:** critical user flows, access control and permissions, approval flow, data persistence/storage, key reports and data outputs, validation rules and logic, integration stubs/APIs, acceptance criteria coverage, scope exclusions.

**Prototype/Validation matrices:** Prototype Coverage Matrix, Requirement-to-Prototype Traceability Matrix, Screen/Flow Inventory, User Walkthrough Matrix, Validation Findings Register, Usability Feedback Matrix, Issue/Defect Matrix, Change Request Matrix, Acceptance Criteria Matrix, Gap and Exclusion Matrix.

**Outputs:** Prototype Validation Report, Requirement-to-Prototype Traceability, Screen and Flow Inventory, Issue/Defect Log, Change Request Register, Usability Findings, Acceptance Status Summary, All Supporting Matrices, Prototype/Validation Baseline Pack.

**Exit gate:** prototype walkthrough completed, critical flows validated, issues classified and captured, change requests assigned, acceptance criteria reviewed, no unresolved critical blockers for next action, baseline or decision pack frozen.

**Handover to Build Iteration / Acceptance / Deployment:** validated findings, issue list, change requests, updated traceability, acceptance status.

**Note:** prototype validation is a feedback loop into requirements and design.

### 10.7 Balanced session metrics and red flag (all stages)

**Metrics:** pre-answered coverage, confirmation rate, correction rate, high-impact correction rate, missing-info closure rate, conflict-resolution rate, parked-item percentage, post-session amendment rate. Stage-specific additions include user-reality gaps found (URS), technically-documented gap found (SRS), architectural change per round (SDS), operationally-documented gap found (SLD/DBD/IFD and Prototype), prototype coverage % and acceptance coverage % (Prototype).

**Red flag:** if high-impact confirmation is 100% and high-impact correction is 0%, possible rubber-stamping. Anomaly signals are advisory, not judgments.

---

## 11. End-to-End User Flow

One project = one system; one system may have many modules. One main session per stage. New uploaded evidence is stored in the project knowledge base and re-analysed by Claude SA. AI assists at each step but humans validate and approve. All outputs are versioned and traceable end-to-end. The Knowledge Base is shared/global plus project-specific.

**Top-level phases:** Project Initiation -> Requirement Sessions -> Requirements Lifecycle -> Prototype Lifecycle -> Documents and Handover.

**Process columns:** A. Portfolio and Project Setup, B. Pre-Session, C. During Session, D. Post-Session, E. Validation and Approval, F. Next-Stage Handover, G. Prototype and Test, H. KRISA Documents and Handover.

### 11.1 By user (swimlane)

**URSB Project Manager**
- Portfolio setup: create project/system, register client and tenant, one project = one system, define modules, define deliverables, upload Gantt/milestones.
- Pre-session: plan stage session, create BRS/URS/SRS/SDS session, set date and agenda, identify attendees, send invitations.
- During session: facilitate session, lead session, guide stage flow, confirm decisions, manage participants.
- Post-session: consolidate, merge notes, merge AI drafts, update matrices, generate outputs.
- Validation and approval: submit for validation, submit to owner, track comments, coordinate resolution.
- Handover: confirm completion, baseline outputs, trigger next stage.

**URSB Business Analyst / System Analyst**
- Portfolio setup: configure project, set up structure, roles and permissions, approval matrix, deliverable scope, session stages.
- Pre-session: AI pre-analysis and checklist, run Claude SA analysis, use Knowledge Base, check readiness, detect gaps/conflicts, prepare questions.
- During session: capture requirements, record answers, build matrices, log issues/decisions/actions.
- Post-session: AI draft and analysis, generate drafts, extract rules/data, detect gaps/conflicts, quality check.
- Validation and approval: update and resolve, address comments, clarify information, update requirements, track changes.
- Next-stage handover: link and trace, link to upstream, prepare traceability, map to next stage.
- Prototype and test: prepare build pack, compile approved requirements, data model and flows, API contracts, acceptance criteria.
- Documents and handover: generate documents (D01, D02, D03, D04 and other applicable KRISA docs), review and finalise, submit to client.

**Client Business Owner / Process Owner**
- Portfolio setup: confirm ownership, confirm business owner, process owner, approval authority.
- During session: provide input, confirm business rules, confirm process, confirm priorities, desired outcomes.
- Post-session: review outputs, review session outputs, validate accuracy, provide comments, request changes.
- Validation and approval: approve, confirm requirements, official approval, sign off stage, approve baseline.

**Client Champion / SME / End User**
- Portfolio setup: upload documents, SOPs, forms, policies, reports and data, screenshots/manuals, tender docs.
- Pre-session: complete templates, process details, pain points, current practice, wishlist/ideas.
- During session: participate, attend session, provide details, confirm steps, validate operational reality.
- Post-session: review and provide feedback, review outputs, suggest improvements, confirm completeness.

**Client ICT / Technical Team**
- Validation and approval: technical validation, validate SRS/SDS, review integrations, security/NFR, provide comments.
- Next-stage handover: confirm handover, confirm technical completeness, accept handover, ready for build.
- Prototype and test: prototype review, walkthrough prototype, verify functionality, provide feedback, log issues.

**Client Approver**
- Knowledge Base and Claude SA support pre-analysis.
- Validation and approval: final/stage approval, review deliverables, ensure compliance, grant approval.
- Documents and handover: accept handover, review all deliverables, final approval, accept handover.

### 11.2 Stage flow (one system, one session per stage)

BRS (understand business, scope and objectives, stakeholders, opportunities) -> URS (user needs, goals and journeys, use cases, user validation) -> SRS (functional requirements, non-functional requirements, business rules, system validation) -> SDS (architecture design, data design, process design, display design) -> SLD/DBD/IFD (component design, database objects, interfaces/APIs, data structures) -> Prototype (build proof of concept, prototype generation, user feedback, iterative refinement) -> Validation and Acceptance (review and validate, test and verify, accept criteria, approval) -> Deploy and Handover (deploy to environment, training and docs, go-live support, handover and close).

### 11.3 Prototype build pack to Claude Code

The approved prototype build pack is pushed to Claude Code / code repository for generation and version control.

### 11.4 KRISA outputs

KRISA outputs include diagrams (DFD, ERD, BPMN, etc.), matrices and traceability (Trace, RACI, etc.), PPTX/Word/PDF deliverables, baseline packs and versioned outputs.

### 11.5 Legend and notes

**Legend (line styles and symbols):** main flow (stage progression), information flow, feedback loop (comments/changes), decision point/approval gate.

**Notes:** (1) AI assists at each step but humans validate and approve. (2) One project = one system; one system may have many modules. (3) Knowledge Base is shared/global plus project-specific. (4) All outputs are versioned and traceable end-to-end.

---

## 12. Canonical Engineering Object Graph (data model)

All project artefacts are structured objects, not files. Documents are generated views of this model. Every object is linked, versioned and traceable.

### 12.1 Core object types

Evidence, Finding, User Need, Business Requirement, User Requirement, Functional Requirement, Non-Functional Requirement, Business Rule, Assumption, Conflict, Decision, Risk, Acceptance Criterion, Design Decision, Design Component, Interface, Database Object, Prototype Element, Test Case, Test Result, Defect, Issue, Approval, Baseline, Change Request, Trace Relationship.

### 12.2 Common object metadata

Permanent ID, owner, source, status, confidence, impact, version, stage, module, linked relationships, effective date, baseline, approval record, timestamps.

### 12.3 Trace example (object chain)

```
EVD-0027 (Evidence)
  -> FIND-0014 (Finding)
  -> BRS-REQ-0018 (Business Requirement)
  -> URS-REQ-0041 (User Requirement)
  -> SRS-FR-0102 (Functional Requirement)
  -> SDS-COMP-0012 (Design Component)
  -> API-003 (Interface)
  -> PROTO-SCREEN-07 (Prototype Element)
  -> AC-0045 (Acceptance Criterion)
  -> UAT-TC-019 (Test Case)
```

### 12.4 Data stores

Object Store (raw evidence), Canonical Object DB (graph / relational), Version and Baseline DB (immutable snapshots), Traceability Graph DB (relationships), Session and Activity DB (events, metrics), Audit and Approval DB (immutable logs), Document Store (generated outputs), Knowledge Store (vector + metadata).

---

## 13. Deck and Presentation Generation System

The deck system has three sources of truth working together: a visual master template, a machine-readable design system, and a slide-generation engine. Claude provides content intelligence and structure. The URSB application provides design governance, layout control and secure generation. Slides are views over linked objects, not standalone content.

### 13.1 PowerPoint master template (visual source of truth)

Corporate-branded .pptx with approved layouts and components. Approved layouts: Cover, Section Divider, Title and Content, Two Column, Process Diagram, Architecture Diagram, Matrix/Table, Dashboard, Issue/Decision, Validation, Closing, Annex/Appendix. Includes corporate branding (logo, colours, fonts), header/footer/page number, 12-15 approved layouts, icon set, chart styles and image treatment.

### 13.2 Machine-readable design system (logic source of truth)

Rules and tokens in JSON.

**Design tokens:**
- Aspect ratio: 16:9
- Font family: Aptos
- Type scale: H1 28 Bold, H2 20 Semibold, Body 16 Regular, Footnote 9 (title 28 / body 16 / footer 9)
- Colours: primary `#17365D`, secondary `#2F75B5`, accent `#00A6A6`, background `#F5F7FA`, danger `#C00000`, neutral `#6C7D7D`
- Layout IDs: LAYOUT_COVER, LAYOUT_SECTION, LAYOUT_CONTENT, LAYOUT_TWO_COLUMN, LAYOUT_PROCESS, LAYOUT_ARCHITECTURE, LAYOUT_MATRIX, LAYOUT_DASHBOARD, LAYOUT_DECISION, LAYOUT_VALIDATION, LAYOUT_CLOSING

**Rules and limits:** max words per slide, max 6 bullets per slide, max 8 words per bullet (average), table max 7 columns, use accent colour sparingly (under 20%), title case for headings, consistent footer and page number, preferred diagram types, spacing and alignment, icon style, image treatment, when to use accent colours, naming conventions, footer conventions, accessibility rules.

### 13.3 Slide-generation engine (content intelligence)

Claude generates structured slide specifications, not raw slides.

**Inputs from the Session Engine:** session scope (Stage x Module x Champion), four-point items (Confirm/Correct/Complete/Decide), evidence and findings, high-impact and risk tags, stakeholders and roles, decisions and actions, project knowledge base, session type and scope, curated four-point items, checklists and rules, design system (JSON).

**Claude reasoning and planning:** Understand -> Organise -> Prioritise -> Structure -> Select Layouts (from the approved library).

**Slide plan (structured JSON output):** layout ID, titles and subtitles, tables and data, diagrams and charts, actionable insights. Example fields per slide: `layout`, `title`, `subtitle`, `table`, `diagram`.

### 13.4 Design system validator

Checks each slide before render: layout permitted, word/bullet limits, colour and font rules, icon and image rules, brand compliance, security and sensitivity policies, terminology rules, template mapping, consistency check.

### 13.5 Render and export pipeline (controlled by the application)

1. Receive slide plan (JSON from Claude).
2. Validate against design system (check layouts, limits, rules, compliance).
3. Map to approved layouts (each slide mapped to a permitted layout / master).
4. Populate template with content (insert text, tables, charts, icons, images).
5. Render diagrams and charts (use approved styles and components).
6. Final validation and QA check (consistency, branding, accessibility, references).
7. Export PowerPoint (.pptx) generated from the master template.

**Output:** brand-consistent PowerPoint that is fully branded, has consistent layouts, is data-driven and audit-ready.

### 13.6 Approved component library

- **Diagrams:** Process Flow, Swimlane, Architecture, Timeline.
- **Tables:** Simple, Matrix, Heat Map, Decision.
- **Charts:** Bar, Stacked, Donut, S-Curve.
- **Badges and status:** Confirmed, Correct, Complete, Decide, High Impact, Risk, Parking Lot, Decision, Approved.
- **Icon set:** uniform line style.
- **Image treatment:** Photos, Diagram Style, Screenshot, Blur for Sensitive.

### 13.7 Design assets library

Colour palette, typography (Aptos family), icon set (line style), chart styles, table styles, smart diagrams (process, architecture), image styles (treatment rules).

### 13.8 Role-based slide view profiles

Each role receives a tailored view of the same underlying objects: Business Owner, Champion/SME, End User, Business Analyst, System Analyst, Architect, Project Manager, Approver. The Access Control module (Section 6) governs which objects and fields each profile can see.

### 13.9 Quality and governance controls

Brand compliance (logo, colours, fonts, layouts), content limits enforcement (words, bullets, tables, visuals), accessibility compliance (WCAG-friendly slides), data and IP protection (no sensitive data leakage), versioning and audit trail (who, when, what changed), human review required (approve before release).

### 13.10 Linkage and change control

Slides are linked to the canonical object graph, so each slide traces to its source objects (evidence, finding, requirement, design, prototype, test case, decision, baseline). No baseline is changed without a controlled change request (Change Request -> Impact Analysis -> Affected Objects -> Approvals -> New Baseline).

### 13.11 Integration with session and knowledge system

Session Selected -> Curated Items -> Evidence Citations -> Validation Results -> Decisions and Actions -> Slide Plan Generated -> PowerPoint Generated. Everything traceable back to the canonical model and session records.

**Example slide plan (SRS Session - Payment Module):** Cover Slide, Session Purpose and Scope, Documents Reviewed, Existing Confirmed Knowledge, Confirm Items, Correct Items.

### 13.12 Human review and release

Review accuracy, check completeness, approve or amend, baselined version, share or archive. Roles in review: Presenter/Author, Business Owner/Champion, BA/System Analyst, Project Manager, Approver/Signatory.

### 13.13 Design system principles

One master, many presentations. Content is dynamic, design is controlled. AI chooses the message and layout. The system controls how it looks. Consistency builds trust. Professionalism builds credibility.

---

## 14. KRISA Deliverables and Document Generation

KRISA deliverables span D01-D19. Document generation produces views of the canonical model in Word, PDF, PPTX and Excel, with custom reports, dashboards, matrix views, diagrams and visualisations. Examples referenced in the flow include D01, D02, D03 and D04 alongside other applicable KRISA documents.

**Abbreviations:** BRS (Business Requirements Specification), URS (User Requirements Specification), SRS (System Requirements Specification), SDS (Solution Design Specification), BRD (Business Requirements Document), DDD (Detailed Design Document), IFD (Interface Design), STD (Standards and Conventions), SOP (Standard Operating Procedures), TRD (Technical Requirements Document), OPS (Operations and Support Plan).

---

## 15. Functional Requirements

### 15.1 Portfolio and Project (PORT)

| ID | Requirement | Priority |
|---|---|---|
| PORT-01 | Manage many clients (tenants), each with many projects, where one project equals one system. | Must |
| PORT-02 | Model many modules per system, many stages per module and many sessions per stage. | Must |
| PORT-03 | Provide a portfolio dashboard covering deliverable status, milestones, Gantt, risks, resources, workload and cross-project analytics. | Should |
| PORT-04 | Roll up approved session outputs into a single stage baseline per stage. | Must |
| PORT-05 | Provide a per-project view: Gantt and milestones, team and roles, deliverables, risks and issues, baselines and versions. | Should |

### 15.2 Knowledge and Standards (KNOW)

| ID | Requirement | Priority |
|---|---|---|
| KNOW-01 | Hold a shared Knowledge Book of KRISA deliverables (D01-D19), question banks, matrices, coverage rules, stage gates and quality rules. | Must |
| KNOW-02 | Version the Knowledge Book and pin each project to a specific version to prevent retroactive change. | Must |
| KNOW-03 | Maintain a tenant-isolated Project Knowledge Base for tailored question sets, matrices, assumptions, reference documents and lessons learned. | Must |
| KNOW-04 | Provide an Applicability and Mapping service for stage-specific rules, thresholds, overrides and extensions. | Should |
| KNOW-05 | Provide vector retrieval, semantic search, a rules and inference engine and a prompt library. | Should |

### 15.3 Session Engine (SESS)

| ID | Requirement | Priority |
|---|---|---|
| SESS-01 | Run AI pre-analysis to ingest evidence, extract content and draft hypotheses before each session. | Must |
| SESS-02 | Require a mandatory human quality-firewall review before any pre-session pack or PPTX is released to the client, and prevent marking a pack "Ready" until approved. | Must |
| SESS-03 | Tag confidence and impact on every item and prevent quick-confirm on high-impact, low-confidence items. | Must |
| SESS-04 | Capture client responses as Confirm, Amend/Correct, Reject, Decision Required or Park, and support live AI capture. | Must |
| SESS-05 | Operate a parking lot workflow with owner, due date and resolution route for unresolved items. | Must |
| SESS-06 | Consolidate and regenerate outputs after the session and update all affected matrices. | Must |
| SESS-07 | Apply the status classification (Confirmed, Needs Confirmation, Conflict, Missing/Unknown, Decision Required, Not Applicable) to every item. | Must |
| SESS-08 | Support the question taxonomy (Confirm, Correct, Complete, Decide) per item. | Must |

### 15.4 Stage configuration (STAGE)

| ID | Requirement | Priority |
|---|---|---|
| STAGE-01 | Run all stages (BRS, URS, SRS, SDS, SLD/DBD/IFD, Specialist, Prototype, Validation, Deploy) on one engine with settings from the Knowledge Book. | Must |
| STAGE-02 | Apply stage-specific inputs, AI capabilities, matrices, high-impact examples, outputs and exit gates per Section 10. | Must |
| STAGE-03 | Auto-populate and update stage-specific matrices after each session. | Must |
| STAGE-04 | Carry handover content from each stage to the next per the defined handover sets. | Must |

### 15.5 Workflow and Gate (GATE)

| ID | Requirement | Priority |
|---|---|---|
| GATE-01 | Enforce stage-specific gates, readiness checks and exit criteria before a stage can baseline. | Must |
| GATE-02 | Require explicit approval and sign-off before creating a baseline, and version-lock the baseline. | Must |
| GATE-03 | Block baselining when too many critical items are parked and trigger a re-convene. | Should |

### 15.6 Change Management (CHG)

| ID | Requirement | Priority |
|---|---|---|
| CHG-01 | Intake change requests and perform automatic impact analysis by tracing affected objects. | Must |
| CHG-02 | Assess cost, schedule, risk and compliance impact and route the change for approval. | Should |
| CHG-03 | Create new versions, supersede prior ones and regenerate every affected output. | Must |
| CHG-04 | Prevent any baseline change without a controlled change request. | Must |

### 15.7 Document, View and Deck (DOC, DECK)

| ID | Requirement | Priority |
|---|---|---|
| DOC-01 | Generate KRISA documents, reports, dashboards, matrices and diagrams as views of the object graph. | Must |
| DOC-02 | Export to Word, PDF, PPTX and Excel using approved templates and the design system. | Must |
| DECK-01 | Generate slide plans as structured JSON (layout ID, titles, tables, diagrams, insights) from session content. | Must |
| DECK-02 | Validate every slide plan against the design system (layout, limits, brand, colour, font, icon, image, terminology, security) before render. | Must |
| DECK-03 | Render and export brand-consistent .pptx from the master template through the seven-step pipeline. | Must |
| DECK-04 | Enforce content limits, brand compliance, accessibility and no sensitive-data leakage, with versioning and audit trail. | Must |
| DECK-05 | Produce role-based slide views (Business Owner, Champion/SME, End User, BA, System Analyst, Architect, PM, Approver) governed by the Access Control module. | Should |
| DECK-06 | Link every slide to its source objects in the canonical graph for traceability. | Must |
| DECK-07 | Require human review and release before any deck is shared or archived. | Must |

### 15.8 AI Orchestration (AI)

| ID | Requirement | Priority |
|---|---|---|
| AI-01 | Route AI work through a model-neutral provider registry with a policy engine, supporting private and on-prem providers. | Must |
| AI-02 | Bind every AI output to evidence (source, section, page, date, confidence) and restrict retrieval to the user's eligible scope. | Must |
| AI-03 | Provide document understanding, reasoning, classification, embedding, diagram and prototype/code generation providers. | Should |

### 15.9 Prototype and build handover (PROTO)

| ID | Requirement | Priority |
|---|---|---|
| PROTO-01 | Compile an approved prototype build pack (requirements, data model and flows, API contracts, acceptance criteria). | Must |
| PROTO-02 | Push the approved build pack to Claude Code / code repository for generation and version control. | Must |
| PROTO-03 | Map prototype elements to requirements and acceptance criteria, and maintain prototype coverage. | Must |

---

## 16. Non-Functional and Security Requirements

### 16.1 Security and governance (SEC)

| ID | Requirement | Priority |
|---|---|---|
| SEC-01 | Enforce tenant and project isolation and prevent cross-project retrieval at all times. | Must |
| SEC-02 | Enforce role-based access control with object-level and document-level permissions through the Access Control module. | Must |
| SEC-03 | Apply evidence-level access control, data classification and redaction. | Must |
| SEC-04 | Protect against prompt injection and route only approved models and data. | Must |
| SEC-05 | Encrypt data at rest and in transit, and support data residency and key management. | Must |
| SEC-06 | Opt out of AI training on tenant data. | Must |
| SEC-07 | Keep immutable audit trails for user, AI, data and admin actions, and immutable approval records. | Must |
| SEC-08 | Support retention, legal hold and deletion policies. | Should |

SEC-02 is delivered by the Access Control module (Section 6).

### 16.2 Quality, performance and operations (NFR)

| ID | Requirement | Priority |
|---|---|---|
| NFR-01 | Be multi-tenant and scale to many clients, projects, modules and concurrent sessions. | Must |
| NFR-02 | Provide system and performance monitoring, health checks, logging and SLA monitoring. | Must |
| NFR-03 | Keep AI orchestration model-neutral so providers can be swapped without changing the workflow. | Should |
| NFR-04 | Maintain availability and recoverability appropriate to government service levels, with backup and restore. | Must |
| NFR-05 | Present generated documents and decks in a consistent, brand-aligned, accessible format. | Should |

---

## 17. Metrics, Red Flags and AI vs Human Line

### 17.1 Key metrics (refined)

Unresolved high-impact items, requirements without evidence, requirements without acceptance criteria, traceability completeness, stage baseline churn, post-approval change rate, average issue resolution time, prototype elements linked to requirements, test cases linked to acceptance criteria, rework avoided, evidence-to-baseline elapsed time.

### 17.2 Session and presentation quality metrics

Session metrics, quality metrics, traceability metrics, prototype coverage, test case linkage, issue resolution time, plus the per-stage balanced metrics in Section 10.7. Anomaly signals are advisory, not judgments.

### 17.3 Red flags (anomaly signals)

- 100% confirmation with 0% correction (possible rubber-stamping).
- Unusually low challenge on high-impact items.
- High-impact items parked without resolution.
- High baseline churn.
- Missing evidence and missing links.

### 17.4 AI vs human dividing line

**AI does (thinking and analysis):** read, analyse, extract and compare evidence; detect gaps and conflicts; draft requirements, matrices and models; prepare packs, slides and narratives; regenerate outputs after the session.

**Human does (judgment and authority):** technical validation and feasibility; verify documented truth against the real system; approve, prioritise and decide; manage risks, trade-offs and constraints; escalate unresolved issues and own decisions.

---

## 18. Roadmap, Assumptions and Open Items

### 18.1 Indicative build phasing

| Phase | Focus | Key modules |
|---|---|---|
| Phase 1 | Foundation and security | Access Control (ACL), Portfolio, Canonical Object Graph, Knowledge Book |
| Phase 2 | Sessions and validation | Session Engine, Workflow and Gate, AI Orchestration, evidence binding |
| Phase 3 | Outputs and change | Document and View Engine, Deck Generation, Change Management |
| Phase 4 | Insight and scale | Metrics and Quality Analytics, Operations, predictive insights |

Access Control is in Phase 1 by design. It is foundational, not a later add-on, because every other module enforces through it.

### 18.2 Open questions

Identity provider standard, on-prem model choice, baseline approval thresholds, retention periods per agency, which KRISA deliverables (D01-D19) are mandatory per project type.

### 18.3 Assumptions

Evidence is supplied by the client and treated as immutable; one project equals one system; one main session per stage with many sub-sessions allowed; the Knowledge Base is shared global plus project-specific.

### 18.4 Out of scope (v1)

Automated production deployment, third-party marketplace, public self-service registration.

---

## 19. Appendix A: Glossary

| Term | Meaning |
|---|---|
| BRS | Business Requirements Specification |
| URS | User Requirements Specification |
| SRS | System Requirements Specification |
| SDS | Solution Design Specification |
| SLD / DBD / IFD | Detailed design: low-level design, database design, interface design |
| BRD | Business Requirements Document |
| DDD | Detailed Design Document |
| IFD | Interface Design |
| STD | Standards and Conventions |
| SOP | Standard Operating Procedures |
| TRD | Technical Requirements Document |
| OPS | Operations and Support Plan |
| KRISA | The deliverables and knowledge framework (D01-D19) |
| RBAC | Role-Based Access Control |
| ACL | Access Control List |
| PDP | Policy Decision Point |
| PEP | Policy Enforcement Point |
| FR / NFR | Functional Requirement / Non-Functional Requirement |
| CRUD | Create, Read, Update, Delete |
| ERD | Entity Relationship Diagram |
| DFD | Data Flow Diagram |
| BPMN | Business Process Model and Notation |

---

*End of PRD. Version 2026.1. Evidence in, objects out, views generated. Documents, diagrams, prototypes and tests are generated views of the canonical model.*
