<?php

namespace AuroraWebSoftware\AAuth\Tests\Models;

use AuroraWebSoftware\AAuth\Interfaces\AAuthOrganizationNodeInterface;
use AuroraWebSoftware\AAuth\Traits\AAuthOrganizationNode;
use Illuminate\Database\Eloquent\Model;

class OrganizationNodeable extends Model implements AAuthOrganizationNodeInterface
{
    use AAuthOrganizationNode;

    protected $fillable = ['name'];

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
}
