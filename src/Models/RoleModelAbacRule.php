<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, string $string1, int $id)
 */
class RoleModelAbacRule extends Model
{
    use HasFactory;
    protected $casts = [
        'rules_json' => 'array',
    ];
    protected $fillable = ['role_id', 'model_type', 'rules_json'];
}
