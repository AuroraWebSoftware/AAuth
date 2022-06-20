<?php

namespace Aurora\AAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationNodeRequest extends FormRequest
{
    // todo
    public static array $rules = [
        'name' => ['required', 'min:5'],
        'parent_id' => ['required', 'int'],
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
        return self::$rules;
    }
}
