#!/bin/bash

# 1. Generate SSL Certs if missing
if [ ! -f key.pem ] || [ ! -f cert.pem ]; then
    echo "Generating Self-Signed SSL Certificates..."
    openssl req -x509 -newkey rsa:2048 -keyout key.pem -out cert.pem -days 365 -nodes -subj "/C=DE/ST=Berlin/L=Berlin/O=Inventar/CN=localhost"
fi

# 2. Install http-proxy if missing
if [ ! -d "node_modules/http-proxy" ]; then
    echo "Installing http-proxy..."
    npm install http-proxy
fi

# 3. Get Local IP
IP=$(ipconfig getifaddr en0)
if [ -z "$IP" ]; then
    IP="localhost"
fi

# 4. Start PHP Server (Background)
echo "Starting PHP Server on port 8000..."
php -S 127.0.0.1:8000 > /dev/null 2>&1 &
PHP_PID=$!

# 5. Start Node Proxy
echo "---------------------------------------------------"
echo "SECURE SERVER STARTED!"
echo "---------------------------------------------------"
echo "Local:   https://localhost:8443"
echo "Mobile:  https://$IP:8443"
echo "---------------------------------------------------"
echo "IMPORTANT: On iPhone, click 'Advanced' -> 'Proceed' to accept the certificate."
echo "---------------------------------------------------"

# Trap to kill PHP when Node exits
trap "kill $PHP_PID" EXIT

node proxy.js
