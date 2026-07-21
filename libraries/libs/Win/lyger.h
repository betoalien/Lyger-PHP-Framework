#ifndef LYGER_H
#define LYGER_H

#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

#define LYGER_ABI_VERSION 2u

/* Runtime limits: LYGER_BIND_ADDRESS, LYGER_MAX_BODY_BYTES,
 * LYGER_PHP_RESPONSE_TIMEOUT_MS. */

typedef char *(*lyger_request_callback)(const char *request_json);
typedef void (*lyger_release_callback)(char *response_json);

uint32_t lyger_abi_version(void);
char *lyger_core_version(void);
char *lyger_last_error(void);
uint32_t lyger_last_error_code(void);
char *lyger_abi_contract(void);

uint64_t lyger_db_query(const char *dsn, const char *query);
uint64_t lyger_db_query_v2(const char *dsn, const char *query, const char *bindings_json);
int32_t lyger_db_health(const char *dsn);
int32_t lyger_db_health_with_retry(const char *dsn, uint32_t attempts, uint64_t backoff_ms);
char *lyger_db_pool_metrics(void);
char *lyger_memory_metrics(void);
uint64_t lyger_db_transaction(const char *dsn, const char *statements_json);
char *lyger_jsonify_result(uint64_t handle);
char *lyger_result_chunk(uint64_t handle, uint64_t offset, uint32_t limit);
int32_t lyger_free_result(uint64_t handle);
uint64_t lyger_active_result_handles(void);
int32_t lyger_db_close_pools(void);
uint64_t lyger_create_benchmark_dataset(uint32_t rows);

char *lyger_hello_world(void);
double lyger_heavy_computation(uint64_t iterations);
char *lyger_system_info(void);

void lyger_cache_set(const char *key, const char *value);
int32_t lyger_cache_set_with_ttl(const char *key, const char *value, uint64_t ttl_seconds);
char *lyger_cache_get(const char *key);
int32_t lyger_cache_delete(const char *key);
void lyger_cache_clear(void);
uint64_t lyger_cache_size(void);

void lyger_free_string(char *pointer);
void lyger_free_engine(void *pointer);

void lyger_start_server(uint16_t port);
int32_t lyger_start_server_v2(
    uint16_t port,
    lyger_request_callback callback,
    lyger_release_callback release
);
int32_t lyger_start_server_v3(uint16_t port);
char *lyger_next_request(uint64_t timeout_ms);
int32_t lyger_send_response(uint64_t request_id, const char *response_json);
int32_t lyger_stop_server(void);
int32_t lyger_server_is_running(void);

#ifdef __cplusplus
}
#endif

#endif
