<?php

declare(strict_types=1);

namespace App\Models\Graph;

use App\Enums\ObjectType;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-(project, type) sequence backing permanent-ID minting (PRD §12.6).
 */
class ObjectIdSequence extends Model
{
    protected $fillable = ['project_id', 'type', 'next_seq'];

    protected $casts = [
        'type' => ObjectType::class,
        'next_seq' => 'integer',
    ];
}
