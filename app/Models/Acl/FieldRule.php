<?php

declare(strict_types=1);

namespace App\Models\Acl;

use Illuminate\Database\Eloquent\Model;

/**
 * Field-level rule, including redaction for unauthorised viewers (PRD §6.3).
 */
class FieldRule extends Model
{
    protected $table = 'acl_field_rules';

    protected $fillable = [
        'tenant_id', 'object_type', 'field', 'classification',
        'effect', 'required_role_key', 'priority',
    ];

    protected $casts = ['priority' => 'integer'];
}
