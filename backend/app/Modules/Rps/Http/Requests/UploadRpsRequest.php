<?php

declare(strict_types=1);

namespace App\Modules\Rps\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class UploadRpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // mimetypes inspects file content (finfo), extensions checks the name.
            'file' => [
                'required',
                'file',
                'mimetypes:application/pdf',
                'extensions:pdf',
                'max:'.($this->maxMb() * 1024),
                // Magic bytes: guards against content that finfo misdetects.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $path = $value instanceof UploadedFile ? $value->getRealPath() : false;
                    $head = $path === false ? false : file_get_contents($path, false, null, 0, 5);

                    if ($head !== '%PDF-') {
                        $fail('Berkas harus berupa PDF.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Berkas RPS wajib diunggah.',
            'file.file' => 'Unggahan berkas gagal. Coba lagi.',
            'file.uploaded' => 'Unggahan berkas gagal. Coba lagi.',
            'file.mimetypes' => 'Berkas harus berupa PDF.',
            'file.extensions' => 'Berkas harus berekstensi .pdf.',
            'file.max' => $this->tooLargeMessage(),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Oversized files get 413 (docs/API.md §6), other failures stay 422.
        if (array_key_exists('Max', $validator->failed()['file'] ?? [])) {
            throw new HttpResponseException(response()->json([
                'message' => $this->tooLargeMessage(),
                'errors' => ['file' => [$this->tooLargeMessage()]],
            ], 413));
        }

        parent::failedValidation($validator);
    }

    private function maxMb(): int
    {
        return (int) config('skillbridge.upload.max_pdf_mb');
    }

    private function tooLargeMessage(): string
    {
        return "Ukuran PDF melebihi batas {$this->maxMb()} MB.";
    }
}
