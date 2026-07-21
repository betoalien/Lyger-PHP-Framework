# Lyger PHP Framework v0.2 — GAPs posteriores al streaming Rust

El core Rust v0.2 tiene cerrados los GAP-RUST-001 a GAP-RUST-014. Estos GAPs describen el trabajo pendiente del consumidor PHP y deben validarse sobre `v0.2` y `Dental_Clinic`.

## GAP-PHP-020 — Exponer streaming de resultados

**Prioridad:** P0 · **Dependencia:** GAP-RUST-014

1. Usar `Engine::resultChunk()` para consultas grandes en lugar de materializar todo el resultado.
2. Añadir un iterador/paginador público que avance por `offset` y respete un límite configurable.
3. Liberar siempre el handle con `freeResult()` mediante `try/finally`.
4. Documentar que el límite válido es 1–10,000 filas.

**Aceptación:** prueba SQLite en framework y Dental Clinic procesa múltiples chunks, conserva orden y no deja handles activos.

## GAP-PHP-021 — Gobernador de memoria y backpressure

**Prioridad:** P0 · **Dependencia:** GAP-RUST-014

1. Leer `memoryMetrics()` antes de iniciar exportaciones o respuestas grandes.
2. Configurar `LYGER_MEMORY_BUDGET_BYTES` por entorno y rechazar trabajos que excedan el presupuesto.
3. Convertir errores de límite Rust en una excepción PHP diagnosticable sin filtrar secretos.
4. Añadir prueba de presupuesto bajo y recuperación después de liberar el resultado.

**Aceptación:** el proceso permanece acotado, reporta `used_bytes`/`budget_bytes` y libera memoria al cerrar cada handle.

## GAP-PHP-022 — Contrato FFI y compatibilidad de librería

**Prioridad:** P0

1. Verificar ABI y capacidades antes de llamar streaming o métricas.
2. Detectar librerías sin estos símbolos y usar el camino JSON completo solo como fallback explícito.
3. Registrar versión de core, ABI y driver activo en diagnóstico.

**Aceptación:** v0.2 funciona con la librería nueva y falla de forma clara con una librería incompatible.

## GAP-PHP-023 — Validación de producción en Dental Clinic

**Prioridad:** P1 · **Dependencias:** GAP-PHP-020/021/022

1. Sincronizar `Engine.php` y la librería generada en Dental Clinic.
2. Ejecutar CRUD, HTTP, health, streaming y memoria contra SQLite, PostgreSQL y MySQL disponibles.
3. Registrar resultados reales, tiempos y fallos en el changelog de v0.2.

**Aceptación:** todos los contratos pasan sin warnings ni errores y la documentación refleja únicamente resultados reproducidos.
