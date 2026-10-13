<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImageBatchRequest extends FormRequest
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
            'images' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'images.*' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp',
            ],

            'settings' => [
                'nullable',
                'array',
            ],

            'settings.width' => [
                'nullable',
                'integer',
                'min:1',
                'max:12000',
            ],

            'settings.height' => [
                'nullable',
                'integer',
                'min:1',
                'max:12000',
            ],

            'settings.resize_mode' => [
                'nullable',
                Rule::in([
                    'contain',
                    'cover',
                ]),
            ],

            'settings.output_format' => [
                'nullable',
                Rule::in([
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                ]),
            ],

            'settings.quality' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],

            'settings.prevent_upscaling' => [
                'nullable',
                'boolean',
            ],
            'settings.watermark_enabled' => ['sometimes', 'boolean'],
            'settings.watermark_text' => ['nullable', 'string', 'max:200'],
            'settings.watermark_position' => [
                'sometimes',
                'in:top-left,top-center,top-right,center,bottom-left,bottom-center,bottom-right',
            ],
            'settings.watermark_opacity' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'settings.watermark_size' => ['sometimes', 'integer', 'min:8', 'max:200'],
            'settings.watermark_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.watermark_margin' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * Return only settings that are actually supported by
     * the image processing pipeline.
     */
    public function processingSettings(): array
    {
        $settings = $this->input('settings', []);

        return array_filter([
            'width' => isset($settings['width'])
                ? (int) $settings['width']
                : null,

            'height' => isset($settings['height'])
                ? (int) $settings['height']
                : null,

            'resize_mode' => $settings['resize_mode'] ?? null,

            'output_format' => $settings['output_format'] ?? null,

            'quality' => isset($settings['quality'])
                ? (int) $settings['quality']
                : null,

            'prevent_upscaling' => isset($settings['prevent_upscaling'])
                ? filter_var(
                    $settings['prevent_upscaling'],
                    FILTER_VALIDATE_BOOLEAN
                )
                : null,
            'watermark_enabled' =>  isset($settings['watermark_enabled'])
                ? filter_var(
                    $settings['watermark_enabled'],
                    FILTER_VALIDATE_BOOLEAN
                )
                : null,
            'watermark_text' => $settings['watermark_text'] ?? null,
            'watermark_position' => $settings['watermark_position'] ?? null,
            'watermark_opacity' => isset($settings['watermark_opacity'])
                ? (int) $settings['watermark_opacity']
                : null,
            'watermark_size' => isset($settings['watermark_size']) 
                ? (int) $settings['watermark_size']
                : null,
            'watermark_color' => $settings['watermark_color'] ?? null,
            'watermark_margin' => isset($settings['watermark_margin'])
                ? (int) $settings['watermark_margin']
                : null
        ], static fn ($value) => $value !== null);
    }
}
