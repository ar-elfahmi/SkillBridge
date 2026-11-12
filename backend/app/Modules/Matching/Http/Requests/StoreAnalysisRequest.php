<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalysisRequest extends FormRequest
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
            'rps_document_id' => ['required', 'integer', 'exists:rps_documents,id'],
            'career_id' => ['required', 'integer', 'exists:careers,id'],
            'job_posting_ids' => ['required', 'array', 'min:1'],
            'job_posting_ids.*' => ['required', 'integer', 'distinct', 'exists:job_postings,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rps_document_id.required' => 'Dokumen RPS wajib dipilih.',
            'rps_document_id.exists' => 'Dokumen RPS tidak ditemukan.',
            'career_id.required' => 'Profesi sasaran wajib dipilih.',
            'career_id.exists' => 'Profesi tidak ditemukan.',
            'job_posting_ids.required' => 'Minimal satu lowongan pekerjaan harus dipilih.',
            'job_posting_ids.min' => 'Minimal satu lowongan pekerjaan harus dipilih.',
            'job_posting_ids.*.exists' => 'Satu atau lebih lowongan pekerjaan tidak ditemukan.',
        ];
    }
}
