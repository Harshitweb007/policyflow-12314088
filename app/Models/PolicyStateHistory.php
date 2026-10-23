<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PolicyState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyStateHistory extends Model
{
    public $timestamps = false;

    protected $table = 'policy_state_history';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_state' => PolicyState::class,
            'to_state' => PolicyState::class,
            'created_at' => 'datetime',
        ];
    }

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}
