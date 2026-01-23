#!/bin/bash
# update.sh — Update Inventar Webapp

set -e
GREEN='\033[0;32m'
NC='\033[0m'

BASE_DIR="/var/www/html/inventar-webapp"

if [ "$EUID" -ne 0 ]; then 
  echo "Please run as root (sudo ./deploy/update.sh)"
  exit 1
fi

echo -e "${GREEN}>>> Pulling latest changes...${NC}"
# If the /var/www/html/inventar-webapp is a git repo, pull. 
# Or if we are updating from a local folder, copy?
# Let's assume the user git clones into /var/www/html/inventar-webapp OR
# the user works in /home/pi/inventar-webapp and uses 'deploy' script to copy.

# Check if we are inside the git repo
if [ -d ".git" ]; then
    git pull origin main
    
    echo -e "${GREEN}>>> Syncing to /var/www/html/inventar-webapp...${NC}"
    # If the live site is elsewhere, rsync it
    if [ "$(pwd)" != "$BASE_DIR" ]; then
        rsync -av --exclude='.git' --exclude='deploy/setup_pi.sh' ./ $BASE_DIR/
    fi
else
    echo "Not a git repository. Cannot auto-pull. Assuming manual file update."
fi

echo -e "${GREEN}>>> Fixing Permissions...${NC}"
chown -R www-data:www-data $BASE_DIR
chmod -R 775 $BASE_DIR
if [ -f "$BASE_DIR/inventar.db" ]; then
    chmod 664 $BASE_DIR/inventar.db
fi

echo -e "${GREEN}>>> Reloading Services...${NC}"
systemctl reload nginx
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
systemctl reload php$PHP_VER-fpm

echo -e "${GREEN}>>> Update Complete.${NC}"
