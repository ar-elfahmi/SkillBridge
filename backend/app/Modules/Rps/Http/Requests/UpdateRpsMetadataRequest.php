<?php

declare(strict_types=1);

namespace App\Modules\Rps\Http\Requests;

use App\Models\RpsDocument;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRpsMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RpsDocument|null $document */
        $document = $this->route('document');

        if ($document === null) {
            return true;
        }

        if (! $document->isOwnedBy($this->user())) {
            abort(404);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'credits' => ['nullable', 'integer', 'between:0,32767'],
            'semester' => ['nullable', 'integer', 'between:0,32767'],
            'study_program' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.max' => 'Kode mata kuliah maksimal 255 karakter.',
            'name.max' => 'Nama mata kuliah maksimal 255 karakter.',
            'credits.integer' => 'SKS harus berupa bilangan bulat.',
            'credits.between' => 'SKS harus antara 0 dan 32767.',
            'semester.integer' => 'Semester harus berupa bilangan bulat.',
            'semester.between' => 'Semester harus antara 0 dan 32767.',
            'study_program.max' => 'Program studi maksimal 255 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var RpsDocument|null $document */
            $document = $this->route('document');

            if ($document !== null) {
                if ($document->parse_status !== ParseStatus::Parsed) {
                    $v->errors()->add('parse_status', 'Metadata hanya dapat diubah setelah dokumen selesai diproses.');
                }

                if ($document->isMetadataConfirmed()) {
                    $v->errors()->add('metadata', 'Metadata yang sudah dikonfirmasi tidak dapat diubah.');
                }
            }

            $allowed = ['code', 'name', 'credits', 'semester', 'study_program'];
            $payloadKeys = array_keys($this->all());

            $unknown = array_diff($payloadKeys, $allowed);
            if (! empty($unknown)) {
                foreach ($unknown as $field) {
                    $v->errors()->add($field, "Field '{$field}' tidak diizinkan.");
                }
            }

            $present = array_intersect($payloadKeys, $allowed);
            if (empty($present)) {
                $v->errors()->add('payload', 'Kirim minimal satu field metadata yang akan diubah.');
            }
        });
    }
}
