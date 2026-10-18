<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRequestPayment extends Model
{
    public const OPERATORS = ['orange_money', 'mtn_momo', 'moov_money', 'wave', 'other'];

    protected $fillable = [
        'access_request_id', 'amount', 'currency', 'operator', 'reference',
        'paid_at', 'payer_phone', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'integer'];
    }

    public function accessRequest(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
