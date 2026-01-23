#!/bin/bash
# Get local IP on Mac
IP=$(ipconfig getifaddr en0)
if [ -z "$IP" ]; then
    IP="localhost"
fi

echo "---------------------------------------------------"
echo "Inventar Webapp started!"
echo "Local:   http://localhost:8000"
echo "Network: http://$IP:8000"
echo "---------------------------------------------------"

php -S 0.0.0.0:8000
