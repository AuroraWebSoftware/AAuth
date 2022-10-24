<?php

namespace AuroraWebSoftware\AAuth\Interfaces;

interface AAuthOrganizationNodeInterface
{
    public static function bootAAuthOrganizationNode(): void;

    public static function getModelType(): string;

    public function getModelId(): int;

    public function getModelName(): ?string;
}
