<?php

namespace AuroraWebSoftware\AAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public static array $rules = [
        'name' => [
            'required',
            'min:3',
        ],
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = self::$rules;
        $rules['name'][] = 'unique:roles,name,' . $this->route('role');

        return $rules;
    }
}
