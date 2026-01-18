<?php

namespace AuroraWebSoftware\AAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationScopeRequest extends FormRequest
{
    public static array $rules = [
        'name' => ['required', 'min:3'],
        'level' => [],
    ];

    /**
     * @return array
     */
    public static function getRules(): array
    {
        return self::$rules;
    }

    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return self::$rules;
    }
}
