<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuroraWebSoftware\AAuth\Models\RolePermission
 *
 * @property-read int $id
 * @property int $role_id
 * @property string $permission
 * @property array|null $parameters
 *
 * @method static RolePermission create(array $attributes)
 */
class RolePermission extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role_permission';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['role_id', 'permission', 'parameters'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameters' => 'array',
    ];

    /**
     * Get the role that owns this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\AuroraWebSoftware\AAuth\Models\Role, \AuroraWebSoftware\AAuth\Models\RolePermission>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
