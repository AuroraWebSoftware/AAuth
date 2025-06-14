<?php

namespace AuroraWebSoftware\AAuth\Models;

use AuroraWebSoftware\AAuth\Contracts\AAuthUserContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|Role[] $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Role[] $organizational_roles
 * @property-read int|null $organizational_roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Role[] $systemRoles
 * @property-read int|null $system_roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Role[] $system_roles
 * @property-read \Illuminate\Database\Eloquent\Collection|Role[] $organization_roles
 * @property-read int|null $organization_roles_count
 * @property-read int $assigned_user_count
 * @property-read bool $deletable
 */
class User extends Authenticatable implements AAuthUserContract
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\AuroraWebSoftware\AAuth\Models\User>> */
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\AuroraWebSoftware\AAuth\Models\Role, \AuroraWebSoftware\AAuth\Models\User, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_organization_node');
    }

    /**
     * @return Collection<int, Role>
     */
    public function rolesWithOrganizationNodes(): Collection
    {
        // @phpstan-ignore-next-line
        $rolesCollection = collect();

        $rolesWithOrganizationNodes = DB::table('user_role_organization_node')->where('user_id', '=', $this->id)->get();

        foreach ($rolesWithOrganizationNodes as $rolesWithOrganizationNode) {
            $role = Role::find($rolesWithOrganizationNode->role_id);
            /**
             * @var Role $role
             * @phpstan-ignore-next-line
             */
            $role->organizationNode = OrganizationNode::find($rolesWithOrganizationNode->organization_node_id);

            $rolesCollection->push($role);
        }

        return $rolesCollection;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\AuroraWebSoftware\AAuth\Models\Role, \AuroraWebSoftware\AAuth\Models\User, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function system_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_organization_node')
            ->where('type', 'system');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\AuroraWebSoftware\AAuth\Models\Role, \AuroraWebSoftware\AAuth\Models\User, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function organization_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_organization_node')
            ->where('type', 'organization');
    }

    /**
     * @return int
     */
    public function getAssignedUserCountAttribute(): int
    {
        return DB::table('user_role_organization_node')
            ->where('user_id', $this->id)->count();
    }

    /**
     * @return bool
     */
    public function getDeletableAttribute(): bool
    {
        // todo new syntax
        return $this->getAssignedUserCountAttribute() == 0;
    }
}
