const http = require('http');
const https = require('https');
const httpProxy = require('http-proxy');
const fs = require('fs');

// Configuration
const TARGET = 'http://127.0.0.1:8000';
const PORT = 8443;

// SSL Certificates
const options = {
    key: fs.readFileSync('key.pem'),
    cert: fs.readFileSync('cert.pem'),
};

// Create Proxy
const proxy = httpProxy.createProxyServer({});

// Error handling
proxy.on('error', function (err, req, res) {
    console.error('Proxy Error:', err);
    if (res) {
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Proxy Error: ' + err.message);
    }
});

// Create HTTPS Server
const server = https.createServer(options, (req, res) => {
    proxy.web(req, res, { target: TARGET });
});

// Upgrade for WebSockets (if needed later)
server.on('upgrade', (req, socket, head) => {
    proxy.ws(req, socket, head, { target: TARGET });
});

console.log(`HTTPS Proxy running on https://0.0.0.0:${PORT}`);
console.log(`Forwarding to ${TARGET}`);

server.listen(PORT, '0.0.0.0');
