<?php

namespace App\Http\Requests;

use App\Rules\PlainSupportText;
use App\Support\SupportText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => SupportText::normalize($this->input('message'))]);
        }
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['payment', 'streaming', 'other'])],
            'message' => ['bail', 'required', 'string', 'max:2000', new PlainSupportText],
            'language' => ['required', Rule::in(['en', 'sw'])],
            'transaction_id' => ['nullable', 'integer', Rule::exists('transactions', 'id')->where(fn ($query) => $query->where(fn ($owner) => $owner->where('user_id', (string) $this->user()->id)->orWhere('user_email', $this->user()->email)))],
            'video_id' => ['nullable', 'integer', 'exists:videos,id'],
            'media_error_code' => ['nullable', 'integer', 'between:1,4'],
            'playback_seconds' => ['nullable', 'integer', 'between:0,864000'],
        ];
    }
}
