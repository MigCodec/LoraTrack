# AGENTS.md — LoraTrack

## Propósito

LoraTrack es una plataforma de inventario y localización de activos para entornos indoor y outdoor. Integra catálogos de productos desde sistemas externos, recibe telemetría IoT/LoRaWAN y muestra en un dashboard la ubicación actual e histórica de cada activo.

Este documento guía a cualquier agente que modifique el repositorio. Si una solicitud contradice estas reglas, debe señalarse la contradicción antes de implementar.

## Estado actual del repositorio

El proyecto parte de una instalación mínima de Laravel 12:

- PHP 8.2 o superior y Laravel 12.
- Blade como tecnología exclusiva de vistas del servidor.
- CSS estático y JavaScript nativo servidos desde `public/`; no usar npm, Vite, Tailwind como build tool, Axios, React, Vue, Inertia ni otra SPA.
- MariaDB/MySQL como base de datos de aplicación.
- PHPUnit 11 para pruebas.
- Los módulos principales de catálogo, activos, dispositivos, planos, posicionamiento, alertas, conectores e identidad están implementados; inspeccionar su estado antes de modificarlos.

No asumir que una capacidad descrita aquí ya existe. Verificar siempre el código y las migraciones antes de extenderla.

## Modelo del producto

Mantener separados estos conceptos:

- **Producto:** definición comercial proveniente del catálogo, identificada normalmente por SKU.
- **Activo:** instancia física rastreable de un producto. Tiene identidad propia; un SKU no identifica por sí solo un activo.
- **Dispositivo:** hardware registrado en la plataforma, por ejemplo beacon BLE, scanner, gateway o tracker B1000.
- **Vinculación:** relación temporal entre un activo y un dispositivo. Debe conservar fecha de inicio y fin para mantener trazabilidad.
- **Observación:** medición recibida de un dispositivo o proveedor, con hora del dispositivo, hora de recepción, potencia/señal y carga original.
- **Posición estimada:** resultado calculado a partir de observaciones. Incluye método, coordenadas, nivel de confianza, precisión estimada y referencias a la evidencia usada.
- **Ubicación física:** organización jerárquica de sitio, edificio, piso y zona, junto con su sistema de coordenadas y mapa.
- **Conector:** adaptador aislado que intercambia datos con un sistema externo sin filtrar su formato al dominio interno.

### Modos de rastreo

El sistema debe soportar al menos dos topologías sin mezclar sus responsabilidades:

1. **Beacon móvil, scanners fijos:** un beacon BLE está vinculado a un activo móvil. Varios scanners de posición conocida detectan el beacon y el sistema estima la posición del activo.
2. **Beacons fijos, tracker móvil:** beacons instalados en posiciones conocidas son detectados por un tracker B1000 vinculado al activo móvil. El sistema estima la posición del tracker y, por asociación, la del activo.

Los activos realmente estáticos también pueden tener una posición asignada o verificada mediante beacon. No convertir “estático” y “móvil” en tipos rígidos: son comportamientos o estrategias de localización que pueden cambiar en el tiempo.

## Arquitectura objetivo

Comenzar como **monolito modular Laravel**. Mantener límites claros dentro de `app/` y evitar una arquitectura de microservicios prematura.

Módulos de dominio previstos:

- `Catalog`: productos, SKU y sincronización de catálogos.
- `Assets`: activos físicos, estado y vinculaciones con dispositivos.
- `Devices`: beacons, scanners, gateways, trackers y sus instalaciones.
- `Locations`: sitios, edificios, pisos, zonas, mapas y coordenadas conocidas.
- `Telemetry`: ingreso, validación, normalización y almacenamiento de observaciones.
- `Positioning`: estrategias de cálculo, estimaciones, confianza e historial.
- `Connectors`: integraciones de catálogo y proveedores IoT, incluida The Things Industries (TTI).
- `Dashboard`: consultas optimizadas para estado actual, mapas, alertas e historial.
- `Identity`: autenticación local, acceso con Microsoft y autorización de usuarios.

Usar nombres de clases y código en inglés. Los textos de interfaz pueden localizarse; no incrustarlos en la lógica de dominio.

### Dependencias

- El dominio no debe depender de DTOs, nombres de campos ni SDKs de proveedores.
- Cada conector traduce el formato externo a contratos internos versionados.
- `Telemetry` almacena observaciones normalizadas; `Positioning` las consume, pero no conoce HTTP, TTI ni el conector que las originó.
- El dashboard consulta modelos de lectura o servicios de aplicación; no implementa triangulación ni interpreta payloads.
- Las integraciones entrantes y sincronizaciones pesadas deben despachar trabajos idempotentes a la cola.

## Modelo de conectores

La aplicación debe ofrecer una pantalla Blade para crear, configurar, probar, activar y desactivar conectores. Existen dos familias distintas y no deben compartir contratos de negocio:

### Conectores de telemetría

Reciben eventos de dispositivos y producen un `NormalizedTelemetryEvent` interno.

- **TTI Webhook:** opción principal para recibir por HTTPS los mensajes enviados por The Things Stack.
- **MQTT genérico:** se conecta a un broker configurable y se suscribe a uno o más topics. También puede apuntar al servidor MQTT de TTI, pero no debe asumir su formato salvo que se seleccione explícitamente un perfil/decoder TTI.

El formulario debe adaptar sus campos al proveedor seleccionado. Como mínimo debe contemplar nombre, estado, endpoint o broker, puerto/TLS cuando corresponda, credenciales cifradas, topics/eventos, decoder y botón “Probar conexión”. Mostrar última actividad, último error sanitizado y estado de salud.

### Conectores de catálogo

Importan productos desde un sistema de inventario y producen un `NormalizedProduct` interno. La selección inicial prevista es:

- Microsoft Dynamics 365 Business Central;
- SAP S/4HANA, como conector obligatorio de primera clase;
- Shopify;
- Odoo;
- CSV como mecanismo manual controlado y plantilla de referencia.

La arquitectura debe permitir añadir otros proveedores registrando una implementación y sus metadatos, sin modificar un `switch` central en controladores o vistas. Cada proveedor declara capacidades, campos de configuración, estrategia de autenticación, versión de API y documentación oficial.

Una instancia de conector debe conservar proveedor, nombre, configuración no secreta, credenciales cifradas, estado, versión del contrato, cursor de sincronización, última ejecución correcta y último error sanitizado. Nunca devolver secretos a Blade después de guardarlos.

### Requisitos del conector SAP

- La implementación inicial debe orientarse a SAP S/4HANA y la API oficial de Product Master publicada en SAP Business Accelerator Hub.
- Soportar URL/base path configurables para no acoplarse a un tenant o deployment específico de SAP.
- Encapsular autenticación, versión y dialecto OData en el adaptador SAP. No propagar entidades como `A_Product` fuera del conector.
- Normalizar como mínimo identificador externo, código de producto/material, SKU cuando exista, nombre/descripción, unidad base, estado y datos de modificación disponibles.
- Implementar paginación OData y sincronización incremental/delta sólo cuando la API y versión elegidas lo soporten oficialmente.
- Tratar catálogo de productos y existencias como capacidades separadas: Product Master no implica automáticamente disponibilidad de stock por centro o almacén.
- Permitir probar credenciales y permisos con una lectura mínima, con timeout, sin importar productos durante la prueba.
- Mantener fixtures y contract tests por versión de S/4HANA soportada. Una variante SAP ECC u otro producto SAP requiere un adaptador o perfil explícito; no debe simularse como S/4HANA.

### Reglas comunes

- Separar el registro de proveedores disponibles de las instancias configuradas por el usuario.
- Validar la configuración en el servidor aunque el formulario oculte campos.
- Ejecutar pruebas de conexión con timeout estricto, sin persistir cambios implícitos y sin exponer respuestas sensibles.
- Registrar inicio, resultado y métricas de cada ejecución, no los secretos ni payloads sensibles completos.
- Usar colas para consumidores MQTT persistentes, sincronizaciones y procesamiento; no mantener conexiones largas dentro de una petición web.
- Implementar cada conector contra documentación oficial vigente y fijar la versión de API cuando el proveedor la ofrezca.
- Mantener fixtures sanitizados de respuestas reales para contract tests.

## Ingesta LoRaWAN y TTI

The Things Industries (TTI) gestiona toda la red LoRaWAN, dispositivos, gateways y entrega de uplinks. LoraTrack no implementa un network server LoRaWAN ni administra el protocolo de radio. Su responsabilidad comienza al recibir por HTTPS los eventos enviados por TTI.

- Recibir webhooks HTTPS de TTI en endpoints de API dedicados; no usar rutas web con sesión para telemetría.
- Autenticar TTI mediante el mecanismo soportado por su webhook y documentar la rotación del secreto.
- Verificar límites de tamaño, `Content-Type`, esquema y campos obligatorios antes de procesar.
- Persistir de forma segura el evento original o una referencia auditable antes de normalizarlo. Nunca registrar secretos ni datos sensibles sin filtrar.
- Deduplicar mediante un identificador estable del proveedor; cuando no exista, construir una clave determinista con los campos pertinentes.
- Responder rápido al webhook y procesar cálculos mediante cola. Un reintento debe producir el mismo resultado que el primer intento.
- Conservar por separado `observed_at`, `received_at` y, cuando aplique, `processed_at`. Guardar tiempos en UTC y convertirlos sólo en presentación.
- Versionar decodificadores de payload. Los cambios de firmware no deben reescribir silenciosamente la interpretación histórica.
- Tratar mensajes fuera de orden, duplicados y tardíos como condiciones normales.
- Normalizar los uplinks antes de alimentar gráficos, estado de dispositivos o posicionamiento. Ninguna vista debe consultar ni interpretar directamente el JSON original de TTI.
- Preparar proyecciones o consultas agregadas para gráficos por intervalo; no recalcular series completas en cada carga de una vista Blade.
- Para TTI, admitir los campos documentados `uplink_message.frm_payload`, `uplink_message.decoded_payload`, `uplink_message.rx_metadata` y las marcas `received_at` sin suponer que todos estarán presentes.
- Si se usa el servidor MQTT de TTI, respetar MQTT 3.1.1, QoS 0, API key y el formato de usuario/topic indicado por el deployment de TTI. Diseñar recuperación ante desconexiones porque QoS 0 no garantiza entrega.

## Conectores de catálogo

Los conectores pueden importar productos y SKU desde aplicaciones externas conocidas. Deben implementar un contrato común con:

- autenticación y configuración por integración;
- lectura paginada y sincronización incremental cuando el proveedor lo permita;
- mapeo explícito a un DTO interno;
- claves externas con `provider` y `external_id`;
- `upsert` idempotente y reporte de conflictos;
- reintentos con backoff para errores transitorios y respeto de rate limits;
- cursores/checkpoints persistentes, observabilidad y ejecución por cola.

No borrar productos locales porque desaparezcan de una respuesta parcial. Las políticas de archivado o eliminación deben ser explícitas. Separar el producto importado de la instancia física rastreable.

## Interfaz y marca

- Construir todas las pantallas con Blade, CSS estático reutilizable y JavaScript nativo progresivo sólo cuando sea necesario.
- La navegación debe incluir al menos Dashboard, Productos, Activos, Dispositivos, Ubicaciones, Conectores y Usuarios/Configuración según permisos.
- En Conectores, separar visualmente “Telemetría” de “Catálogo”; mostrar tarjetas de proveedores disponibles y una lista de instancias configuradas.
- LoraTrack usa una identidad neutral predeterminada y permite que cada organización configure logo, color principal, secundario y de acento. Centralizarla como variables CSS (`brand-primary`, `brand-secondary`, `brand-accent`, neutrales y colores semánticos); no dispersar colores de marca en vistas.
- No incluir nombres, logotipos, tipografías ni paletas de terceros como identidad predeterminada. Toda personalización aportada por una organización es contenido tenant y debe permanecer aislada en almacenamiento privado.
- Los colores de series en gráficos deben ser distinguibles, accesibles y consistentes. No usar sólo color para comunicar estados.
- Cumplir WCAG AA para contraste, foco visible, etiquetas, mensajes de error y navegación por teclado.

## Fuentes técnicas oficiales

Consultar estas referencias al implementar y registrar en el conector la versión utilizada:

- TTI Webhooks: <https://www.thethingsindustries.com/docs/integrations/webhooks/>
- Formato de datos TTI: <https://www.thethingsindustries.com/docs/integrations/data-formats/>
- MQTT de The Things Stack: <https://www.thethingsindustries.com/docs/integrations/other-integrations/mqtt/>
- Business Central `item`: <https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/api-reference/v2.0/resources/dynamics_item>
- Shopify Admin GraphQL `products`: <https://shopify.dev/docs/api/admin-graphql/latest/queries/products>
- Odoo External JSON-2 API: <https://www.odoo.com/documentation/19.0/developer/reference/external_api.html>
- SAP S/4HANA Product Master API: <https://api.sap.com/api/API_PRODUCT_SRV/overview>

Estas URLs son puntos de partida, no sustituyen revisar autenticación, paginación, límites, permisos, webhooks y política de versiones del proveedor antes de programar.

## Posicionamiento

- Modelar el cálculo como estrategias intercambiables; por ejemplo, proximidad, centroide ponderado, multilateración o posición asignada.
- No llamar “triangulación” a cualquier promedio de RSSI en el código. Nombrar el algoritmo real y documentar sus supuestos.
- Conservar las observaciones usadas por cada estimación para poder reproducir y explicar el resultado.
- Toda estimación debe incluir `calculated_at`, algoritmo y versión, coordenadas, sistema de referencia, confianza y precisión estimada cuando sea calculable.
- No mezclar coordenadas geográficas con coordenadas locales de un piso. Toda coordenada debe declarar su sistema de referencia.
- Los scanners y beacons fijos necesitan instalación con vigencia temporal. Reubicar un dispositivo cierra una instalación y crea otra; no altera el pasado.
- Aplicar ventanas temporales configurables y descartar o reducir el peso de mediciones obsoletas o anómalas.
- La intensidad RSSI no equivale directamente a distancia confiable. Cualquier conversión debe usar parámetros calibrables por dispositivo y entorno.
- Cuando la evidencia sea insuficiente, devolver una posición desconocida o de baja confianza en vez de fabricar precisión.
- El payload confirmado contiene como mínimo MAC y RSSI. El normalizador debe aceptar aliases documentados, pero el contrato interno siempre usa `transmitter_mac` normalizada y `rssi` entero en dBm.
- La solución matemática 2D y la regla operativa exigen al menos tres anclas activas, no colineales, con coordenadas conocidas en la misma ubicación. Cada instalación conserva RSSI de referencia a un metro y exponente de pérdida calibrables.
- Mantener por separado posición actual e historial. La vista actual puede ser una proyección regenerable desde los eventos.

## Planos y zonas

- Permitir planos raster (PNG, JPG, WEBP) y archivos PDF/DXF con vista previa raster para el editor web.
- Todo plano declara dimensiones reales en metros. No inferir escala sólo desde píxeles.
- Las zonas se guardan en coordenadas normalizadas para mantenerse alineadas al redimensionar la imagen.
- La primera herramienta geométrica es un rectángulo dibujado con puntero/mouse; el modelo conserva `shape` y `geometry` para polígonos futuros.
- Los beacons/scanners fijos se colocan como puntos sobre el mismo plano y sus coordenadas se convierten a metros.
- Clasificar una posición dentro de una zona en el servidor, no sólo visualmente en JavaScript.

## Datos, organización, identidad y seguridad

- La aplicación es multiempresa/multiproyecto con base de datos compartida. Toda entidad de negocio debe llevar `organization_id` y aplicar el alcance de `BelongsToOrganization`; las tablas de identidad usan membresías por organización.
- El rol efectivo pertenece a `organization_memberships`, no globalmente al usuario. Un mismo usuario puede tener roles diferentes en distintas organizaciones.
- El contexto activo se resuelve en middleware desde una membresía válida. Nunca aceptar un `organization_id` aportado por formularios o payloads como fuente de autorización.
- Validaciones `exists` y `unique`, route model binding, archivos privados, jobs, comandos, webhooks, conectores y tareas programadas deben mantener el mismo aislamiento tenant que las consultas web.
- El registro público permite crear una organización aislada con nombre, correo administrativo y contraseña. Debe conservar rate limiting, protección anti-bot, transacción atómica y rechazo de correos ya registrados. Las invitaciones se usan para incorporar cuentas a organizaciones existentes.
- Los usuarios inician sesión mediante correo y contraseña o mediante un botón de acceso con Microsoft.
- El login local debe usar hashing, sesiones, protección CSRF, regeneración de sesión al autenticar y rate limiting provistos por Laravel.
- La autenticación Microsoft debe usar OAuth 2.0/OpenID Connect mediante una integración mantenida para Laravel. Validar `state`, callback y correo/identificador del proveedor; no implementar el protocolo manualmente.
- Vincular la identidad Microsoft a un usuario mediante un identificador estable del proveedor. No confiar únicamente en el nombre visible y no fusionar cuentas automáticamente por correo sin una política explícita y segura.
- Definir autorización aun con una sola organización. Como mínimo, distinguir las acciones administrativas de la consulta normal del dashboard.
- Aplicar autorización del lado del servidor a cada consulta y mutación; nunca confiar en filtros del frontend.
- Usar UUID/ULID para identificadores expuestos públicamente salvo una razón documentada en contrario.
- Añadir restricciones de base de datos, índices y claves foráneas además de validación en PHP.
- Las migraciones deben ser reversibles cuando sea seguro y compatibles con datos existentes.
- No guardar credenciales en el repositorio. Añadir sólo nombres y valores ficticios a `.env.example`.
- Cifrar credenciales de conectores en reposo mediante las capacidades de Laravel y ocultarlas en serialización y logs.
- Evitar datos de ubicación sensibles en logs. Usar IDs de correlación para seguir una ingesta entre webhook, cola y cálculo.

## Convenciones de implementación

- Toda acción de GitHub debe fijarse a un SHA completo revisado; no usar tags flotantes en workflows de producción.
- Nunca usar `set -x`, PAT incrustados en URLs, descarga y ejecución por tubería, aceptación automática de claves SSH ni creación de `.env` durante despliegues.
- El despliegue debe ejecutar el mismo commit que superó CI, usar un Environment protegido y conservar fuera del repositorio secretos, archivos privados y respaldos.
- Un control técnico no debe describirse como “cumplimiento ISO” o aprobación de un cliente sin alcance, evidencia organizacional y validación formal del responsable autorizado.

- Mantener controladores pequeños: validar/autenticar, invocar un caso de uso y formar la respuesta.
- Usar Form Requests para entradas HTTP, Policies/Gates para autorización, Jobs para trabajo asíncrono y Events cuando exista desacoplamiento real.
- Encapsular transacciones en el servicio de aplicación que define la unidad de trabajo.
- Evitar lógica de negocio en controladores, vistas, modelos Eloquent con observers implícitos o jobs gigantes.
- Preferir tipos explícitos, `declare(strict_types=1)` en archivos nuevos y objetos de valor para unidades, coordenadas e identificadores relevantes.
- No agregar abstracciones genéricas sin al menos un caso de uso concreto. Los conectores y algoritmos sí requieren contratos porque se prevén múltiples implementaciones.
- Mantener las APIs versionadas bajo `/api/v1` cuando sean públicas o consumidas por dispositivos/proveedores.
- Documentar payloads de integración con ejemplos sanitizados y definir respuestas de error estables.

## Pruebas obligatorias

Cada cambio de negocio debe incluir pruebas proporcionales al riesgo:

- pruebas unitarias para algoritmos de posicionamiento, conversiones y objetos de valor;
- pruebas feature para endpoints, autenticación, autorización y validación;
- contract tests con fixtures sanitizados para cada conector y versión de payload;
- pruebas del registro de proveedores, configuración condicional, cifrado/ocultamiento de secretos y prueba de conexión;
- pruebas de idempotencia, eventos duplicados, llegada fuera de orden y reintentos de jobs;
- pruebas de autorización entre tipos de usuario;
- pruebas con reloj controlado para ventanas temporales e historial.

Para algoritmos espaciales incluir casos degenerados: una sola medición, sensores colineales, señales imposibles, coordenadas en pisos distintos y evidencia antigua. Comparar valores flotantes con tolerancia, no con igualdad exacta.

## Comandos de trabajo

Desde la raíz del repositorio:

```bash
composer setup
composer dev
composer test
php artisan test
./vendor/bin/pint --test
```

Antes de entregar un cambio:

1. Ejecutar las pruebas relacionadas y, cuando sea viable, toda la suite con `composer test`.
2. Ejecutar `./vendor/bin/pint --test` para PHP modificado.
3. Verificar manualmente que los assets estáticos bajo `public/css` y `public/js` carguen sin proceso de compilación.
4. Informar qué verificaciones se ejecutaron y cualquier limitación pendiente.

## Criterios de aceptación

Un cambio está terminado cuando:

- respeta los límites entre dominio, proveedor y presentación;
- es idempotente donde intervienen webhooks, sincronizaciones o colas;
- conserva trazabilidad de los datos externos y de las posiciones calculadas;
- aplica aislamiento, validación, autorización y manejo seguro de secretos;
- incluye migraciones, índices y pruebas necesarias;
- no degrada silenciosamente la precisión ni atribuye certeza falsa a una ubicación;
- actualiza la documentación de contratos o configuración afectada.

## Decisiones aún abiertas

No asumir respuestas para estos puntos sin una decisión explícita o un ADR:

- biblioteca de mapas y gráficos compatible con vistas Blade;
- orden de implementación después de SAP: Business Central, Shopify, Odoo y CSV;
- versiones y modalidades concretas de SAP S/4HANA que deben certificarse, además del conector inicial;
- modelo exacto y formato de uplink del tracker B1000;
- payloads, headers y autenticación concretos de los webhooks TTI;
- algoritmo inicial, calibración y precisión objetivo por entorno;
- escala esperada de activos, dispositivos, usuarios y mensajes por segundo;
- política de retención de payloads, observaciones e historial de posiciones;
- requisitos de tiempo real, alertas y operación offline.

Registrar decisiones arquitectónicas relevantes en `docs/adr/` cuando aparezcan. Cada ADR debe indicar contexto, decisión, alternativas y consecuencias.
