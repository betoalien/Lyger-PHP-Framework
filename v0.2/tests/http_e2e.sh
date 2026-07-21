#!/usr/bin/env bash
set -euo pipefail

port="${1:-18080}"
base="http://127.0.0.1:${port}"

health="$(curl --fail --silent --show-error --max-time 5 "${base}/api/health")"
[[ "${health}" == *'"status":"ok"'* ]]

info="$(curl --fail --silent --show-error --max-time 5 "${base}/api/info")"
[[ "${info}" == *'"mode":"always-alive"'* ]]

status="$(curl --silent --show-error --max-time 5 -o /tmp/lyger-404-body -w '%{http_code}' "${base}/missing")"
[[ "${status}" == "404" ]]
grep -q '"error":"Not Found"' /tmp/lyger-404-body
rm -f /tmp/lyger-404-body

echo "v0.2 HTTP E2E: PASS"
