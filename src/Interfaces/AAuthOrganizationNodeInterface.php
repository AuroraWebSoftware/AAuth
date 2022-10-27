<?php

namespace AuroraWebSoftware\AAuth\Interfaces;

interface AAuthOrganizationNodeInterface
{
    public static function bootAAuthOrganizationNode(): void;

    public static function getModelType(): string;

    public static function getModelName(): ?string;

    public function getModelId(): int;

}
