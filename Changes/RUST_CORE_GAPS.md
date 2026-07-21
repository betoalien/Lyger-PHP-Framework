# Lyger Rust Core — Gaps ejecutables

Este documento convierte las diferencias entre la arquitectura publicada y el estado actual del Rust Core en unidades de trabajo verificables. Los GAPs están ordenados por dependencia; no deben usarse cifras de rendimiento del README hasta completar los GAPs de implementación y validación correspondientes.

## Definición de terminado

Un GAP se considera terminado cuando:

1. El código compila en modo `release` sin warnings nuevos.
2. Las pruebas automatizadas relacionadas pasan.
3. La frontera FFI no permite que un panic cruce hacia PHP.
4. La funcionalidad se verifica desde PHP, no únicamente desde una prueba Rust.
5. Se documentan firmas ABI, ownership de memoria y comandos de reproducción.

## GAP-RUST-001 — Versionar y endurecer la ABI FFI

**Prioridad:** P0  
**Dependencias:** Ninguna  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, `lyger_framework_rust-/Cargo.toml`, headers FFI distribuidos

**Objetivo:** Establecer un contrato seguro y versionado entre los binarios Rust y el framework PHP.

**Tareas ejecutables:**

1. Exponer `lyger_abi_version() -> u32` y una versión semántica del core.
2. Definir tipos C con tamaños explícitos para IDs, status codes y longitudes.
3. Envolver todas las funciones `extern "C"` con captura de panics.
4. Sustituir `unwrap()` y `expect()` alcanzables desde FFI por errores controlados.
5. Agregar una API para consultar el último error sin transferir ownership ambiguo.
6. Documentar para cada función quién asigna y quién libera strings, handles y estructuras.
7. Generar o mantener un único header canónico compatible con PHP FFI.

**Criterios de aceptación:**

- PHP detecta y rechaza una ABI incompatible antes de invocar otras funciones.
- Punteros nulos, UTF-8 inválido y handles desconocidos regresan errores sin abortar el proceso.
- Una prueba de panic intencional no termina el worker PHP.

## GAP-RUST-002 — Contrato HTTP Rust → PHP → Rust

**Prioridad:** P0  
**Dependencias:** GAP-RUST-001  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, header FFI

**Objetivo:** Definir e implementar el callback que permita a Axum delegar cada request al router PHP persistente.

**Tareas ejecutables:**

1. Definir estructuras FFI para request y response.
2. Incluir método, URI, query string, headers, body e IP del cliente.
3. Registrar un callback PHP con lifetime estable durante toda la ejecución del servidor.
4. Recibir status, headers y body producidos por PHP.
5. Convertir la respuesta PHP en una respuesta Axum válida.
6. Establecer límites configurables para body y headers.
7. Definir comportamiento para timeout, callback nulo y error PHP.

**Criterios de aceptación:**

- `GET /api/hello` entra por Axum, se resuelve en PHP y vuelve al cliente.
- Un POST JSON conserva método, headers y body sin alteraciones.
- PHP puede producir correctamente respuestas 200, 404 y 500.
- El fallback de Axum ya no devuelve una respuesta simulada.

## GAP-RUST-003 — Servidor Axum persistente y apagado limpio

**Prioridad:** P0  
**Dependencias:** GAP-RUST-002  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`

**Objetivo:** Convertir `lyger_start_server` y `lyger_stop_server` en un ciclo de vida real y controlable.

**Tareas ejecutables:**

1. Reutilizar un runtime Tokio administrado en lugar de crear runtimes inconexos.
2. Conservar el handle del thread o task del servidor.
3. Implementar graceful shutdown mediante `CancellationToken`, `watch` u `oneshot`.
4. Reportar errores de bind y arranque a PHP.
5. Evitar estados donde `SERVER_RUNNING` no coincida con el listener real.
6. Liberar callback, listener y recursos al detenerse.

**Criterios de aceptación:**

- El arranque falla de forma controlada cuando el puerto está ocupado.
- `lyger_stop_server()` libera el puerto y termina todas las tasks.
- Arrancar, detener y volver a arrancar funciona en el mismo proceso de prueba.

## GAP-RUST-004 — Result Store robusto para handles opacos

**Prioridad:** P0  
**Dependencias:** GAP-RUST-001  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`

**Objetivo:** Hacer seguro y observable el almacenamiento de resultados que PHP referencia mediante IDs opacos.

**Tareas ejecutables:**

1. Renombrar conceptos `ptr` a `handle` cuando el valor sea un `u64`.
2. Usar un contador atómico resistente a colisiones.
3. Representar filas, metadata, filas afectadas, último ID y errores.
4. Detectar handles inválidos y double-free.
5. Añadir TTL o recolección de resultados abandonados.
6. Añadir límites de memoria y número de handles.
7. Exponer métricas de handles activos y memoria estimada.

**Criterios de aceptación:**

- Crear, serializar y liberar un handle no deja entradas residuales.
- Liberar dos veces no produce panic.
- Los límites impiden crecimiento ilimitado del proceso.
- Pruebas concurrentes no generan IDs duplicados.

## GAP-RUST-005 — Driver PostgreSQL real

**Prioridad:** P0  
**Dependencias:** GAP-RUST-004  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, `lyger_framework_rust-/Cargo.toml`

**Objetivo:** Reemplazar la fila simulada de PostgreSQL por consultas reales con `tokio-postgres`.

**Tareas ejecutables:**

1. Validar y parsear DSNs PostgreSQL estándar.
2. Abrir conexiones reales y conducir la task de conexión.
3. Ejecutar SELECT y escrituras.
4. Convertir tipos SQL comunes a valores serializables.
5. Devolver columnas, filas, filas afectadas y errores.
6. Añadir prepared statements y bindings tipados.
7. Cubrir transacciones y rollback.

**Criterios de aceptación:**

- Una prueba de integración crea una tabla temporal y ejecuta CRUD completo.
- El SQL recibido se ejecuta realmente y los errores llegan a PHP.
- No existe código `Sample Data` en el camino de producción.

## GAP-RUST-006 — Driver MySQL real

**Prioridad:** P0  
**Dependencias:** GAP-RUST-004  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, `lyger_framework_rust-/Cargo.toml`

**Objetivo:** Reemplazar la fila simulada de MySQL por operaciones reales con `mysql_async`.

**Tareas y criterios de aceptación:** Iguales al GAP-RUST-005, adaptados a DSN, tipos, placeholders, último insert ID y transacciones MySQL.

## GAP-RUST-007 — Driver SQLite real

**Prioridad:** P0  
**Dependencias:** GAP-RUST-004  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, `lyger_framework_rust-/Cargo.toml`

**Objetivo:** Proporcionar un camino Rust verificable para SQLite en lugar de devolver una fila simulada.

**Tareas ejecutables:**

1. Seleccionar y documentar `rusqlite`, `libsql` u otra implementación.
2. Soportar archivos y base en memoria.
3. Ejecutar CRUD, bindings y transacciones.
4. Convertir correctamente NULL, integer, real, text y blob.
5. Documentar el modelo de concurrencia de SQLite.

**Criterios de aceptación:**

- CRUD completo funciona contra un archivo temporal y `:memory:`.
- Los resultados coinciden con una consulta PDO equivalente.

## GAP-RUST-008 — Pools persistentes por DSN

**Prioridad:** P1  
**Dependencias:** GAP-RUST-005, GAP-RUST-006; aplica según driver  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, `lyger_framework_rust-/Cargo.toml`

**Objetivo:** Evitar crear una conexión por consulta y habilitar concurrencia real.

**Tareas ejecutables:**

1. Mantener pools compartidos indexados por DSN normalizado.
2. Configurar mínimo, máximo, timeout y health check.
3. Evitar almacenar credenciales en logs o errores públicos.
4. Añadir invalidación y cierre de pools.
5. Medir espera de pool y tiempo de consulta por separado.

**Criterios de aceptación:**

- Dos consultas consecutivas reutilizan conexión.
- Una prueba concurrente no supera el máximo configurado.
- Caída y recuperación de la base no requieren reiniciar PHP.

## GAP-RUST-009 — Bindings SQL tipados y API de ejecución

**Prioridad:** P0  
**Dependencias:** GAP-RUST-005, GAP-RUST-006, GAP-RUST-007  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`, header FFI

**Objetivo:** Eliminar interpolación de valores y ofrecer una API común para SELECT, escrituras y transacciones.

**Tareas ejecutables:**

1. Aceptar bindings serializados con tipo explícito.
2. Implementar `query`, `execute`, `begin`, `commit` y `rollback`.
3. Normalizar respuestas entre motores.
4. Probar strings, números, booleanos, NULL, fechas y blobs.

**Criterios de aceptación:**

- Valores maliciosos permanecen como datos y no alteran el SQL.
- Los tres motores pasan el mismo contrato de pruebas.

## GAP-RUST-010 — Serialización JSON medible y equivalente

**Prioridad:** P1  
**Dependencias:** GAP-RUST-004  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`

**Objetivo:** Permitir una comparación real entre `json_encode` y `serde_json` usando el mismo dataset.

**Tareas ejecutables:**

1. Crear una API que serialice un handle con un dataset conocido.
2. Separar tiempo de construcción, FFI, serialización y copia final.
3. Verificar igualdad semántica del JSON producido.
4. Ejecutar warm-up y múltiples muestras.

**Criterios de aceptación:**

- PHP y Rust serializan los mismos 1,000 objetos.
- El benchmark no deriva resultados mediante divisiones o estimaciones.
- Se conservan resultados crudos de cada muestra.

## GAP-RUST-011 — Cómputo equivalente PHP/Rust

**Prioridad:** P1  
**Dependencias:** GAP-RUST-001  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`

**Objetivo:** Hacer comparable la prueba de cómputo usada en el README.

**Tareas ejecutables:**

1. Implementar exactamente `sqrt(i) * sin(i)` en Rust.
2. Devolver el acumulado final.
3. Verificar tolerancia numérica contra PHP.
4. Medir builds `release` con warm-up, mediana y dispersión.

**Criterios de aceptación:**

- Ambos caminos ejecutan la misma fórmula y número de iteraciones.
- La diferencia de resultado queda dentro de una tolerancia documentada.

## GAP-RUST-012 — Caché compartido, TTL y concurrencia

**Prioridad:** P1  
**Dependencias:** GAP-RUST-001  
**Archivos principales:** `lyger_framework_rust-/src/lib.rs`

**Objetivo:** Sustituir el caché `thread_local` por un almacén compartido consistente.

**Tareas ejecutables:**

1. Usar `DashMap` o `RwLock<HashMap<...>>`.
2. Implementar TTL, expiración y limpieza.
3. Configurar capacidad máxima.
4. Probar lectura y escritura desde múltiples threads Tokio.

**Criterios de aceptación:**

- Un valor escrito desde un thread puede leerse desde otro.
- Los valores expirados dejan de estar disponibles y se liberan.

## GAP-RUST-013 — Suite automatizada y matriz de binarios

**Prioridad:** P0 transversal  
**Dependencias:** Se amplía con cada GAP  
**Archivos principales:** `lyger_framework_rust-/tests/`, CI, scripts de release

**Objetivo:** Impedir que fuente, header y binarios distribuidos diverjan.

**Tareas ejecutables:**

1. Añadir unit tests e integration tests por subsistema.
2. Ejecutar `cargo fmt --check`, `cargo clippy` y `cargo test`.
3. Construir binarios Linux, macOS ARM64/x64 y Windows x64.
4. Verificar símbolos y ABI en cada artefacto.
5. Publicar checksum, versión ABI y commit SHA junto al binario.

**Criterios de aceptación:**

- Cada binario publicado se puede asociar inequívocamente a una versión del código.
- PHP ejecuta un smoke test contra cada artefacto soportado.

## Orden de ejecución recomendado

1. GAP-RUST-001
2. GAP-RUST-002
3. GAP-RUST-003
4. GAP-RUST-004
5. GAP-RUST-007 como primer driver verificable
6. GAP-RUST-005 y GAP-RUST-006
7. GAP-RUST-009
8. GAP-RUST-008
9. GAP-RUST-010 y GAP-RUST-011
10. GAP-RUST-012
11. GAP-RUST-013 de manera continua durante todos los pasos
