<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreFloorPlanRequest extends FormRequest
{
    private const TWO_DIMENSIONAL_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dxf'];

    private const THREE_DIMENSIONAL_EXTENSIONS = ['glb', 'gltf'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('view_mode')) {
            $this->merge(['view_mode' => '2d']);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'location_id' => ['required', TenantRule::exists('locations')],
            'name' => ['required', 'string', 'max:255'],
            'view_mode' => ['required', 'in:2d,3d'],
            'plan' => ['required', 'file', 'max:102400'],
            'preview' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'width_meters' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'height_meters' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'depth_meters' => ['nullable', 'required_if:view_mode,3d', 'numeric', 'gt:0', 'max:100000'],
            'model_scale' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'model_rotation_y' => ['nullable', 'numeric', 'between:-360,360'],
            'model_offset_x' => ['nullable', 'numeric', 'between:-100000,100000'],
            'model_offset_y' => ['nullable', 'numeric', 'between:-100000,100000'],
            'model_offset_z' => ['nullable', 'numeric', 'between:-100000,100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('plan');
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $mode = $this->string('view_mode')->toString();
            $allowed = $mode === '3d' ? self::THREE_DIMENSIONAL_EXTENSIONS : self::TWO_DIMENSIONAL_EXTENSIONS;

            if (! in_array($extension, $allowed, true)) {
                $validator->errors()->add(
                    'plan',
                    $mode === '3d'
                        ? 'El modelo 3D debe ser GLB o glTF autocontenido.'
                        : 'El plano 2D debe ser PNG, JPG, WEBP, PDF o DXF.',
                );
            }

            if ($mode === '2d' && $file->getSize() > 20 * 1024 * 1024) {
                $validator->errors()->add('plan', 'El plano 2D no puede superar 20 MB.');
            }

            if ($mode === '3d' && $extension === 'gltf') {
                if ($file->getSize() > 20 * 1024 * 1024) {
                    $validator->errors()->add('plan', 'Un glTF JSON no puede superar 20 MB. Para modelos mayores usa GLB.');

                    return;
                }
                $this->validateSelfContainedGltf($validator, $file);
            }
        });
    }

    private function validateSelfContainedGltf(Validator $validator, UploadedFile $file): void
    {
        $document = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (! is_array($document) || ! isset($document['asset']['version'])) {
            $validator->errors()->add('plan', 'El archivo glTF no contiene un documento válido.');

            return;
        }

        foreach (array_merge($document['buffers'] ?? [], $document['images'] ?? []) as $resource) {
            $uri = $resource['uri'] ?? null;
            if (is_string($uri) && ! str_starts_with($uri, 'data:')) {
                $validator->errors()->add('plan', 'El glTF debe incluir buffers y texturas embebidos. Para modelos con archivos externos usa GLB.');

                return;
            }
        }
    }
}
