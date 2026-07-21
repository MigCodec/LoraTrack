# ADR 0003: 2D Floor Plan and 3D Model Navigation

## Context

LoraTrack stored images, PDFs, and DXF files, but the editor could only display a raster preview without pan or zoom. The system needs to navigate 2D plans and 3D models while preserving the metric coordinates used by zones, installations, and position estimates.

The project does not use npm or a frontend build pipeline. Files for each organization remain in private storage.

## Decision

- `floor_plans.view_mode` explicitly distinguishes `2d` from `3d`.
- Supported 3D models are GLB and self-contained glTF. GLB is recommended because it packages geometry, materials, and textures in one file.
- The original file is served through an authenticated, tenant-scoped route. An optional raster preview keeps 2D zone and anchor editing available.
- `width_meters` and `height_meters` represent local X/Y floor axes. `depth_meters` represents vertical extent available for model normalization.
- `model_transform` stores scale, vertical rotation, and offset. If no scale is declared, the viewer fits the model to the metric floor volume without modifying the file.
- Domain coordinates are projected in Three.js as `(x - width/2, z, y - height/2)`. Model provider coordinate systems do not leak into positioning.
- The 2D viewer uses native JavaScript for zoom and pan without changing normalized geometries.
- The 3D viewer uses Three.js `0.184.0`, `GLTFLoader`, and `OrbitControls`, loaded through an import map pinned to that version.

## Alternatives Considered

- Always rasterize 3D models: rejected because it prevents spatial inspection and orbit navigation.
- Add a SPA or npm pipeline: rejected because it contradicts the current Blade/native JavaScript architecture.
- Accept glTF with external resources: rejected because it complicates private storage, authorization, and safe resolution of multiple files.
- Convert models in PHP: rejected because CAD/3D conversion requires specialized tooling and should not run inside a web request.

## Consequences

- The browser requires WebGL for 3D view.
- The 3D viewer depends on a versioned Three.js resource. Offline installations must vendor exactly that version under `public/vendor`.
- Geometric editing remains in 2D. Direct placement through raycast on 3D surfaces is outside this decision.
- The system keeps a single local metric frame for 2D and 3D, avoiding reinterpretation of historical observations.
