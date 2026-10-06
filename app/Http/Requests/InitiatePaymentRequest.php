<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:100'],
            'phone_number' => ['required', 'string'],
            'video_id' => ['nullable', 'integer'],
            'gateway' => ['nullable', 'string', 'in:sonicpesa,mobilipa'],
        ];
    }
}
