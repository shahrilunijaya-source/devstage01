<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\LifecycleStage;
use App\Models\Knowledge\KnowledgeBook;

/**
 * Seeds the shared KRISA Knowledge Book v2026.1 (PRD §7.1): deliverables
 * D01-D19, skeleton question banks, stage gates, glossary. Idempotent.
 */
class KnowledgeSeeder
{
    private const VERSION = 'v2026.1';

    public function seed(): KnowledgeBook
    {
        $book = KnowledgeBook::firstOrCreate(
            ['kind' => 'krisa', 'version' => self::VERSION],
            ['title' => 'KRISA Knowledge Book', 'status' => 'published', 'published_at' => now()],
        );

        $this->seedDeliverables($book);
        $this->seedQuestionBanks($book);
        $this->seedStageGates($book);
        $this->seedGlossary($book);

        return $book;
    }

    private function seedDeliverables(KnowledgeBook $book): void
    {
        $titles = [
            'Business Requirements Specification', 'User Requirements Specification',
            'System Requirements Specification', 'Solution Design Specification',
            'Detailed Design Document', 'Interface Design', 'Database Design',
            'Standards and Conventions', 'Standard Operating Procedures',
            'Technical Requirements Document', 'Operations and Support Plan',
            'Traceability Matrix', 'Acceptance Criteria Catalogue', 'Test Plan',
            'Prototype Validation Report', 'Change Request Register',
            'Risk and Issue Register', 'Baseline Pack', 'Handover Document',
        ];

        foreach ($titles as $i => $title) {
            $code = sprintf('D%02d', $i + 1);
            $book->items()->firstOrCreate(
                ['code' => $code],
                ['item_type' => 'deliverable', 'title' => $title, 'sort_order' => $i + 1],
            );
        }
    }

    private function seedQuestionBanks(KnowledgeBook $book): void
    {
        $banks = [
            [LifecycleStage::BRS, [
                'What business problem is this system solving?',
                'Who are the primary business stakeholders and owners?',
                'What are the measurable business objectives?',
            ]],
            [LifecycleStage::URS, [
                'Which user roles interact with the system?',
                'What are the key user journeys and tasks?',
                'What approval and notification rules apply to users?',
            ]],
            [LifecycleStage::SRS, [
                'What functional capabilities must the system provide?',
                'What are the non-functional thresholds (performance, security)?',
                'What integrations and data interfaces are required?',
            ]],
        ];

        foreach ($banks as [$stage, $questions]) {
            foreach ($questions as $i => $question) {
                $code = sprintf('%s-Q-%03d', $stage->value, $i + 1);
                $book->items()->firstOrCreate(
                    ['code' => $code],
                    [
                        'item_type' => 'question',
                        'stage' => $stage->value,
                        'title' => $question,
                        'sort_order' => $i + 1,
                    ],
                );
            }
        }
    }

    private function seedStageGates(KnowledgeBook $book): void
    {
        foreach ([LifecycleStage::BRS, LifecycleStage::URS, LifecycleStage::SRS, LifecycleStage::SDS] as $stage) {
            $code = sprintf('GATE-%s-01', $stage->value);
            $book->items()->firstOrCreate(
                ['code' => $code],
                [
                    'item_type' => 'stage_gate',
                    'stage' => $stage->value,
                    'title' => sprintf('%s exit gate: baseline readiness', $stage->label()),
                    'payload' => ['criteria' => ['all_items_resolved', 'traceability_complete', 'approved']],
                ],
            );
        }
    }

    private function seedGlossary(KnowledgeBook $book): void
    {
        $terms = [
            'KRISA' => 'The deliverables and knowledge framework (D01-D19).',
            'Baseline' => 'An immutable, approved snapshot of a stage\'s objects.',
            'Quality Firewall' => 'Mandatory human review before client presentation.',
        ];

        foreach (array_values(array_keys($terms)) as $i => $term) {
            $book->items()->firstOrCreate(
                ['code' => 'GLOSS-'.strtoupper(preg_replace('/[^a-z0-9]/i', '', $term))],
                ['item_type' => 'glossary_term', 'title' => $term, 'body' => $terms[$term], 'sort_order' => $i + 1],
            );
        }
    }
}
