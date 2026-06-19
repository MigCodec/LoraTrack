# LoraTrack

Dashboard Laravel para inventario y localización de activos mediante LoRaWAN y BLE. Normaliza catálogos externos —con SAP S/4HANA como integración prioritaria— y recibe telemetría desde TTI o MQTT.

La aplicación es multiempresa/multiproyecto sobre una base de datos compartida. Productos, SKU, activos, dispositivos, planos, zonas, telemetría, alertas y conectores quedan aislados por organización.

## Requisitos

- PHP 8.2+
- Composer
- MariaDB 10.6+ o MySQL 8+

## Instalación

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Para desarrollo:

```bash
composer dev
```

La interfaz usa Blade, CSS estático y JavaScript nativo; no requiere Node.js ni npm. Ejecuta el worker en otra consola porque las importaciones SAP y el procesamiento de uplinks usan colas:

```bash
php artisan queue:work
```

Los planos se guardan exclusivamente en `storage/app/private` y se entregan mediante una ruta autenticada. No debe crearse `public/storage` en el servidor.

El seeder crea `test@example.com` con contraseña `password` y rol administrador para desarrollo. No conservar esta credencial en un entorno publicado.

## Empresas y proyectos

Cada usuario accede mediante membresías y puede tener un rol distinto en cada empresa o proyecto. Cualquier persona puede crear su empresa desde `/register` indicando nombre, correo administrativo y contraseña. También se admiten invitaciones para incorporar cuentas a una organización existente. Todas las consultas posteriores quedan limitadas a la organización activa.

Los conectores, procesos MQTT, webhooks TTI, sincronizaciones de catálogo, tareas programadas y archivos privados conservan el identificador de organización. Una URL de un recurso perteneciente a otra organización responde 404.

## Microsoft

Registra una aplicación en Microsoft Entra ID y configura:

```dotenv
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT_ID=
MICROSOFT_REDIRECT_URI="http://localhost:8000/auth/microsoft/callback"
```

El registro público crea inicialmente una cuenta local. Para usar Microsoft, el correo debe pertenecer a un usuario de LoraTrack; en su primer acceso Microsoft se vincula el identificador estable de esa cuenta.

## Conectores

Los administradores crean conectores desde `/connectors`.

- Telemetría: TTI Webhook y MQTT genérico.
- Catálogo: SAP S/4HANA, Business Central, Shopify, Odoo y CSV.

SAP S/4HANA, Business Central, Shopify y Odoo tienen sincronización de catálogo implementada. CSV admite `sku,name,external_id,description,base_unit,status`; `external_id` es opcional y usa el SKU como respaldo. Cada servicio requiere credenciales y permisos válidos del proveedor antes de activarlo.

### TTI Webhook

1. Crea un conector `TTI Webhook` y define un token largo.
2. Activa el conector.
3. Configura en TTI un webhook hacia:

```text
POST https://tu-dominio.example/api/v1/ingest/tti/{connector-ulid}
Authorization: Bearer {token-configurado}
Content-Type: application/json
```

Los eventos se deduplican, almacenan y procesan por cola. El endpoint responde HTTP 202.

El payload decodificado puede incluir listas bajo `observations`, `beacons`, `ble`, `scan` o `devices`. Cada observación debe contener una MAC (`mac`, `mac_address`, `address` o `beacon_mac`) y RSSI (`rssi`, `signal` o `signal_strength`). Para publicar una posición 2D deben existir al menos cuatro anclas no colineales instaladas en el mismo plano.

### SAP S/4HANA

Configura URL base, ruta de `API_PRODUCT_SRV` y autenticación Basic o Bearer. Usa “Probar” antes de activar y “Sincronizar” para enviar la importación a la cola. Los números de material se conservan como texto, incluidos ceros iniciales.

### MQTT

Configura host, puerto, TLS, usuario, contraseña y topic desde el conector. Mantén este listener supervisado en el servidor:

```bash
php artisan loratrack:mqtt-listen
```

Puede limitarse a un conector pasando su ULID. El mensaje debe ser JSON e incluir MAC y RSSI en las mismas claves aceptadas por TTI.

### Decoders de payload

Los administradores pueden crear perfiles reutilizables en `/payload-profiles`. Un perfil puede asociarse a varios productos, reconocer un formato por FPort o por el valor de una ruta dentro de `decoded_payload`, y mapear mediante notación con puntos:

- ruta de la lista de observaciones;
- campo MAC;
- campo RSSI;
- identificador opcional del receptor.

Cada perfil dispone de prioridad, activación y vista previa con un payload TTI completo. Si ningún perfil coincide, se conserva el extractor estándar. No se ejecuta código JavaScript aportado por usuarios.

## Planos, anclas y zonas

Desde `/floor-plans` se pueden:

- crear sitios, edificios y pisos;
- subir planos PNG, JPG, WEBP, PDF o DXF;
- añadir una vista previa raster para PDF/DXF;
- indicar ancho y alto reales en metros;
- crear beacons/scanners y colocarlos sobre el plano;
- calibrar RSSI a un metro y factor ambiental;
- dibujar zonas rectangulares con el mouse.

Las posiciones se calculan por multilateración RSSI. Cuando una posición cae dentro de un rectángulo, la estimación queda asociada a esa zona y el dashboard puede expresar qué activo, producto y SKU está allí. El mapa consulta nuevas estimaciones cada 10 segundos y marca como atrasadas las superiores a 10 minutos.

## Activos, permisos y alertas

Los activos estáticos y móviles se administran por separado. Se soportan beacon fijo para activo estático, beacon móvil observado por scanners fijos y tracker móvil observado por beacons fijos.

La aplicación separa navegación y autorización en cinco grupos:

- `admin`: acceso completo; administra conectores, cuentas, seguridad y auditoría.
- `engineer`: configura planos, anclas, calibración y decoders; consulta la salud técnica.
- `supervisor`: gestiona activos, alertas y supervisa la salud operacional.
- `operator`: registra, asigna y sigue activos durante la operación diaria.
- `viewer`: consulta productos, activos, planos y mapa sin modificar datos.

Los permisos se validan en las rutas; ocultar una opción del menú no sustituye la autorización del servidor.

En `/alerts` se configuran correos, umbral offline, confianza mínima y tipos de alerta. Configura SMTP y ejecuta permanentemente:

```bash
php artisan schedule:work
```

En producción también puede usarse cron para `php artisan schedule:run` cada minuto. La evaluación ocurre cada 10 minutos, evita correos repetidos y vuelve a notificar si la incidencia reaparece.

## Calibración RSSI

Cada plano dispone de un banco de calibración para administradores. Se selecciona la estrategia (beacons fijos o scanners fijos), se indica un punto real `X/Y` en metros y se introduce el RSSI mediano en dBm de cuatro o más anclas. Para cada ancla pueden ajustarse:

- RSSI de referencia `A` medido a 1 metro, en dBm;
- exponente de pérdida ambiental `n`, adimensional;
- RSSI observado, en dBm.

La vista previa calcula coordenadas, error de posición, RMSE, confianza, distancias y residuales en metros. También superpone la posición esperada y calculada en el plano. Los parámetros solo cambian la operación al pulsar **Aplicar** y cada prueba queda en el historial de auditoría.

## Operación y diagnóstico

Los administradores disponen de `/operations/health`, que muestra trabajos pendientes o fallidos, telemetría atascada, conectores, archivos privados, cantidad de anclas por plano y auditoría reciente.

Las mutaciones web generan una auditoría con usuario, ruta, resultado y `X-Request-ID`; nunca se almacenan contraseñas, tokens ni credenciales. Los uplinks aceptan un máximo de 1 MB. Los trabajos de un mismo evento o conector no se ejecutan en paralelo y aplican reintentos escalonados.

Procesos que deben quedar supervisados en producción:

```bash
php artisan queue:work --tries=3 --timeout=300
php artisan schedule:work
php artisan loratrack:mqtt-listen
```

## Verificación

```bash
composer test
./vendor/bin/pint --test
composer audit
```

## Seguridad y despliegue

El repositorio incluye CI para PHP, Dependabot y un despliegue SSH directo sin PAT para repositorios públicos. Las acciones están fijadas por SHA y el despliegue exige un Environment llamado `production`, verificación estricta de la clave del host y un `.env` de producción preexistente. CI se ejecuta por separado y no bloquea el despliegue solicitado al hacer push a `main`.

Antes de publicar o desplegar, aplica la configuración descrita en [despliegue seguro](docs/security/deployment.md), revisa la [política de seguridad](SECURITY.md) y completa la [matriz de aseguramiento](docs/security/assurance.md). Estos controles técnicos no equivalen por sí solos a una certificación ISO ni a la aprobación de seguridad de un cliente.

Las decisiones y reglas de arquitectura están en [AGENTS.md](AGENTS.md).
