# Adaptación del modelo de memoria de PardoX para Lyger

La copia local de PardoX confirma que el problema de memoria ya fue atacado con un modelo matemático de presupuesto, no con un límite fijo.

## Principios reutilizables

### Presupuesto de memoria

PardoX calcula un presupuesto según RAM total:

- hasta 4 GB: 1 GB de presupuesto;
- hasta 8 GB: 2 GB;
- hasta 64 GB: 45% de RAM;
- más de 64 GB: 55% de RAM.

El override manual está limitado a 45% y aplica un piso de 1 GB cuando la máquina lo permite. Ese límite protege al sistema operativo de un proceso que intente consumir toda la memoria.

### Costo estimado de chunk

PardoX estima:

```text
estimated_chunk_ram_cost = io_chunk_size × 4
```

Después calcula la capacidad segura como presupuesto disponible dividido entre ese costo. En turbo usa 80% del presupuesto para buffers; en modo lite conserva una profundidad limitada por CPU.

### Backpressure

La capacidad calculada se usa para dimensionar un canal `bounded`, evitando que el productor siga acumulando bloques cuando el escritor no puede consumirlos.

### Streaming y zero-copy

El writer de PardoX evita un buffer combinado intermedio y escribe directamente al encoder Zstd. Los offsets variables se convierten a bytes mediante una vista controlada y las operaciones grandes se procesan bloque por bloque.

### Protección aritmética

El código usa `saturating_sub`, límites explícitos de chunk y validaciones de `chunk_size > 0` para impedir underflow y divisiones inválidas.

## Aplicación propuesta en Lyger

No se debe copiar el HyperBlockManager ni el formato PRDX dentro de `database.rs`. Lyger debe reutilizar el patrón en un módulo separado:

```text
src/memory_governor.rs
src/result_store.rs
src/streaming.rs
```

El governor de Lyger debe calcular:

```text
budget = min(ram_total × configured_ratio, hard_cap)
result_budget = budget × result_store_ratio
stream_chunk = clamp(target_bytes, minimum, maximum)
in_flight = budget / estimated_result_cost
```

## GAP-RUST-MEM-001 — Governor de memoria

1. Detectar memoria disponible de forma portable.
2. Permitir `LYGER_MEMORY_RATIO` con hard cap de 45%.
3. Definir piso y techo configurables.
4. Calcular capacidad con `checked_*`/`saturating_*`.
5. Exponer presupuesto y uso mediante métricas sin filtrar secretos.

## GAP-RUST-MEM-002 — Result store gobernado

1. Medir bytes aproximados de filas, columnas y strings.
2. Rechazar inserciones que excedan el presupuesto.
3. Evictar por TTL y presión de memoria.
4. Mantener `active_handles` y bytes usados.
5. Añadir pruebas concurrentes y de límite.

## GAP-RUST-MEM-003 — Streaming FFI

1. Añadir cursor/stream handle para no materializar millones de filas.
2. Entregar chunks con ownership explícito.
3. Liberar cada chunk desde PHP.
4. Aplicar backpressure cuando PHP no consume.
5. Validar un dataset grande con memoria acotada.

## GAP-RUST-MEM-004 — PardoX como motor analítico separado

La futura integración PardoX debe vivir detrás de un módulo/API independiente. Lyger debe orquestar el job y conservar los datos en el heap de PardoX; no debe convertir un dataset completo en `serde_json::Value` ni en arrays PHP.

## Criterio de terminado

El bloque de memoria no estará terminado por “no crashear” solamente. Debe demostrar:

- presupuesto calculado y documentado;
- límite duro reproducible;
- streaming sin materialización completa;
- backpressure;
- cero warnings y cero errores;
- prueba desde PHP y desde Dental Clinic;
- memoria máxima registrada en resultados crudos.
