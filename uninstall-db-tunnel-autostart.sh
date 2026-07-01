#!/bin/bash
# Remove auto-start for the database SSH tunnel.

LABEL="com.sparkingcuriosity.db-tunnel"
PLIST_PATH="${HOME}/Library/LaunchAgents/${LABEL}.plist"

launchctl bootout "gui/$(id -u)/${LABEL}" 2>/dev/null || launchctl unload "$PLIST_PATH" 2>/dev/null || true
rm -f "$PLIST_PATH"

echo "✓ Removed auto-start. Port 3307 will stay closed until you run start-db-tunnel.sh manually."
