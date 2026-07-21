# Lyger PHP Framework — Gaps ejecutables

Este documento convierte los cambios necesarios del framework PHP en unidades de trabajo verificables. La fuente canónica asumida es `v0.1/`; `Dental_Clinic`, `webapp_testing` y `testing_control/v0.1_Lyger` deben tratarse como consumidores o fixtures hasta definir una estrategia explícita de sincronización.

## Definición de terminado

Un GAP se considera terminado cuando:

1. El código PHP pasa lint y las pruebas relacionadas.
2. Existe una prueba desde la interfaz pública del framework.
3. Se conserva el fallback documentado cuando corresponda.
4. No se introducen estados persistentes entre requests sin ciclo de vida explícito.
5. La documentación describe el comportamiento real, no el esperado.

## GAP-PHP-001 — Línea base compilable y versión consistente

**Prioridad:** P0  
**Dependencias:** Ninguna  
**Archivos principales:** `v0.1/App/Controllers/Test.php`, `v0.1/Lyger/Console/Console.php`, `v0.1/Lyger/Admin/Admin.php`, `v0.1/Lyger/Database/Model.php`, `v0.1/composer.json`, `v0.1/README.md`

**Objetivo:** Eliminar errores de sintaxis y contradicciones de plataforma antes de modificar arquitectura.

**Tareas ejecutables:**

1. Corregir el error de sintaxis de `App/Controllers/Test.php`.
2. Resolver el conflicto de la clase/importación `Command` en `Console.php`.
3. Reemplazar interpolaciones obsoletas en `Admin.php`.
4. Declarar parámetros anulables de relaciones como `?string`.
5. Alinear PHP 8.0+/8.2+ entre README, Composer y CI.
6. Ejecutar lint sobre todos los archivos PHP canónicos.

**Criterios de aceptación:**

- Todos los PHP de `v0.1` pasan `php -l` sin fatal errors.
- Composer y README declaran la misma versión mínima de PHP.

## GAP-PHP-002 — `rawr serve` debe arrancar Always-Alive real

**Prioridad:** P0  
**Dependencias:** GAP-RUST-002, GAP-RUST-003  
**Archivos principales:** `v0.1/rawr`

**Objetivo:** Separar realmente `serve` de `serve:php`.

**Tareas ejecutables:**

1. Hacer que `serve` invoque `serveAlwaysAlive`.
2. Mantener `serve:php` como fallback mediante `php -S`.
3. Pasar y validar `--port` como entero entre 1 y 65535.
4. Reportar claramente motor, ABI, binario y puerto activos.
5. Propagar errores de arranque con exit code distinto de cero.

**Criterios de aceptación:**

- `php rawr serve` no inicia `PHP Development Server`.
- `php rawr serve:php` continúa funcionando.
- Una prueba confirma que `serve` escucha mediante Axum.

## GAP-PHP-003 — Integración del callback HTTP en Engine

**Prioridad:** P0  
**Dependencias:** GAP-RUST-001, GAP-RUST-002  
**Archivos principales:** `v0.1/Lyger/Core/Engine.php`, `v0.1/lyger.h`

**Objetivo:** Registrar desde PHP un callback estable que conecte Axum con el framework.

**Tareas ejecutables:**

1. Actualizar firmas FFI conforme al header canónico.
2. Verificar ABI antes de usar la librería.
3. Conservar la referencia PHP al callback durante toda la vida del servidor.
4. Convertir el payload Rust en `Request`.
5. Ejecutar el router persistente.
6. Convertir `Response` a la estructura esperada por Rust.
7. Liberar memoria y callback al detener el servidor.

**Criterios de aceptación:**

- `/api/hello` se procesa en el router PHP cuando el listener es Axum.
- Status, headers y body sobreviven el viaje completo.
- Cien requests consecutivos no provocan crash ni crecimiento sostenido de handles.

## GAP-PHP-004 — Request independiente de superglobales

**Prioridad:** P0  
**Dependencias:** GAP-PHP-003  
**Archivos principales:** `v0.1/Lyger/Http/Request.php`

**Objetivo:** Construir requests tanto desde PHP SAPI como desde payloads enviados por Rust.

**Tareas ejecutables:**

1. Añadir `Request::fromServerPayload(...)`.
2. Normalizar método, URI, query, headers, body e IP.
3. Parsear JSON y formularios de forma explícita.
4. Mantener `Request::capture()` para `serve:php`.
5. Añadir acceso consistente a body crudo, cookies y archivos cuando estén soportados.

**Criterios de aceptación:**

- La misma prueba de request pasa con SAPI y con payload Rust.
- GET query, POST JSON y headers personalizados conservan sus valores.

## GAP-PHP-005 — Response transportable por Rust

**Prioridad:** P0  
**Dependencias:** GAP-PHP-003  
**Archivos principales:** `v0.1/Lyger/Http/Response.php`

**Objetivo:** Separar la construcción de una respuesta de su envío mediante SAPI.

**Tareas ejecutables:**

1. Exponer todos los headers mediante `getHeaders()`.
2. Conservar `getStatusCode()` y `getContent()` como contrato de transporte.
3. Añadir normalización de múltiples headers y Content-Type.
4. Mantener `send()` únicamente como adaptador SAPI.
5. Definir respuesta para streams o archivos grandes.

**Criterios de aceptación:**

- Una respuesta 201 con headers personalizados llega intacta a través de Axum.
- JSON, HTML, texto, 404 y 500 funcionan en ambos servidores.

## GAP-PHP-006 — Rehacer ServerManager y ciclo de vida persistente

**Prioridad:** P0  
**Dependencias:** GAP-PHP-003, GAP-PHP-004, GAP-PHP-005  
**Archivos principales:** `v0.1/Lyger/Core/ServerManager.php`

**Objetivo:** Sustituir el loop simulado por administración real del worker persistente.

**Tareas ejecutables:**

1. Construir Container, Router y rutas una sola vez.
2. Registrar el callback en Engine.
3. Eliminar el loop `sleep(1)` que no recibe callbacks.
4. Manejar `SIGINT` y `SIGTERM` cuando la plataforma lo soporte.
5. Ejecutar hooks de inicio, request y apagado.
6. Restablecer estado scoped al terminar cada request.

**Criterios de aceptación:**

- El framework arranca una vez y atiende múltiples requests.
- Las rutas no se duplican.
- Ctrl+C detiene Rust y libera el puerto.

## GAP-PHP-007 — Scopes del Container y aislamiento entre requests

**Prioridad:** P0  
**Dependencias:** GAP-PHP-006  
**Archivos principales:** `v0.1/Lyger/Container/Container.php`

**Objetivo:** Evitar contaminación de estado en el worker Always-Alive.

**Tareas ejecutables:**

1. Definir lifetimes singleton, scoped y transient.
2. Añadir `beginRequestScope()` y `endRequestScope()`.
3. Registrar Request y contexto de autenticación como scoped.
4. Limpiar buffers, errores temporales y servicios scoped al terminar.
5. Detectar transacciones o recursos abiertos.

**Criterios de aceptación:**

- Datos del request A nunca aparecen en el request B.
- Singletons intencionales sí conservan estado.
- Una prueba de 1,000 requests no aumenta indefinidamente el número de servicios.

## GAP-PHP-008 — Drivers de base de datos intercambiables

**Prioridad:** P0  
**Dependencias:** GAP-RUST-005/006/007, GAP-RUST-009  
**Archivos principales:** `v0.1/Lyger/Database/`, `v0.1/Lyger/Core/Engine.php`

**Objetivo:** Desacoplar QueryBuilder de PDO y usar Rust como camino principal con fallback explícito.

**Tareas ejecutables:**

1. Crear una interfaz `DatabaseDriver`.
2. Implementar `RustDatabaseDriver`.
3. Implementar o conservar `PdoDatabaseDriver`.
4. Seleccionar driver mediante configuración y disponibilidad ABI.
5. Normalizar select, execute, insert ID, transacciones y errores.
6. Exponer qué driver está activo para diagnóstico.

**Criterios de aceptación:**

- La misma suite CRUD pasa con Rust y PDO.
- La caída de FFI activa fallback solo si está configurado.
- El README puede demostrar qué driver fue medido.

## GAP-PHP-009 — Corregir QueryBuilder

**Prioridad:** P0  
**Dependencias:** Puede iniciarse después de GAP-PHP-001  
**Archivos principales:** `v0.1/Lyger/Database/QueryBuilder.php`

**Objetivo:** Corregir errores funcionales y hacer el builder compatible con múltiples motores.

**Tareas ejecutables:**

1. Corregir el orden de bindings en UPDATE.
2. Construir correctamente AND/OR.
3. Validar tabla, columnas, operadores y dirección de orden.
4. Separar `insert(): bool` de `insertGetId()`.
5. Añadir transacciones.
6. Implementar gramáticas o capacidades por motor.
7. Probar límites, offsets, joins, NULL e IN vacío.

**Criterios de aceptación:**

- Pruebas unitarias cubren todas las ramas del SQL generado.
- Valores maliciosos se mantienen como bindings.
- PostgreSQL, MySQL y SQLite pasan el contrato común soportado.

## GAP-PHP-010 — Corregir persistencia de Model

**Prioridad:** P0  
**Dependencias:** GAP-PHP-009  
**Archivos principales:** `v0.1/Lyger/Database/Model.php`, relaciones

**Objetivo:** Garantizar IDs, timestamps, atributos y relaciones correctos.

**Tareas ejecutables:**

1. Usar `insertGetId()` y asignar el ID real.
2. Preservar primary keys no llamadas `id`.
3. Validar fillable/guarded y casts.
4. Probar create, update, delete y find.
5. Corregir relaciones y clases relacionadas faltantes o inconsistentes.
6. Definir explícitamente soft deletes si se anuncian.

**Criterios de aceptación:**

- `Model::create()` devuelve el ID real persistido.
- CRUD y relaciones pasan pruebas de integración.
- No se anuncian capacidades no implementadas.

## GAP-PHP-011 — Router correcto, compilable y persistente

**Prioridad:** P0  
**Dependencias:** GAP-PHP-001; se integra con GAP-PHP-006  
**Archivos principales:** `v0.1/Lyger/Routing/Router.php`

**Objetivo:** Asegurar que las rutas se carguen una vez y se midan realmente.

**Tareas ejecutables:**

1. Separar registro global de tabla compilada por Router.
2. Evitar duplicación al cargar archivos varias veces.
3. Soportar GET, POST, PUT, PATCH, DELETE, HEAD y OPTIONS según alcance.
4. Compilar parámetros y validar firmas de handlers.
5. Añadir manejo seguro de excepciones.
6. Corregir el benchmark para cargar las rutas en la instancia medida.

**Criterios de aceptación:**

- El benchmark hace match con la última de 50 rutas y ejecuta su handler.
- Parámetros y Request se inyectan correctamente.
- Las pruebas confirman 404 y conflicto de métodos.

## GAP-PHP-012 — Detección y fallback FFI confiables

**Prioridad:** P0  
**Dependencias:** GAP-RUST-001  
**Archivos principales:** `v0.1/Lyger/Core/Engine.php`, distribución de librerías

**Objetivo:** Evitar fatals cuando existe la extensión FFI pero está restringida o el binario es incompatible.

**Tareas ejecutables:**

1. Capturar errores de `FFI::cdef` durante inicialización.
2. Validar `ffi.enable`, plataforma, arquitectura y símbolos.
3. Verificar versión ABI.
4. Exponer estado y causa del fallback.
5. Evitar `shell_exec` frágil para detectar arquitectura cuando sea posible.
6. Diferenciar fallback permitido de configuración inválida fatal.

**Criterios de aceptación:**

- FFI deshabilitado no produce fatal si el fallback está permitido.
- Un binario x64 en ARM64 se rechaza con un mensaje accionable.

## GAP-PHP-013 — Suite end-to-end del framework

**Prioridad:** P0 transversal  
**Dependencias:** Se amplía con cada GAP  
**Archivos principales:** nuevo directorio `v0.1/tests/`

**Objetivo:** Validar Lyger como framework completo y no solo componentes aislados.

**Tareas ejecutables:**

1. Arrancar `rawr serve` en un puerto temporal.
2. Probar página raíz, hello, system, parámetros, POST JSON y 404.
3. Probar status, headers y body.
4. Probar múltiples requests y aislamiento.
5. Probar parada limpia.
6. Ejecutar variantes Rust y `serve:php`.

**Criterios de aceptación:**

- La suite falla si `serve` usa accidentalmente `php -S`.
- Todos los endpoints públicos pasan por el transporte esperado.
- No hay procesos o puertos residuales al finalizar.

## GAP-PHP-014 — Reconstruir benchmarks reproducibles

**Prioridad:** P1, bloquea claims públicos  
**Dependencias:** GAP-PHP-002 a GAP-PHP-013 y GAP-RUST-010/011  
**Archivos principales:** `v0.1/benchmark/`, `testing_control/benchmark/`

**Objetivo:** Sustituir simulaciones y microbenchmarks inválidos por mediciones equivalentes.

**Tareas ejecutables:**

1. Separar benchmarks internos, base de datos y HTTP.
2. Eliminar el cálculo de req/s basado en asignar `"Hello World"`.
3. Eliminar `$rustTime = $phpTime / 3`.
4. Eliminar filas de base simuladas de resultados publicables.
5. Ejecutar la misma carga y dataset en cada framework.
6. Añadir warm-up, repeticiones, mediana, p95, p99 y dispersión.
7. Medir CPU, memoria, errores y throughput.
8. Guardar entorno, commit SHA, configuración y resultados crudos.

**Criterios de aceptación:**

- Un tercero puede reproducir los resultados con comandos documentados.
- No existe ningún número hardcodeado, derivado o descrito como real si es proyectado.
- Los benchmarks HTTP atraviesan servidor, router, controlador y respuesta.

## GAP-PHP-015 — Metodología y README verificables

**Prioridad:** P1  
**Dependencias:** GAP-PHP-014  
**Archivos principales:** `v0.1/README.md`, nuevo `v0.1/docs/benchmarks.md`, `v0.1/docs/why-lyger.md`

**Objetivo:** Publicar únicamente capacidades y cifras demostradas.

**Tareas ejecutables:**

1. Crear metodología con hardware, versiones y comandos exactos.
2. Enlazar resultados crudos y commit medido.
3. Marcar cada capacidad como estable, experimental o planeada.
4. Retirar o reemplazar `139M req/s`, `313× DB` y `3× JSON` hasta validarlos.
5. Añadir una explicación breve de por qué existe Lyger.
6. Alinear README con el comportamiento real de `rawr serve`.

**Criterios de aceptación:**

- Cada cifra pública tiene metodología y evidencia enlazada.
- Un checkout del commit indicado reproduce el mismo orden de magnitud.

## GAP-PHP-016 — Fuente canónica y sincronización de copias

**Prioridad:** P1  
**Dependencias:** Ninguna  
**Archivos principales:** workspace raíz, CI y scripts de distribución

**Objetivo:** Evitar divergencia entre `v0.1`, `Dental_Clinic`, `webapp_testing` y `testing_control/v0.1_Lyger`.

**Tareas ejecutables:**

1. Declarar `v0.1` como fuente canónica o mover el framework a un package único.
2. Hacer que demos y benchmarks consuman el package por Composer/path repository.
3. Eliminar copias manuales del core cuando sea seguro.
4. Añadir una prueba que detecte versiones divergentes.

**Criterios de aceptación:**

- Un cambio del framework se realiza una sola vez.
- Demo y benchmarks prueban exactamente el mismo código publicado.

## Orden de ejecución recomendado

1. GAP-PHP-001
2. GAP-PHP-012 junto con GAP-RUST-001
3. GAP-PHP-011
4. GAP-PHP-002 a GAP-PHP-006 junto con GAP-RUST-002/003
5. GAP-PHP-007
6. GAP-PHP-009 y GAP-PHP-010
7. GAP-PHP-008 junto con los drivers Rust
8. GAP-PHP-013 de manera continua
9. GAP-PHP-016
10. GAP-PHP-014
11. GAP-PHP-015
