<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property bool $is_company_admin
 * @property string|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CompanyUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'company_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_company_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
