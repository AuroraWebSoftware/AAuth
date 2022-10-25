<?php

namespace AuroraWebSoftware\AAuth\Tests\Models;

use AuroraWebSoftware\AAuth\Enums\ABACCondition;
use AuroraWebSoftware\AAuth\Interfaces\AAuthABACModelInterface;
use AuroraWebSoftware\AAuth\Interfaces\AAuthOrganizationNodeInterface;
use AuroraWebSoftware\AAuth\Traits\AAuthABACModel;
use AuroraWebSoftware\AAuth\Traits\AAuthOrganizationNode;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $name
 */
class OrganizationNodeable extends Model implements AAuthOrganizationNodeInterface, AAuthABACModelInterface
{
    use AAuthOrganizationNode;
    use AAuthABACModel;

    protected $fillable = ['name', 'age'];

    public static function getModelType(): string
    {
        return 'AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable';
    }

    public function getModelId(): int
    {
        return $this->id;
    }

    public function getModelName(): ?string
    {
        return $this->name;
    }

    public static function getABACRules(): array
    {
        return [
            'name' => [ABACCondition::equal, ABACCondition::like],
            'age' => [ABACCondition::equal, ABACCondition::greater_then],
            'id' => [ABACCondition::equal, ABACCondition::greater_than_or_equal_to],
        ];
    }
}
