import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const output = path.join(root, 'public', 'examples', 'bodega-demo.glb');

const materials = [
    {name: 'Concrete', color: [0.56, 0.60, 0.64, 1], metallic: 0, roughness: 0.92},
    {name: 'Walls', color: [0.88, 0.91, 0.94, 1], metallic: 0, roughness: 0.8},
    {name: 'Steel blue', color: [0.08, 0.28, 0.48, 1], metallic: 0.55, roughness: 0.42},
    {name: 'Rack orange', color: [0.92, 0.32, 0.08, 1], metallic: 0.18, roughness: 0.52},
    {name: 'Pallet wood', color: [0.55, 0.31, 0.13, 1], metallic: 0, roughness: 0.9},
    {name: 'Safety yellow', color: [0.96, 0.65, 0.04, 1], metallic: 0.05, roughness: 0.58},
    {name: 'Door dark', color: [0.10, 0.14, 0.20, 1], metallic: 0.35, roughness: 0.5},
    {name: 'Office glass', color: [0.35, 0.72, 0.88, 0.55], metallic: 0.1, roughness: 0.25, blend: true},
    {name: 'Zone green', color: [0.10, 0.72, 0.42, 1], metallic: 0, roughness: 0.65},
];

const buckets = materials.map(() => ({positions: [], normals: [], indices: []}));

function box(name, center, size, material) {
    const [cx, cy, cz] = center;
    const [sx, sy, sz] = size.map((value) => value / 2);
    const faces = [
        {normal: [1, 0, 0], corners: [[sx, -sy, -sz], [sx, sy, -sz], [sx, sy, sz], [sx, -sy, sz]]},
        {normal: [-1, 0, 0], corners: [[-sx, -sy, sz], [-sx, sy, sz], [-sx, sy, -sz], [-sx, -sy, -sz]]},
        {normal: [0, 1, 0], corners: [[-sx, sy, -sz], [-sx, sy, sz], [sx, sy, sz], [sx, sy, -sz]]},
        {normal: [0, -1, 0], corners: [[-sx, -sy, sz], [-sx, -sy, -sz], [sx, -sy, -sz], [sx, -sy, sz]]},
        {normal: [0, 0, 1], corners: [[sx, -sy, sz], [sx, sy, sz], [-sx, sy, sz], [-sx, -sy, sz]]},
        {normal: [0, 0, -1], corners: [[-sx, -sy, -sz], [-sx, sy, -sz], [sx, sy, -sz], [sx, -sy, -sz]]},
    ];
    const bucket = buckets[material];
    faces.forEach(({normal, corners}) => {
        const base = bucket.positions.length / 3;
        corners.forEach(([x, y, z]) => {
            bucket.positions.push(cx + x, cy + y, cz + z);
            bucket.normals.push(...normal);
        });
        bucket.indices.push(base, base + 1, base + 2, base, base + 2, base + 3);
    });
    return name;
}

// Building shell: 40 m wide (X), 24 m deep (Z), 8 m high (Y).
box('Floor', [0, -0.1, 0], [40, 0.2, 24], 0);
box('Back wall', [0, 4, 11.85], [40, 8, 0.3], 1);
box('Left wall', [-19.85, 4, 0], [0.3, 8, 24], 1);
box('Right wall', [19.85, 4, 0], [0.3, 8, 24], 1);
box('Front wall left', [-13.5, 4, -11.85], [13, 8, 0.3], 1);
box('Front wall right', [13.5, 4, -11.85], [13, 8, 0.3], 1);
box('Loading door header', [0, 7.25, -11.85], [14, 1.5, 0.3], 1);
box('Loading door', [0, 3.25, -11.7], [12.5, 6.5, 0.18], 6);

// Roof structure intentionally open for comfortable navigation.
for (let x = -18; x <= 18; x += 6) {
    box(`Roof beam ${x}`, [x, 7.65, 0], [0.22, 0.3, 23.5], 2);
}
for (const z of [-11, 0, 11]) {
    box(`Cross beam ${z}`, [0, 7.5, z], [39.5, 0.28, 0.24], 2);
}

// Four warehouse rack rows with three levels.
for (const x of [-13.5, -4.5, 4.5, 13.5]) {
    for (const z of [-5, 0, 5]) {
        for (const postX of [-1.35, 1.35]) {
            for (const postZ of [-1.9, 1.9]) {
                box('Rack post', [x + postX, 2.6, z + postZ], [0.16, 5.2, 0.16], 3);
            }
        }
        for (const y of [0.9, 2.5, 4.1]) {
            box('Rack shelf', [x, y, z], [3, 0.14, 4], 2);
            for (const palletX of [-0.72, 0.72]) {
                box('Pallet', [x + palletX, y + 0.17, z], [1.25, 0.20, 1.1], 4);
                box('Cargo', [x + palletX, y + 0.66, z], [1.12, 0.78, 1], (Math.abs(x + z + y) % 2) ? 5 : 8);
            }
        }
    }
}

// Office and safety elements.
box('Office floor', [-14.5, 0.06, 8.2], [9, 0.12, 6.2], 0);
box('Office back', [-14.5, 1.6, 11.15], [9, 3.2, 0.18], 1);
box('Office side', [-18.85, 1.6, 8.2], [0.18, 3.2, 6], 1);
box('Office glass front', [-14.5, 1.6, 5.25], [9, 3.2, 0.10], 7);
box('Office glass side', [-10.15, 1.6, 8.2], [0.10, 3.2, 6], 7);
for (const x of [-18, -12, -6, 6, 12, 18]) {
    box('Safety bollard', [x, 0.55, -9.5], [0.22, 1.1, 0.22], 5);
}

// Loading zone markings and a compact forklift.
for (const x of [-6, -3, 0, 3, 6]) {
    box('Loading stripe', [x, 0.015, -8], [0.18, 0.03, 5], 5);
}
box('Forklift body', [10.5, 0.65, -7.5], [2.2, 1.3, 1.4], 5);
box('Forklift mast', [9.3, 1.5, -7.5], [0.18, 3, 1.5], 6);
box('Forklift fork 1', [8.6, 0.18, -7.95], [1.6, 0.10, 0.16], 6);
box('Forklift fork 2', [8.6, 0.18, -7.05], [1.6, 0.10, 0.16], 6);

const json = {
    asset: {version: '2.0', generator: 'LoraTrack warehouse GLB generator'},
    scene: 0,
    scenes: [{name: 'Bodega LoraTrack', nodes: [0]}],
    nodes: [{name: 'Bodega demo 40x24x8m', mesh: 0}],
    meshes: [{name: 'Warehouse geometry', primitives: []}],
    materials: materials.map((material) => ({
        name: material.name,
        pbrMetallicRoughness: {
            baseColorFactor: material.color,
            metallicFactor: material.metallic,
            roughnessFactor: material.roughness,
        },
        alphaMode: material.blend ? 'BLEND' : 'OPAQUE',
        doubleSided: Boolean(material.blend),
    })),
    accessors: [],
    bufferViews: [],
    buffers: [{byteLength: 0}],
};

const binaryParts = [];
let binaryLength = 0;

function appendBuffer(buffer, target) {
    const padding = (4 - (binaryLength % 4)) % 4;
    if (padding) {
        binaryParts.push(Buffer.alloc(padding));
        binaryLength += padding;
    }
    const byteOffset = binaryLength;
    binaryParts.push(buffer);
    binaryLength += buffer.length;
    const view = {buffer: 0, byteOffset, byteLength: buffer.length};
    if (target) view.target = target;
    json.bufferViews.push(view);
    return json.bufferViews.length - 1;
}

function minMax(values) {
    const min = [Infinity, Infinity, Infinity];
    const max = [-Infinity, -Infinity, -Infinity];
    for (let index = 0; index < values.length; index += 3) {
        for (let axis = 0; axis < 3; axis++) {
            min[axis] = Math.min(min[axis], values[index + axis]);
            max[axis] = Math.max(max[axis], values[index + axis]);
        }
    }
    return {min, max};
}

buckets.forEach((bucket, materialIndex) => {
    if (!bucket.indices.length) return;

    const positionArray = new Float32Array(bucket.positions);
    const normalArray = new Float32Array(bucket.normals);
    const indexArray = new Uint32Array(bucket.indices);
    const positionView = appendBuffer(Buffer.from(positionArray.buffer), 34962);
    const normalView = appendBuffer(Buffer.from(normalArray.buffer), 34962);
    const indexView = appendBuffer(Buffer.from(indexArray.buffer), 34963);
    const bounds = minMax(bucket.positions);

    const positionAccessor = json.accessors.push({
        bufferView: positionView,
        componentType: 5126,
        count: positionArray.length / 3,
        type: 'VEC3',
        min: bounds.min,
        max: bounds.max,
    }) - 1;
    const normalAccessor = json.accessors.push({
        bufferView: normalView,
        componentType: 5126,
        count: normalArray.length / 3,
        type: 'VEC3',
    }) - 1;
    const indexAccessor = json.accessors.push({
        bufferView: indexView,
        componentType: 5125,
        count: indexArray.length,
        type: 'SCALAR',
        min: [Math.min(...bucket.indices)],
        max: [Math.max(...bucket.indices)],
    }) - 1;

    json.meshes[0].primitives.push({
        attributes: {POSITION: positionAccessor, NORMAL: normalAccessor},
        indices: indexAccessor,
        material: materialIndex,
        mode: 4,
    });
});

const binaryChunk = Buffer.concat(binaryParts);
json.buffers[0].byteLength = binaryChunk.length;

function paddedJson(document) {
    const content = Buffer.from(JSON.stringify(document), 'utf8');
    const padding = (4 - (content.length % 4)) % 4;
    return Buffer.concat([content, Buffer.alloc(padding, 0x20)]);
}

const jsonChunk = paddedJson(json);
const totalLength = 12 + 8 + jsonChunk.length + 8 + binaryChunk.length;
const header = Buffer.alloc(12);
header.writeUInt32LE(0x46546c67, 0);
header.writeUInt32LE(2, 4);
header.writeUInt32LE(totalLength, 8);

const jsonHeader = Buffer.alloc(8);
jsonHeader.writeUInt32LE(jsonChunk.length, 0);
jsonHeader.writeUInt32LE(0x4e4f534a, 4);

const binaryHeader = Buffer.alloc(8);
binaryHeader.writeUInt32LE(binaryChunk.length, 0);
binaryHeader.writeUInt32LE(0x004e4942, 4);

fs.mkdirSync(path.dirname(output), {recursive: true});
fs.writeFileSync(output, Buffer.concat([header, jsonHeader, jsonChunk, binaryHeader, binaryChunk]));

console.log(JSON.stringify({
    output,
    bytes: totalLength,
    dimensions_meters: {width: 40, height: 24, vertical: 8},
    primitives: json.meshes[0].primitives.length,
    materials: materials.length,
}, null, 2));
