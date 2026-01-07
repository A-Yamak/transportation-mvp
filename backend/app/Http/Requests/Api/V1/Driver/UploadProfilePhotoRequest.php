<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload Profile Photo Request
 *
 * Validates profile photo uploads for drivers.
 */
class UploadProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:5120'], // 5MB max
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'A profile photo is required.',
            'photo.image' => 'The file must be an image.',
            'photo.max' => 'The photo must not exceed 5MB.',
        ];
    }
}
