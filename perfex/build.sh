#!/usr/bin/env bash
# بيعمل dse_studio.zip جاهز للرفع على Perfex (Setup → Modules → Upload)
set -euo pipefail
cd "$(dirname "$0")/modules"
out="${1:-../dse_studio.zip}"
rm -f "$out"
zip -qr "$out" dse_studio -x '*.DS_Store'
echo "✔ $out"
