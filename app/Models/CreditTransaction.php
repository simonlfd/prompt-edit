<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $generation_id
 * @property string $type
 * @property int $amount_nanos
 * @property int $balance_after_nanos
 * @property string|null $description
 */
class CreditTransaction extends Model
{
    public const PURCHASE = 'purchase';

    public const HOLD = 'hold';

    public const RELEASE = 'release';

    public const CHARGE = 'charge';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_nanos' => 'integer', 'balance_after_nanos' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
