<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('sessionNote')) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:10000']];
    }
}
