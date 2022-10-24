<?php

namespace AuroraWebSoftware\AAuth\Interfaces;

interface AAuthABACModelInterface
{
    public static function getModelType(): string;

    public static function getABACRules(): array;
}
