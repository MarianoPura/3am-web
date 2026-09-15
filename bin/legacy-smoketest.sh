#!/usr/bin/env bash
#
# 3AM — Legacy Application Smoke Test
# ===================================
# The safety net for the highest-risk constraint in this project:
# /armonyx, /trebl and every other existing app must keep working
# BYTE FOR BYTE after the new site is deployed at the apex.
#
# This turns "we think we didn't break anything" into a verifiable fact.
#
#   1. BEFORE any web-server change:   ./legacy-smoketest.sh baseline
#   2. AFTER the change:               ./legacy-smoketest.sh verify
#
# `verify` exits non-zero on ANY difference in status code, content type,
# body hash, redirect target, or a >2% swing in body size. Wire that exit
# code into the deploy script so a regression physically blocks the deploy.
#
# READ-ONLY: issues GET/HEAD requests only. Never sends POST, never writes
# outside its own snapshot directory.

set -uo pipefail

BASE_URL="${BASE_URL:-https://3ammediatech.com}"
SNAP_DIR="${SNAP_DIR:-$(dirname "$0")/../storage/smoketest}"
PATHS_FILE="${PATHS_FILE:-$(dirname "$0")/legacy-paths.txt}"
UA="3AM-SmokeTest/1.0"
TIMEOUT=20

mkdir -p "$SNAP_DIR"

# ─────────────────────────────────────────────────────────────
# Probe one URL. Emits a single TSV record.
# Body hash deliberately ignores volatile content (CSRF tokens, session ids,
# timestamps, nonces) so that a normal dynamic page still produces a stable
# fingerprint. Everything else must match exactly.
# ─────────────────────────────────────────────────────────────
probe() {
  local path="$1" url="${BASE_URL}${1}"
  local tmp; tmp=$(mktemp)

  local meta
  meta=$(curl -sS -o "$tmp" \
        -w '%{http_code}\t%{content_type}\t%{size_download}\t%{redirect_url}\t%{num_redirects}' \
        -A "$UA" --max-time "$TIMEOUT" "$url" 2>/dev/null) || meta=$'000\t-\t0\t-\t0'

  local hash
  hash=$(sed -E \
          -e 's/(csrf[_-]?token|_token|authenticity_token|nonce)["'\'']?\s*[:=]\s*["'\'']?[A-Za-z0-9+\/=_-]+/\1=VOLATILE/gI' \
          -e 's/[A-Fa-f0-9]{32,64}/VOLATILE_HASH/g' \
          -e 's/[0-9]{4}-[0-9]{2}-[0-9]{2}[T ][0-9]{2}:[0-9]{2}:[0-9]{2}/VOLATILE_TS/g' \
          -e 's/PHPSESSID=[^;"]+/VOLATILE_SID/g' \
          "$tmp" 2>/dev/null | sha256sum | cut -c1-16)

  rm -f "$tmp"
  printf '%s\t%s\t%s\n' "$path" "$meta" "$hash"
}

collect() {
  local out="$1" n=0
  : > "$out"
  echo "Probing ${BASE_URL} ..." >&2
  while IFS= read -r p; do
    [[ -z "$p" || "$p" == \#* ]] && continue
    probe "$p" >> "$out"
    n=$((n+1))
    printf '\r  %d paths' "$n" >&2
  done < "$PATHS_FILE"
  printf '\r  %d paths probed\n' "$n" >&2
}

# ─────────────────────────────────────────────────────────────
case "${1:-}" in

  baseline)
    [ -f "$PATHS_FILE" ] || { echo "ERROR: $PATHS_FILE not found. Populate it from the Phase 0 audit (§4)." >&2; exit 1; }
    collect "$SNAP_DIR/baseline.tsv"
    cp "$SNAP_DIR/baseline.tsv" "$SNAP_DIR/baseline-$(date +%Y%m%d-%H%M%S).tsv"
    echo
    echo "✓ Baseline captured: $SNAP_DIR/baseline.tsv"
    echo "  Do NOT change any web-server config until this file exists."
    ;;

  verify)
    [ -f "$SNAP_DIR/baseline.tsv" ] || { echo "ERROR: no baseline. Run 'baseline' first." >&2; exit 1; }
    collect "$SNAP_DIR/current.tsv"
    echo

    fail=0
    while IFS=$'\t' read -r path code ctype size redir nredir hash; do
      base=$(grep -P "^\Q$path\E\t" "$SNAP_DIR/baseline.tsv" 2>/dev/null | head -1)
      if [ -z "$base" ]; then
        echo "  [NEW]   $path  (not in baseline)"
        continue
      fi
      IFS=$'\t' read -r _ bcode bctype bsize bredir bnredir bhash <<< "$base"

      diffs=()
      [ "$code"  != "$bcode"  ] && diffs+=("status $bcode → $code")
      [ "$hash"  != "$bhash"  ] && diffs+=("body hash changed")
      [ "$redir" != "$bredir" ] && diffs+=("redirect $bredir → $redir")
      [ "${ctype%%;*}" != "${bctype%%;*}" ] && diffs+=("content-type $bctype → $ctype")

      # size drift > 2% (dynamic pages breathe a little; a real break is bigger)
      if [ "${bsize:-0}" -gt 0 ] 2>/dev/null; then
        delta=$(( (size - bsize) * 100 / bsize ))
        [ "${delta#-}" -gt 2 ] && diffs+=("size ${bsize}→${size} (${delta}%)")
      fi

      if [ ${#diffs[@]} -gt 0 ]; then
        echo "  ✗ REGRESSION  $path"
        printf '      - %s\n' "${diffs[@]}"
        fail=1
      fi
    done < "$SNAP_DIR/current.tsv"

    echo
    if [ "$fail" -eq 0 ]; then
      echo "✓ PASS — all legacy paths identical to baseline."
      exit 0
    else
      echo "✗ FAIL — legacy applications changed. DO NOT PROCEED."
      echo "  Roll back the web-server config and re-verify before continuing."
      exit 1
    fi
    ;;

  *)
    cat <<'USAGE'
3AM Legacy Application Smoke Test

  ./legacy-smoketest.sh baseline    Capture the "before" fingerprint.
                                    Run this BEFORE touching any config.

  ./legacy-smoketest.sh verify      Re-probe and diff against the baseline.
                                    Exits non-zero on any regression.

Environment:
  BASE_URL     default https://3ammediatech.com
  PATHS_FILE   default bin/legacy-paths.txt
  SNAP_DIR     default storage/smoketest

Populate legacy-paths.txt from section 4 of the Phase 0 server audit.
Include, for every legacy app: its root, its login page, a deep page,
a static asset, and a known 404 — the redirect and error behaviour is
exactly where a misconfigured fallthrough shows up first.
USAGE
    exit 1
    ;;
esac
