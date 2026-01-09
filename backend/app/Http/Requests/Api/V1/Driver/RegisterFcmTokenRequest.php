<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;

class RegisterFcmTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fcm_token.required' => 'FCM token is required',
            'fcm_token.string' => 'FCM token must be a valid string',
            'fcm_token.max' => 'FCM token is too long',
        ];
    }
}
