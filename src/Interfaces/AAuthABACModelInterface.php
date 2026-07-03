<?php

namespace AuroraWebSoftware\AAuth\Interfaces;

interface AAuthABACModelInterface
{
    public static function getModelType(): string;

    /**
     * @return array<string, mixed>
     */
    public static function getABACRules(): array;
}
