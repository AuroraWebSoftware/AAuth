<?php

namespace AuroraWebSoftware\AAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationScopeRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    public static array $rules = [
        'name' => ['required', 'min:3'],
        'level' => [],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::$rules;
    }
}
