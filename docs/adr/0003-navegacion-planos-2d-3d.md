# ADR 0003: navegación de planos 2D y modelos 3D

## Contexto

LoraTrack almacenaba imágenes, PDF y DXF, pero el editor sólo podía representar una vista previa raster sin desplazamiento ni zoom. Se requiere navegar planos 2D y modelos 3D manteniendo las coordenadas métricas usadas por zonas, instalaciones y posiciones estimadas.

El proyecto no usa npm ni un proceso de compilación. Los archivos de cada organización permanecen en almacenamiento privado.

## Decisión

- `floor_plans.view_mode` distingue explícitamente `2d` de `3d`.
- Los modelos 3D admitidos son GLB y glTF autocontenido. GLB es el formato recomendado porque empaqueta geometría, materiales y texturas en un archivo.
- El archivo original se sirve mediante una ruta autenticada y con alcance tenant. Una vista previa raster opcional permite seguir editando zonas y anclas en 2D.
- Las dimensiones `width_meters` y `height_meters` representan los ejes locales X/Y del piso. `depth_meters` representa la extensión vertical disponible para normalizar el modelo.
- `model_transform` conserva escala, rotación vertical y desplazamiento. Cuando no se declara escala, el visor ajusta el modelo al volumen métrico del plano sin modificar el archivo.
- Las coordenadas del dominio se proyectan en Three.js como `(x - width/2, z, y - height/2)`. El proveedor del modelo no filtra su sistema de coordenadas al posicionamiento.
- El visor 2D usa JavaScript nativo para zoom y desplazamiento sin cambiar las geometrías normalizadas.
- El visor 3D usa Three.js `0.184.0`, `GLTFLoader` y `OrbitControls`, cargados con un import map fijado a esa versión.

## Alternativas consideradas

- Rasterizar siempre los modelos 3D: no permite inspección espacial ni navegación orbital.
- Incorporar una SPA o un pipeline npm: contradice la arquitectura Blade/JavaScript nativo vigente.
- Aceptar glTF con recursos externos: complica almacenamiento privado, autorización y resolución segura de múltiples archivos.
- Convertir modelos en PHP: las conversiones CAD/3D requieren herramientas especializadas y no deben ejecutarse dentro de una petición web.

## Consecuencias

- El navegador necesita WebGL para la vista 3D.
- La disponibilidad del visor 3D depende del recurso versionado de Three.js; para instalaciones sin salida a Internet se deberá vendorizar exactamente esa versión bajo `public/vendor`.
- La edición geométrica continúa en la representación 2D. La colocación directa mediante raycast sobre superficies 3D queda fuera de esta decisión.
- El sistema conserva un único marco métrico local para vistas 2D y 3D, evitando reinterpretar observaciones históricas.
