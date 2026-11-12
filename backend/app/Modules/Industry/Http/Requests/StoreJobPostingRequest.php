<?php

declare(strict_types=1);

namespace App\Modules\Industry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'career_id' => ['required', 'integer', 'exists:careers,id'],
            'title' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
