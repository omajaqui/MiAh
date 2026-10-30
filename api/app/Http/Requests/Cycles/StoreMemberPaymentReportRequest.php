<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreMemberPaymentReportRequest extends FormRequest
{
    public const MAX_RECEIPT_KILOBYTES = 12288;

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isSocio() ?? false;
    }

    public function rules(): array
    {
        return [
            'contribution_ids' => ['required', 'array', 'min:1'],
            'contribution_ids.*' => ['integer', 'distinct', 'exists:savings_contributions,id'],
            'receipt' => [
                'required',
                'file',
                'mimetypes:'.implode(',', self::ALLOWED_MIMES),
                'max:'.self::MAX_RECEIPT_KILOBYTES,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'contribution_ids.required' => 'Seleccione al menos una cuota.',
            'contribution_ids.min' => 'Seleccione al menos una cuota.',
            'receipt.required' => 'Adjunte una captura de pantalla como soporte.',
            'receipt.file' => 'El soporte debe ser un archivo de imagen.',
            'receipt.mimetypes' => 'Formato incorrecto. Solo se permiten imágenes JPG, PNG, WEBP o GIF.',
            'receipt.max' => 'La imagen supera el tamaño máximo de 12 MB.',
            'receipt.uploaded' => $this->uploadedErrorMessage(),
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $receipt = $this->file('receipt');

            if (! $receipt instanceof UploadedFile) {
                return;
            }

            if (! $receipt->isValid()) {
                $validator->errors()->forget('receipt');
                $validator->errors()->add('receipt', $this->phpUploadError($receipt->getError()));
            }
        });
    }

    private function uploadedErrorMessage(): string
    {
        $receipt = $this->file('receipt');

        if ($receipt instanceof UploadedFile && ! $receipt->isValid()) {
            return $this->phpUploadError($receipt->getError());
        }

        return 'No se pudo cargar la imagen. Use JPG, PNG, WEBP o GIF de máximo 12 MB.';
    }

    private function phpUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido (12 MB).',
            UPLOAD_ERR_PARTIAL => 'La imagen se subió incompleta. Intente de nuevo.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ninguna imagen.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para guardar la imagen.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar la imagen.',
            UPLOAD_ERR_EXTENSION => 'El servidor bloqueó la carga de la imagen.',
            default => 'No se pudo cargar la imagen. Use JPG, PNG, WEBP o GIF de máximo 12 MB.',
        };
    }
}
