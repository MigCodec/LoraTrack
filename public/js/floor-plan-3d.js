import * as THREE from 'three';
import {OrbitControls} from 'three/addons/controls/OrbitControls.js';
import {GLTFLoader} from 'three/addons/loaders/GLTFLoader.js';

const container = document.querySelector('#floor-plan-3d');

if (container) {
    const status = container.querySelector('[data-3d-status]');
    const width = Math.max(0.001, Number(container.dataset.widthMeters));
    const length = Math.max(0.001, Number(container.dataset.heightMeters));
    const verticalExtent = Math.max(1, Number(container.dataset.depthMeters) || Math.min(width, length) * 0.25);
    const transform = JSON.parse(container.dataset.transform || '{}');
    const markers = JSON.parse(document.querySelector('#floor-plan-3d-markers')?.textContent || '[]');
    const scene = new THREE.Scene();
    const markerGroup = new THREE.Group();
    scene.background = new THREE.Color(0xf1f5f9);
    scene.add(markerGroup);

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({antialias: true, alpha: false, powerPreference: 'high-performance'});
    } catch (error) {
        status.textContent = 'Este navegador o equipo no pudo iniciar WebGL.';
        status.classList.add('is-error');
    }

    if (renderer) {
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.shadowMap.enabled = true;
    container.prepend(renderer.domElement);

    const camera = new THREE.PerspectiveCamera(45, 1, 0.01, Math.max(width, length, verticalExtent) * 100);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.screenSpacePanning = true;
    controls.minDistance = 0.25;
    controls.maxDistance = Math.max(width, length, verticalExtent) * 12;

    scene.add(new THREE.HemisphereLight(0xffffff, 0x64748b, 2.2));
    const keyLight = new THREE.DirectionalLight(0xffffff, 2.5);
    keyLight.position.set(width, verticalExtent * 2, length);
    keyLight.castShadow = true;
    scene.add(keyLight);

    const gridSize = Math.max(width, length);
    const gridDivisions = Math.max(2, Math.min(100, Math.round(gridSize)));
    const grid = new THREE.GridHelper(gridSize, gridDivisions, 0x94a3b8, 0xcbd5e1);
    grid.position.y = -0.01;
    scene.add(grid);

    const footprint = new THREE.LineSegments(
        new THREE.EdgesGeometry(new THREE.BoxGeometry(width, 0.01, length)),
        new THREE.LineBasicMaterial({color: 0x475569}),
    );
    footprint.position.y = -0.005;
    scene.add(footprint);

    const offset = new THREE.Vector3(
        Number(transform.offset_x) || 0,
        Number(transform.offset_y) || 0,
        Number(transform.offset_z) || 0,
    );

    const replaceMarkers = (items) => {
        while (markerGroup.children.length) {
            const child = markerGroup.children[0];
            markerGroup.remove(child);
            child.geometry?.dispose();
            child.material?.dispose();
        }
        items.forEach((marker) => {
            const isAsset = marker.kind === 'asset';
            const geometry = isAsset
                ? new THREE.SphereGeometry(Math.max(gridSize * 0.008, 0.12), 20, 12)
                : new THREE.OctahedronGeometry(Math.max(gridSize * 0.007, 0.1));
            const material = new THREE.MeshStandardMaterial({
                color: isAsset ? 0x14b8a6 : 0x2563eb,
                emissive: isAsset ? 0x042f2e : 0x172554,
                emissiveIntensity: 0.3,
                roughness: 0.35,
            });
            const mesh = new THREE.Mesh(geometry, material);
            mesh.position.set(
                Number(marker.x) - width / 2 + offset.x,
                Math.max(0.08, Number(marker.z) || 0.35) + offset.y,
                Number(marker.y) - length / 2 + offset.z,
            );
            mesh.userData = {name: marker.name, kind: marker.kind};
            markerGroup.add(mesh);
        });
    };

    const homeView = () => {
        const distance = Math.max(width, length, verticalExtent) * 1.35;
        camera.position.set(distance * 0.8, distance * 0.75, distance);
        controls.target.set(offset.x, Math.min(verticalExtent * 0.25, 2) + offset.y, offset.z);
        controls.update();
    };

    const topView = () => {
        camera.position.set(offset.x, Math.max(width, length) * 1.5 + offset.y, offset.z + 0.001);
        camera.up.set(0, 0, -1);
        controls.target.set(offset.x, offset.y, offset.z);
        controls.update();
    };

    const loader = new GLTFLoader();
    loader.load(container.dataset.modelUrl, (gltf) => {
        const model = gltf.scene;
        model.rotation.y = THREE.MathUtils.degToRad(Number(transform.rotation_y_degrees) || 0);
        model.updateMatrixWorld(true);

        let bounds = new THREE.Box3().setFromObject(model);
        const size = bounds.getSize(new THREE.Vector3());
        const requestedScale = Number(transform.scale);
        const automaticScale = Math.min(
            width / Math.max(size.x, 0.0001),
            length / Math.max(size.z, 0.0001),
            verticalExtent / Math.max(size.y, 0.0001),
        );
        const scale = requestedScale > 0 ? requestedScale : automaticScale;
        model.scale.setScalar(scale);
        model.updateMatrixWorld(true);

        bounds = new THREE.Box3().setFromObject(model);
        const center = bounds.getCenter(new THREE.Vector3());
        model.position.set(
            offset.x - center.x,
            offset.y - bounds.min.y,
            offset.z - center.z,
        );
        model.traverse((child) => {
            if (!child.isMesh) return;
            child.castShadow = true;
            child.receiveShadow = true;
        });
        scene.add(model);
        replaceMarkers(markers);
        status.hidden = true;
        homeView();

        if (container.dataset.endpoint) {
            const refreshMarkers = async () => {
                try {
                    const response = await fetch(container.dataset.endpoint, {headers: {Accept: 'application/json'}});
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    replaceMarkers([
                        ...(data.anchors || []).map((anchor) => ({
                            kind: 'anchor',
                            name: anchor.name,
                            x: anchor.x_meters,
                            y: anchor.y_meters,
                            z: anchor.z_meters,
                        })),
                        ...(data.positions || []).map((position) => ({
                            kind: 'asset',
                            name: position.name,
                            x: position.x_meters,
                            y: position.y_meters,
                            z: position.z_meters,
                        })),
                    ]);
                    const updated = document.querySelector('#map-updated');
                    if (updated) updated.textContent = `Actualizado ${new Date(data.generated_at).toLocaleTimeString()}`;
                } catch (error) {
                    const updated = document.querySelector('#map-updated');
                    if (updated) updated.textContent = 'No fue posible actualizar las posiciones';
                }
            };
            refreshMarkers();
            window.setInterval(refreshMarkers, 10000);
        }
    }, (event) => {
        if (!event.total) return;
        status.textContent = `Cargando modelo 3D… ${Math.round(event.loaded / event.total * 100)}%`;
    }, () => {
        status.textContent = 'No fue posible cargar el modelo 3D. Verifica que el GLB o glTF sea válido y autocontenido.';
        status.classList.add('is-error');
    });

    document.querySelector('[data-3d-view="home"]')?.addEventListener('click', () => {
        camera.up.set(0, 1, 0);
        homeView();
    });
    document.querySelector('[data-3d-view="top"]')?.addEventListener('click', topView);

    const resize = () => {
        const rect = container.getBoundingClientRect();
        const renderWidth = Math.max(1, Math.floor(rect.width));
        const renderHeight = Math.max(1, Math.floor(rect.height));
        renderer.setSize(renderWidth, renderHeight, false);
        camera.aspect = renderWidth / renderHeight;
        camera.updateProjectionMatrix();
    };

    new ResizeObserver(resize).observe(container);
    resize();
    homeView();

    const animate = () => {
        controls.update();
        renderer.render(scene, camera);
        requestAnimationFrame(animate);
    };
    animate();
    }
}
