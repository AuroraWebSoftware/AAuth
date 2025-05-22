<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * /**
 * AuroraWebSoftware\AAuth\Models\Role
 *
 * @property int $id
 * @property array $rules_json
 * @property string $model_type
 * @property int $role_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static find($role_id) : RoleModelAbacRule
 * @method static where(string $string, string $string1, int $id)
 */
class RoleModelAbacRule extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\AuroraWebSoftware\AAuth\Models\RoleModelAbacRule>> */
    use HasFactory;

    protected $casts = [
        'rules_json' => 'array',
    ];

    protected $fillable = ['role_id', 'model_type', 'rules_json'];
}
