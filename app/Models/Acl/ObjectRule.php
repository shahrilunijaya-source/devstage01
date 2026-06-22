<?php

declare(strict_types=1);

namespace App\Models\Acl;

use Illuminate\Database\Eloquent\Model;

/**
 * Attribute-based object rule (PRD §6.3): refines access by type, status and
 * classification.
 */
class ObjectRule extends Model
{
    protected $table = 'acl_object_rules';

    protected $fillable = [
        'tenant_id', 'object_type', 'action', 'match_classification',
        'match_status', 'effect', 'required_role_key', 'priority',
    ];

    protected $casts = ['priority' => 'integer'];
}
