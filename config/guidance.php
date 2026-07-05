<?php

/*
|--------------------------------------------------------------------------
| Per-stage contextual guidance (spec §14.2)
|--------------------------------------------------------------------------
| Shown on the stage gate and session pages so non-specialists know what each
| stage means, who should feed it, and what good looks like. Data-driven so
| wording is configurable without touching views.
*/

return [

    'BRS' => [
        'what' => 'The Business Requirements Specification captures WHAT the business needs — outcomes, rules and constraints in business language, not system features.',
        'why' => 'Everything downstream (user needs, system functions, design) must justify itself against these statements and the project objective.',
        'who' => 'Business sponsor, process owners and subject-matter experts, guided by the analyst.',
        'good' => '"Payroll must disburse salaries to all active employees by the 25th of every month" — an outcome with an owner and a measurable bound.',
        'mistakes' => 'Writing system features ("the system shall have a dashboard") instead of business outcomes; skipping the evidence that justifies each requirement.',
        'next' => 'Approved BRS content is baselined, then refined into user requirements (URS).',
    ],

    'URS' => [
        'what' => 'The User Requirements Specification describes how PEOPLE will use the solution: user groups, goals, journeys and expectations.',
        'why' => 'Business outcomes only happen through users — this stage turns business needs into user-visible capability.',
        'who' => 'Real end users and their supervisors; the analyst translates, never invents.',
        'good' => '"As a payroll officer, I need to preview the full run and correct exceptions before submitting for approval."',
        'mistakes' => 'Interviewing only managers; writing what users *should* want instead of what they said.',
        'next' => 'URS baselines feed the system requirements (SRS).',
    ],

    'SRS' => [
        'what' => 'The Software Requirements Specification defines how the SYSTEM must behave: functional and non-functional requirements with acceptance criteria.',
        'why' => 'This is the contract the design and tests are verified against.',
        'who' => 'Analyst and solution architect, validated by business reviewers.',
        'good' => '"SRS-FR: The system calculates statutory deductions per LHDN tables; batch of 5,000 completes within 30 minutes (SRS-NFR)."',
        'mistakes' => 'Untestable words ("fast", "user-friendly"); missing error paths and limits.',
        'next' => 'SRS baselines drive design (SDS) and the verification register.',
    ],

    'SDS' => [
        'what' => 'The System Design Specification records HOW the system will be built: architecture, components, interfaces, data design.',
        'why' => 'Every design element must satisfy a requirement — unjustified design is scope creep.',
        'who' => 'Solution architect and senior engineers.',
        'good' => 'A component whose register entry links SATISFIES → the SRS requirements it exists for.',
        'mistakes' => 'Designing features with no requirement trace; leaving integration contracts undocumented.',
        'next' => 'Detailed design (SLD/DBD/IFD), then prototype.',
    ],

    'SLD_DBD_IFD' => [
        'what' => 'Detailed design: system-level design, database design and interface design documents.',
        'why' => 'Builders need unambiguous structures — tables, contracts, screens — before code.',
        'who' => 'Engineers and DBAs, reviewed by the architect.',
        'good' => 'A data dictionary entry per column, an API contract per integration.',
        'mistakes' => 'Diverging from the SDS silently; undocumented nullable-vs-required decisions.',
        'next' => 'Prototype construction against approved designs.',
    ],

    'SPECIALIST' => [
        'what' => 'Optional stage for specialist reviews: security, compliance, performance, integration partners.',
        'why' => 'Some projects need domain sign-offs that do not fit the standard chain.',
        'who' => 'The specialists in question — security officers, regulators, vendors.',
        'good' => 'A recorded decision per specialist concern, traced to the requirements it affects.',
        'mistakes' => 'Treating it as a rubber stamp; skipping it when regulation applies.',
        'next' => 'Prototype, carrying any specialist constraints.',
    ],

    'PROTOTYPE' => [
        'what' => 'Prototype elements let stakeholders EXPERIENCE the solution before it is fully built — each element implements specific requirements.',
        'why' => 'Cheapest place to discover misunderstanding is a prototype, not production.',
        'who' => 'UX/engineers build; real users review and comment.',
        'good' => 'A screen whose register entry lists the requirements it IMPLEMENTS and its review state.',
        'mistakes' => 'Attractive screens with no requirement basis; skipping user review sessions.',
        'next' => 'Validation — test cases verify requirements against the built behaviour.',
    ],

    'VALIDATION' => [
        'what' => 'Verification & validation: test cases VERIFY requirements, results and defects close the loop.',
        'why' => 'The RTM is only credible when every requirement has verification evidence.',
        'who' => 'QA reviewers author and execute; business users accept.',
        'good' => 'Every requirement row in the RTM reads "traced": evidence upstream, design and passing tests downstream.',
        'mistakes' => 'Testing only the happy path; leaving failed tests without defects.',
        'next' => 'Deployment readiness.',
    ],

    'DEPLOY' => [
        'what' => 'Deployment readiness: cutover plan, training, data migration and go-live acceptance.',
        'why' => 'A verified system still fails if the rollout is unplanned.',
        'who' => 'PM, operations, client stakeholders.',
        'good' => 'A baselined checklist with owners and dates, risks recorded.',
        'mistakes' => 'No rollback plan; training treated as an email.',
        'next' => 'Project closure and lessons learned.',
    ],
];
