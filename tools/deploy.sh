#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# tools/deploy.sh — upload a GroveMinder site over SFTP.
#
# Called by the deploy workflows: this repo's demo, and every theme repo (see
# README → "Making a theme"). It is product code: a site or theme sets the
# values below and never edits this file.
#
# Run it from the root of a groveminder checkout. Settings come from the
# environment:
#   DEPLOY_HOST      server name, e.g. example.com                    (required)
#   DEPLOY_DEST      folder on the server, e.g. /home/me/example.com  (required)
#   DEPLOY_MODE      "site" (default) or "demo"
#   THEME_DIR        folder holding the site's own files: site-config.js,
#                    images and .github/known_hosts. Default: this folder.
#   THEME_FILES      extra files from THEME_DIR to upload, space separated.
#                    The heroImage named in site-config.js is added for you.
#   SFTP_USER        SFTP login                                       (required)
#   SSH_PRIVATE_KEY  deploy key; SSHPASS is an optional password fallback
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

fail() { echo "::error::$*"; exit 1; }

: "${DEPLOY_HOST:?Set DEPLOY_HOST}" "${DEPLOY_DEST:?Set DEPLOY_DEST}" "${SFTP_USER:?Set SFTP_USER}"
MODE="${DEPLOY_MODE:-site}"
THEME_DIR="${THEME_DIR:-.}"
SSH_PRIVATE_KEY="${SSH_PRIVATE_KEY:-}"
SSHPASS="${SSHPASS:-}"

# The product. Every site gets these exact files.
PAGES=(site-core.js demo-firebase.js index.html admin.html email-templates.json)
PHP=(hp-lib.php mailer.php cal-push.php ical-proxy.php)
# groveminder-secrets/calendar.php (server-side calendar logins) is never uploaded: it is made by hand on the host.

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"; rm -f ~/.ssh/deploy_key' EXIT
BATCH="$WORK/sftp-batch"

put() { echo "put \"$1\" \"$DEPLOY_DEST/$2\"" >> "$BATCH"; }

echo "-mkdir \"$DEPLOY_DEST\"" > "$BATCH"

case "$MODE" in
  demo)
    # The demo: site-config.demo.js goes up as site-config.js, and
    # demo-firebase.js keeps everything in the visitor's browser. The PHP
    # endpoints aren't needed, so they are removed from the server rather
    # than left reachable.
    CONFIG=site-config.demo.js
    KNOWN_HOSTS=.github/known_hosts
    ;;
  site)
    CONFIG="$THEME_DIR/site-config.js"
    KNOWN_HOSTS="$THEME_DIR/.github/known_hosts"
    ;;
  *)
    fail "DEPLOY_MODE must be 'site' or 'demo', not '$MODE'"
    ;;
esac
[ -f "$CONFIG" ]      || fail "Missing $CONFIG"
[ -f "$KNOWN_HOSTS" ] || fail "Missing $KNOWN_HOSTS (the server's pinned host keys)"

put "$CONFIG" site-config.js
for f in "${PAGES[@]}"; do put "$f" "$f"; done
for f in "${PHP[@]}"; do
  if [ "$MODE" = demo ]; then echo "-rm \"$DEPLOY_DEST/$f\"" >> "$BATCH"; else put "$f" "$f"; fi
done

# The site's own extra files (images). A theme adds files; it never replaces
# a product file, so a name that clashes with one is refused.
HERO="$(sed -n 's/^[[:space:]]*heroImage:[[:space:]]*"\([^"]*\)".*/\1/p' "$CONFIG" | head -n 1)"
SENT=" "
for f in $HERO ${THEME_FILES:-}; do
  case "$SENT" in *" $f "*) continue ;; esac
  case "$f" in
    */*|.*) fail "Theme file '$f' must be a plain file name in the theme's root" ;;
    calendar.php*) fail "Theme file '$f' looks like the server's calendar secrets; those are made by hand on the host" ;;
  esac
  for p in site-config.js "${PAGES[@]}" "${PHP[@]}"; do
    [ "$f" != "$p" ] || fail "Theme file '$f' would replace a product file"
  done
  [ -f "$THEME_DIR/$f" ] || fail "Theme file '$f' not found in $THEME_DIR"
  put "$THEME_DIR/$f" "$f"
  SENT="$SENT$f "
done

# The server's host keys are pinned in .github/known_hosts
SSH_OPTS=(-o "UserKnownHostsFile=$KNOWN_HOSTS" -o StrictHostKeyChecking=yes -o ConnectTimeout=20)

# Signs in with SSH_PRIVATE_KEY when it is set. If a password is also set, it
# is only tried when the key is refused, which lets you add the key without a
# broken deploy in between. See README → "Deploy key".
if [ -n "$SSH_PRIVATE_KEY" ]; then
  mkdir -p ~/.ssh
  install -m 600 /dev/null ~/.ssh/deploy_key
  printf '%s\n' "$SSH_PRIVATE_KEY" > ~/.ssh/deploy_key
  SSH_OPTS+=(-i ~/.ssh/deploy_key -o IdentitiesOnly=yes)

  # Try the key on its own (no password) so the log shows whether it works
  echo pwd > "$WORK/key-check"
  if sftp "${SSH_OPTS[@]}" -o BatchMode=yes -o PasswordAuthentication=no -b "$WORK/key-check" "$SFTP_USER@$DEPLOY_HOST" > /dev/null 2>&1; then
    echo "Deploy key: accepted by the server"
  elif [ -n "$SSHPASS" ]; then
    echo "::warning::Deploy key was refused; falling back to SFTP_PASSWORD"
  else
    fail "Deploy key was refused and no SFTP_PASSWORD fallback is set"
  fi
fi

if [ -n "$SSHPASS" ]; then
  export SSHPASS
  sudo apt-get install -y sshpass > /dev/null 2>&1
  sshpass -e sftp "${SSH_OPTS[@]}" "$SFTP_USER@$DEPLOY_HOST" < "$BATCH"
else
  sftp "${SSH_OPTS[@]}" -b "$BATCH" "$SFTP_USER@$DEPLOY_HOST"
fi

echo "Deployed ($MODE) to $DEPLOY_HOST"
