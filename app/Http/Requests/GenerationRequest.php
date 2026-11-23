<?php

namespace App\Http\Requests;

use App\Pixelforge\PricingRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mirrors Pixelforge's own limits so users get instant, specific errors instead of a failed generation. */
class GenerationRequest extends FormRequest
{
    public const MAX_PROMPT_LENGTH = 4000;

    public const MAX_REFERENCE_IMAGES = 4;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $pricing = app(PricingRepository::class)->current();

        return [
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::in($pricing->sizes())],
            'quality' => ['required', Rule::in($pricing->qualities())],
            'reference_images' => ['array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add at most '.self::MAX_REFERENCE_IMAGES.' reference images.',
            'reference_images.*.url' => 'Each reference image must be an http(s) URL.',
            'reference_images.*.required' => 'Remove empty reference image rows or fill them in.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prompt' => trim((string) $this->input('prompt')),
            'reference_images' => array_values(array_filter(
                array_map(fn ($url) => trim((string) $url), (array) $this->input('reference_images', [])),
                fn (string $url) => $url !== '',
            )),
        ]);
    }

    /** @return list<string> */
    public function referenceImages(): array
    {
        return $this->validated('reference_images') ?? [];
    }
}
