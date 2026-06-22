<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The 26 canonical object types (PRD §12.1). Each maps to a permanent-ID
 * prefix (PRD §12.3 trace example, e.g. EVD-0027, SRS-FR-0102, SDS-COMP-0012).
 */
enum ObjectType: string
{
    case EVIDENCE = 'evidence';
    case FINDING = 'finding';
    case USER_NEED = 'user_need';
    case BUSINESS_REQUIREMENT = 'business_requirement';
    case USER_REQUIREMENT = 'user_requirement';
    case FUNCTIONAL_REQUIREMENT = 'functional_requirement';
    case NON_FUNCTIONAL_REQUIREMENT = 'non_functional_requirement';
    case BUSINESS_RULE = 'business_rule';
    case ASSUMPTION = 'assumption';
    case CONFLICT = 'conflict';
    case DECISION = 'decision';
    case RISK = 'risk';
    case ACCEPTANCE_CRITERION = 'acceptance_criterion';
    case DESIGN_DECISION = 'design_decision';
    case DESIGN_COMPONENT = 'design_component';
    case INTERFACE = 'interface';
    case DATABASE_OBJECT = 'database_object';
    case PROTOTYPE_ELEMENT = 'prototype_element';
    case TEST_CASE = 'test_case';
    case TEST_RESULT = 'test_result';
    case DEFECT = 'defect';
    case ISSUE = 'issue';
    case APPROVAL = 'approval';
    case BASELINE = 'baseline';
    case CHANGE_REQUEST = 'change_request';
    case TRACE_RELATIONSHIP = 'trace_relationship';

    /** Permanent-ID prefix for this type (PRD §12.3). */
    public function idPrefix(): string
    {
        return match ($this) {
            self::EVIDENCE => 'EVD',
            self::FINDING => 'FIND',
            self::USER_NEED => 'UN',
            self::BUSINESS_REQUIREMENT => 'BRS-REQ',
            self::USER_REQUIREMENT => 'URS-REQ',
            self::FUNCTIONAL_REQUIREMENT => 'SRS-FR',
            self::NON_FUNCTIONAL_REQUIREMENT => 'SRS-NFR',
            self::BUSINESS_RULE => 'BR',
            self::ASSUMPTION => 'ASM',
            self::CONFLICT => 'CONF',
            self::DECISION => 'DEC',
            self::RISK => 'RISK',
            self::ACCEPTANCE_CRITERION => 'AC',
            self::DESIGN_DECISION => 'DD',
            self::DESIGN_COMPONENT => 'SDS-COMP',
            self::INTERFACE => 'API',
            self::DATABASE_OBJECT => 'DBO',
            self::PROTOTYPE_ELEMENT => 'PROTO',
            self::TEST_CASE => 'UAT-TC',
            self::TEST_RESULT => 'TR',
            self::DEFECT => 'DEF',
            self::ISSUE => 'ISS',
            self::APPROVAL => 'APR',
            self::BASELINE => 'BL',
            self::CHANGE_REQUEST => 'CR',
            self::TRACE_RELATIONSHIP => 'TRC',
        };
    }

    /** Human-readable singular label, e.g. "Functional Requirement". */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** Plural label for section headings, e.g. "Functional Requirements". */
    public function pluralLabel(): string
    {
        $label = $this->label();

        return str_ends_with($label, 's') ? $label : $label.'s';
    }
}
