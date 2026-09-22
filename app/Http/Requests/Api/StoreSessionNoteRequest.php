<?php

namespace App\Http\Requests\Api;

use App\Models\SessionNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SessionNote::class) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:10000']];
    }
}
