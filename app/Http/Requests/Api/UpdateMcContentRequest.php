<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMcContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateMcContent', $this->route('conferenceSession')) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'mc_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'mc_script' => ['sometimes', 'nullable', 'string', 'max:50000'],
        ];
    }
}
