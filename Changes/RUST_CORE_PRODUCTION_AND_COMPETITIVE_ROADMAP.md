# Lyger Rust Core — producción y roadmap competitivo

Este documento define el trabajo pendiente del Rust core para que Lyger v0.2 pueda considerarse producción y establece la dirección de v0.3.

## Estado confirmado

El core ya implementa y valida PostgreSQL con `tokio-postgres`/`bb8`, MySQL con `mysql_async`, SQLite con `rusqlite`, bindings parametrizados, transacciones, result handles con TTL, ABI versionada, captura de panics, servidor Axum, queue bridge, cache compartido y CI multiplataforma. La integración PHP v0.2 usa Rust como driver predeterminado y PDO sólo como fallback.

## GAP-RUST-PROD-001 — TLS y seguridad

- Sustituir `NoTls` por TLS configurable para PostgreSQL y MySQL.
- Verificar certificados y hostname.
- Redactar credenciales en errores, logs y métricas.
- Añadir pruebas de conexión segura y certificados inválidos.

## GAP-RUST-PROD-002 — Handshake ABI obligatorio

- Consumir `lyger_abi_contract()` y validar ABI, versión semántica, arquitectura y símbolos desde PHP antes de cualquier llamada.
- Rechazar binarios incompatibles con mensajes accionables.
- Documentar ownership de cada string y handle.

## GAP-RUST-PROD-003 — Errores tipados

- Códigos numéricos estables.
- Categorías para database, network, timeout, serialization y FFI.
- Correlation ID por request.
- Último error sin sobrescrituras accidentales.

## GAP-RUST-PROD-004 — HTTP configurable

- Bind address, keep-alive, límites de conexiones, headers y body.
- Timeouts de lectura, respuesta y espera de PHP.
- Backpressure y graceful shutdown con timeout máximo.

## GAP-RUST-PROD-005 — Pools resilientes

- Health checks, reconexión, backoff e invalidación de conexiones rotas.
- DSN normalizado para evitar pools duplicados.
- Métricas y cierre seguro.

## GAP-RUST-PROD-006 — Result store con límite de memoria

- Medir bytes usados, eviction por memoria/TTL y streaming de resultados grandes.
- Cancelar consultas que excedan límites.

## GAP-RUST-PROD-007 — API SQL completa

- Separar `query`/`execute`.
- Begin/commit/rollback, savepoints, prepared statement cache, batch insert, cursores streaming, timeout y cancelación.

## GAP-RUST-PROD-008 — Compatibilidad de tipos

Completar contrato común para NULL, boolean, enteros, decimal, fechas, timestamps, UUID, JSON/JSONB, blobs, arrays PostgreSQL y JSON MySQL.

## GAP-RUST-PROD-009 — Observabilidad y concurrencia

- Métricas p50/p95/p99, requests activas, errores, pool y query.
- Tracing estructurado con secretos redactados.
- Pruebas de 1,000 requests concurrentes, cancelación, desconexiones y múltiples workers PHP.

## Ventajas competitivas objetivo para v0.2

I/O de base de datos nativo sin drivers PHP, pools persistentes, backpressure HTTP, cache compartido, zero-copy/streaming, métricas nativas y graceful reload.

## Roadmap v0.3 — PardoX y Big Data

La promesa de v0.3 es investigar y entregar una integración oficial entre Lyger y PardoX para analítica de datos a gran escala usando PHP como interfaz. Esta integración aún no existe en v0.2 y deberá definirse mediante un contrato verificable:

1. API de consultas, datasets y jobs.
2. Bindings FFI o protocolo estable Rust/PHP/PardoX.
3. Jobs asíncronos, streaming y paginación distribuida.
4. Seguridad, límites, secretos fuera de logs.
5. Pruebas con datasets grandes sin copiar todo al heap PHP.
6. Benchmarks reproducibles.

PardoX se documenta como objetivo de v0.3, no como funcionalidad actual.
