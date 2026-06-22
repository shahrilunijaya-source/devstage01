<?php

return [
    // Heaviness factor weights — must sum to 1.0
    'weights' => [
        'behind' => 0.30, // schedule slip: planned% − actual%
        'deadline' => 0.25, // time pressure: how close to planned_end
        'value' => 0.20, // contract size vs portfolio
        'issues' => 0.15, // open (unresolved) issues — firefighting load
        'scope' => 0.10, // leaf-task count — how much there is to track
    ],

    // Saturation caps: at/above this raw count the factor scores 100.
    'issue_cap' => 8,  // 8+ open issues => max issue pressure
    'scope_cap' => 40, // 40+ leaf tasks  => max scope pressure

    // Role share of a project's heaviness
    'pm_share' => 0.65,
    'pe_share' => 0.35, // split evenly across all active PEs on the project

    // Project statuses that are NOT counted toward live workload
    'inactive_statuses' => ['completed', 'cancelled', 'on_hold'],

    // Person Load band lower bounds (ascending). Load >= threshold => that band.
    'bands' => [
        'light' => 0,
        'moderate' => 40,
        'heavy' => 80,
        'overloaded' => 130,
    ],
];
