#!/bin/bash
# Start SSH tunnel so PHP can reach MySQL on your server.
#
# ONE-TIME SETUP (no Terminal each time):
#   ./install-db-tunnel-autostart.sh
# That installs a background service that starts the tunnel on login.
#
# Manual start (if you did not install auto-start):
#   ./start-db-tunnel.sh

PORT=3307
REMOTE="root@138.197.27.95"
KEY="$HOME/.ssh/dbeaver_droplet_new"

if nc -z 127.0.0.1 "$PORT" 2>/dev/null; then
    echo "✓ Port $PORT is already open — tunnel looks active."
    echo "  Open: http://localhost/stories/test-db.php"
    echo "  Open: http://localhost/stories/index.php"
    read -r -p "Press Enter to close this window..."
    exit 0
fi

if [ ! -f "$KEY" ]; then
    echo "✗ SSH key not found: $KEY"
    read -r -p "Press Enter to close..."
    exit 1
fi

echo "=========================================="
echo "  Sparking Curiosity — database tunnel"
echo "=========================================="
echo ""
echo "  Tip: run ./install-db-tunnel-autostart.sh once"
echo "  so you never need this window again."
echo ""
echo "  KEEP THIS WINDOW OPEN while you use the site."
echo "  Closing it = website loses database connection."
echo ""
echo "  Starting tunnel: localhost:$PORT → server MySQL"
echo ""

exec ssh -i "$KEY" -o ServerAliveInterval=60 -L "${PORT}:127.0.0.1:3306" "$REMOTE"
