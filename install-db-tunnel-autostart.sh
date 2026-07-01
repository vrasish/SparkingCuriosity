#!/bin/bash
# Install LaunchAgent so the MySQL SSH tunnel (port 3307) starts automatically on login.
# Run once: ./install-db-tunnel-autostart.sh

set -euo pipefail

PORT=3307
REMOTE="root@138.197.27.95"
KEY="${HOME}/.ssh/dbeaver_droplet_new"
LABEL="com.sparkingcuriosity.db-tunnel"
PLIST_NAME="${LABEL}.plist"
LAUNCH_AGENTS="${HOME}/Library/LaunchAgents"
PLIST_PATH="${LAUNCH_AGENTS}/${PLIST_NAME}"

if [ ! -f "$KEY" ]; then
    echo "✗ SSH key not found: $KEY"
    echo "  Create the key or update KEY in this script."
    exit 1
fi

mkdir -p "$LAUNCH_AGENTS"

cat > "$PLIST_PATH" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>${LABEL}</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/bin/ssh</string>
        <string>-i</string>
        <string>${KEY}</string>
        <string>-N</string>
        <string>-o</string>
        <string>ServerAliveInterval=60</string>
        <string>-o</string>
        <string>ExitOnForwardFailure=yes</string>
        <string>-o</string>
        <string>StrictHostKeyChecking=accept-new</string>
        <string>-L</string>
        <string>${PORT}:127.0.0.1:3306</string>
        <string>${REMOTE}</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/tmp/sparkingcuriosity-db-tunnel.log</string>
    <key>StandardErrorPath</key>
    <string>/tmp/sparkingcuriosity-db-tunnel.err</string>
</dict>
</plist>
EOF

launchctl bootout "gui/$(id -u)/${LABEL}" 2>/dev/null || launchctl unload "$PLIST_PATH" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "$PLIST_PATH" 2>/dev/null || launchctl load "$PLIST_PATH"

sleep 2

if nc -z 127.0.0.1 "$PORT" 2>/dev/null; then
    echo "✓ Tunnel is running on 127.0.0.1:${PORT}"
    echo "  It will start automatically whenever you log in."
    echo "  Test: http://localhost/stories/test-db.php"
else
    echo "⚠ LaunchAgent installed but port ${PORT} is not open yet."
    echo "  Check: cat /tmp/sparkingcuriosity-db-tunnel.err"
    echo "  If your SSH key has a passphrase, add it to the macOS keychain:"
    echo "    ssh-add --apple-use-keychain ${KEY}"
    echo "  Then run this script again or log out and back in."
    exit 1
fi
