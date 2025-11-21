#!/bin/bash
# start_local.sh

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "PHP could not be found."
    echo "Please install it using Homebrew: brew install php"
    exit 1
fi

echo "Starting local server at http://localhost:8000"
echo "Press Ctrl+C to stop."

# Start PHP built-in server
# -S localhost:8000 : Start server on port 8000
# -t . : Serve from current directory
php -S localhost:8000
