#!/bin/bash
# backup.sh — Backup Database

BACKUP_DIR="/var/backups/inventar"
DB_FILE="/var/www/html/inventar-webapp/inventar.db"
DATE=$(date +%Y-%m-%d_%H-%M-%S)

mkdir -p $BACKUP_DIR

if [ -f "$DB_FILE" ]; then
    echo "Backing up database..."
    sqlite3 "$DB_FILE" ".backup '$BACKUP_DIR/inventar_$DATE.db'"
    echo "Backup created at $BACKUP_DIR/inventar_$DATE.db"
    
    # Keep only last 30 days
    find $BACKUP_DIR -name "inventar_*.db" -mtime +30 -delete
else
    echo "Database file not found at $DB_FILE"
fi
