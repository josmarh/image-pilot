<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImagePresetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            'width' => [
                'nullable',
                'integer',
                'min:1',
                'max:12000',
            ],

            'height' => [
                'nullable',
                'integer',
                'min:1',
                'max:12000',
            ],

            'resize_mode' => [
                'sometimes',
                'required',
                Rule::in(['contain', 'cover']),
            ],

            'output_format' => [
                'sometimes',
                'required',
                Rule::in(['jpg', 'jpeg', 'png', 'webp']),
            ],

            'quality' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:100',
            ],

            'remove_metadata' => [
                'sometimes',
                'boolean',
            ],

            'watermark_enabled' => [
                'sometimes',
                'boolean',
            ],

            'watermark_type' => [
                'nullable',
                Rule::in(['text', 'image']),
            ],

            'watermark_settings' => [
                'nullable',
                'array',
            ],
        ];
    }
}
