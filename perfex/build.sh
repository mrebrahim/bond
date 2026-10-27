#!/usr/bin/env bash
# بيعمل zip جاهز للرفع على Perfex (Setup → Modules → Upload)
#   bash perfex/build.sh [out.zip] [module]     — الافتراضي dse_studio
set -euo pipefail
cd "$(dirname "$0")/modules"
module="${2:-dse_studio}"
out="${1:-../$module.zip}"
rm -f "$out"
zip -qr "$out" "$module" -x '*.DS_Store'
echo "✔ $out"
