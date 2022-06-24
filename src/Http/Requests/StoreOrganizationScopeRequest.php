<?php

namespace AuroraWebSoftware\AAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationScopeRequest extends FormRequest
{
    public static array $rules = [
        'name' => ['required', 'min:5'],
        'level' => [],
    ];

    /**
     * @return array
     */
    public static function getRules(): array
    {
        return self::$rules;
    }
}
