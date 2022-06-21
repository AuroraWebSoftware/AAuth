<?php

namespace Aurora\AAuth\Tests\Models;

use Aurora\AAuth\Interfaces\AAuthOrganizationNodeInterface;
use Aurora\AAuth\Traits\AAuthOrganizationNode;
use Illuminate\Database\Eloquent\Model;

class OrganizationNodeable extends Model implements AAuthOrganizationNodeInterface
{
    use AAuthOrganizationNode;

    protected $fillable = ['name'];

    public static function getModelType(): string
    {
        return 'Aurora\AAuth\Tests\Models\OrganizationNodeable';
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
