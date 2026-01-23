#!/bin/bash
# setup_pi.sh — Install Inventar Webapp on Raspberry Pi

set -e

# Color helpers
GREEN='\033[0;32m'
NC='\033[0m' # No Color

echo -e "${GREEN}>>> Inventar Webapp Setup for Raspberry Pi${NC}"

# Check for root
if [ "$EUID" -ne 0 ]; then 
  echo "Please run as root (sudo ./setup_pi.sh)"
  exit 1
fi

DEST_DIR="/var/www/html/inventar-webapp"
# Resolve directory where this script resides (deploy/)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
# Project Root is one level up
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${GREEN}>>> Updating packages...${NC}"
apt-get update

echo -e "${GREEN}>>> Installing Nginx, PHP, and SQLite...${NC}"
# Install Nginx and PHP ecosystem
apt-get install -y nginx php-fpm php-sqlite3 php-common php-mbstring php-xml unzip git

echo -e "${GREEN}>>> Configuring Nginx...${NC}"
# Copy config
# Copy config
cp "$SCRIPT_DIR/nginx.conf" /etc/nginx/sites-available/inventar
# Link new config
ln -sf /etc/nginx/sites-available/inventar /etc/nginx/sites-enabled/

echo -e "${GREEN}>>> Setting up Application Directory...${NC}"
# Create destination if not exists
mkdir -p $DEST_DIR

# Copy files (excluding .git and deploy/setup_pi.sh itself to be safe, but cp -r is easier)
# We copy from PROJECT_ROOT
echo "Copying files from $PROJECT_ROOT to $DEST_DIR ..."
# Use rsync if available for cleaner copy, else cp
if command -v rsync &> /dev/null; then
    rsync -av --exclude='.git' --exclude='deploy/setup_pi.sh' "$PROJECT_ROOT/" "$DEST_DIR/"
else
    cp -r "$PROJECT_ROOT/"* "$DEST_DIR/"
fi

echo -e "${GREEN}>>> Setting Permissions...${NC}"
# Ensure www-data owns the directory and specifically the database
chown -R www-data:www-data $DEST_DIR
chmod -R 775 $DEST_DIR
# Make sure database file is writable
if [ -f "$DEST_DIR/inventar.db" ]; then
    chmod 664 $DEST_DIR/inventar.db
fi
# Make sure directory is writable (for SQLite WAL/journal creation)
chmod 775 $DEST_DIR

echo -e "${GREEN}>>> Enabling PHP Opcache...${NC}"
# Simple sed to enable opcache in php.ini (usually in /etc/php/*/fpm/php.ini)
# We find the version dynamically
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
sed -i 's/;opcache.enable=1/opcache.enable=1/' /etc/php/$PHP_VER/fpm/php.ini
sed -i 's/;opcache.memory_consumption=128/opcache.memory_consumption=64/' /etc/php/$PHP_VER/fpm/php.ini

echo -e "${GREEN}>>> Restarting Services...${NC}"
systemctl restart php$PHP_VER-fpm
systemctl restart nginx

echo -e "${GREEN}>>> DONE!${NC}"
echo "--------------------------------------------------------"
IP=$(hostname -I | cut -d' ' -f1)
echo "App is accessible at: http://$IP:8081/"
echo "--------------------------------------------------------"
