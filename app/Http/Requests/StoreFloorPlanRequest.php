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
            'plan' => ['required', 'file', 'max:40960'],
            'preview' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'width_meters' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'height_meters' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'depth_meters' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'model_scale' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'model_rotation_y' => ['nullable', 'numeric', 'between:-360,360'],
            'model_offset_x' => ['nullable', 'numeric', 'between:-100000,100000'],
            'model_offset_y' => ['nullable', 'numeric', 'between:-100000,100000'],
            'model_offset_z' => ['nullable', 'numeric', 'between:-100000,100000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Selecciona la ubicación del plano.',
            'name.required' => 'Ingresa un nombre para el plano.',
            'view_mode.required' => 'Selecciona si cargarás un plano 2D o un modelo 3D.',
            'view_mode.in' => 'El tipo de plano seleccionado no es válido.',
            'plan.required' => 'Selecciona el archivo que quieres cargar.',
            'plan.file' => 'El archivo seleccionado no pudo ser procesado.',
            'plan.max' => 'El archivo no puede superar el tamaño máximo permitido.',
            'width_meters.required' => 'Ingresa el ancho real del plano.',
            'width_meters.numeric' => 'El ancho debe ser un valor numérico expresado en metros.',
            'width_meters.gt' => 'El ancho debe ser mayor que cero.',
            'height_meters.required' => 'Ingresa el largo real del plano.',
            'height_meters.numeric' => 'El largo debe ser un valor numérico expresado en metros.',
            'height_meters.gt' => 'El largo debe ser mayor que cero.',
            'depth_meters.numeric' => 'La altura máxima debe ser un valor numérico expresado en metros.',
            'depth_meters.gt' => 'La altura máxima debe ser mayor que cero.',
            'model_scale.numeric' => 'La escala manual debe ser un valor numérico.',
            'model_scale.gt' => 'La escala manual debe ser mayor que cero.',
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

            if ($mode === '3d' && $extension === 'glb') {
                $this->validateGlb($validator, $file);
            }
        });
    }

    private function validateGlb(Validator $validator, UploadedFile $file): void
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = $handle === false ? false : fread($handle, 12);
        if (is_resource($handle)) {
            fclose($handle);
        }

        if (! is_string($header) || strlen($header) !== 12) {
            $validator->errors()->add('plan', 'El archivo GLB está vacío o incompleto.');

            return;
        }

        $values = unpack('Vmagic/Vversion/Vlength', $header);
        if (
            ! is_array($values)
            || ($values['magic'] ?? null) !== 0x46546C67
            || ($values['version'] ?? null) !== 2
            || ($values['length'] ?? null) !== $file->getSize()
        ) {
            $validator->errors()->add('plan', 'El archivo seleccionado no es un modelo GLB 2.0 válido.');
        }
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
