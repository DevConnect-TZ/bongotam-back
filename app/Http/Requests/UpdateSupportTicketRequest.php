<?php

namespace App\Http\Requests;

use App\Rules\PlainSupportText;
use App\Support\SupportText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reply'))) {
            $this->merge(['reply' => SupportText::normalize($this->input('reply'))]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved'])],
            'reply' => ['bail', 'nullable', 'string', 'max:2000', new PlainSupportText],
        ];
    }
}
