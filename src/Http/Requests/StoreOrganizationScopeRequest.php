<?php

namespace AuroraWebSoftware\AAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationScopeRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    public static array $rules = [
        'name' => ['required', 'min:3'],
        'level' => [],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function getRules(): array
    {
        return self::$rules;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::$rules;
    }
}
