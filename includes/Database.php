<?php

class Database
{
    private static $pdo;

    public static function connect()
    {
        if (!self::$pdo) {
            try {
                self::$pdo = new PDO('sqlite:' . __DIR__ . '/../inventar.db');
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::initialize();
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    private static function initialize()
    {
        // Create tables if they don't exist
        $commands = [
            "CREATE TABLE IF NOT EXISTS locations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            )",
            // Inventory will be handled by migration if it lacks location_id
            "CREATE TABLE IF NOT EXISTS inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                qty INTEGER DEFAULT 0,
                location_id INTEGER DEFAULT 1,
                barcode TEXT,
                UNIQUE(name, location_id),
                FOREIGN KEY(location_id) REFERENCES locations(id)
            )",
            "CREATE TABLE IF NOT EXISTS requests (
                id TEXT PRIMARY KEY,
                location TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TEXT DEFAULT 'open',
                fulfilled_at DATETIME,
                fulfilled_by TEXT,
                deleted_at DATETIME
            )",
            "CREATE TABLE IF NOT EXISTS request_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id TEXT NOT NULL,
                name TEXT NOT NULL,
                qty INTEGER NOT NULL,
                FOREIGN KEY(request_id) REFERENCES requests(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT NOT NULL,
                item TEXT,
                value TEXT,
                client_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                location_id INTEGER
            )",
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($commands as $cmd) {
            // We use try-catch because some tables might already exist with different schema
            // and we will handle migration below
            try {
                self::$pdo->exec($cmd);
            } catch (Exception $e) {
            }
        }

        // Check if we need to migrate to multi-location
        self::checkSchemaMigration();

        // Check if migration from JSON is needed (if inventory is empty but json exists)
        $count = self::$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
        if ($count == 0 && file_exists(__DIR__ . '/../inventar.json')) {
            self::migrate();
        }

        // Create default admin if no users
        $userCount = self::$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCount == 0) {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = self::$pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('admin', ?, 'admin')");
            $stmt->execute([$hash]);
        }

        // Ensure default locations exist
        $defaults = ['Hauptlager', 'Küche', 'Keller', 'Garage', 'Dachboden'];
        $stmtLoc = self::$pdo->prepare("INSERT OR IGNORE INTO locations (name) VALUES (?)");
        foreach ($defaults as $loc) {
            $stmtLoc->execute([$loc]);
        }
    }

    private static function checkSchemaMigration()
    {
        // Check if inventory table has location_id
        try {
            $cols = self::$pdo->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('location_id', $cols)) {
                // Migration needed: Add location_id and update unique constraint
                self::$pdo->beginTransaction();

                // 1. Create locations if not exist (should be handled by init but let's ensure)
                self::$pdo->exec("CREATE TABLE IF NOT EXISTS locations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE)");
                self::$pdo->exec("INSERT OR IGNORE INTO locations (id, name) VALUES (1, 'Hauptlager')");

                // 2. Rename old inventory
                self::$pdo->exec("ALTER TABLE inventory RENAME TO inventory_old");

                // 3. Create new inventory
                self::$pdo->exec("CREATE TABLE inventory (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    qty INTEGER DEFAULT 0,
                    location_id INTEGER DEFAULT 1,
                    UNIQUE(name, location_id),
                    FOREIGN KEY(location_id) REFERENCES locations(id)
                )");

                // 4. Copy data (assign to location 1)
                self::$pdo->exec("INSERT INTO inventory (name, qty, location_id) SELECT name, qty, 1 FROM inventory_old");

                // 5. Drop old
                self::$pdo->exec("DROP TABLE inventory_old");

                self::$pdo->commit();
            }
        } catch (Exception $e) {
            if (self::$pdo->inTransaction())
                self::$pdo->rollBack();
            error_log("Migration failed: " . $e->getMessage());
        }

        // Check for barcode column
        try {
            $cols = self::$pdo->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('barcode', $cols)) {
                self::$pdo->exec("ALTER TABLE inventory ADD COLUMN barcode TEXT");
                self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_barcode ON inventory(barcode)");
            } else {
                // Ensure index exists even if column was already there
                self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_barcode ON inventory(barcode)");
            }
        } catch (Exception $e) {
            error_log("Barcode migration failed: " . $e->getMessage());
        }

        // Check for role column in users
        try {
            $cols = self::$pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('role', $cols)) {
                self::$pdo->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
                // Promote admin user if exists
                self::$pdo->exec("UPDATE users SET role = 'admin' WHERE username = 'admin'");
            }
        } catch (Exception $e) {
            error_log("Role migration failed: " . $e->getMessage());
        }
    }

    private static function migrate()
    {
        // Migrate Inventory
        $invJson = @file_get_contents(__DIR__ . '/../inventar.json');
        if ($invJson) {
            $inv = json_decode($invJson, true);
            if (is_array($inv)) {
                // Default to location 1
                $stmt = self::$pdo->prepare("INSERT OR IGNORE INTO inventory (name, qty, location_id) VALUES (?, ?, 1)");
                foreach ($inv as $name => $qty) {
                    // Handle both map format {"name": qty} and list format [{"name":"...", "qty":...}]
                    if (is_array($qty) && isset($qty['name'])) {
                        $stmt->execute([$qty['name'], intval($qty['qty'] ?? 0)]);
                    } else {
                        $stmt->execute([$name, intval($qty)]);
                    }
                }
            }
        }

        // Migrate Requests (No change needed for requests table itself yet, but maybe location column?)
        // The requests table has a 'location' text column which is the destination name.
        // We can keep it as text or link it to location_id. For now, keep as text to allow flexible delivery points.
        $reqJson = @file_get_contents(__DIR__ . '/../requests.json');
        if ($reqJson) {
            $reqs = json_decode($reqJson, true);
            if (is_array($reqs)) {
                $stmtReq = self::$pdo->prepare("INSERT OR IGNORE INTO requests (id, location, created_at, status, fulfilled_at, fulfilled_by, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtItem = self::$pdo->prepare("INSERT INTO request_items (request_id, name, qty) VALUES (?, ?, ?)");

                foreach ($reqs as $r) {
                    $stmtReq->execute([
                        $r['id'],
                        $r['location'],
                        $r['created'] ?? date('c'),
                        $r['status'] ?? 'open',
                        $r['fulfilled_at'] ?? null,
                        $r['fulfilled_by'] ?? null,
                        $r['deleted_at'] ?? null
                    ]);

                    if (isset($r['items']) && is_array($r['items'])) {
                        foreach ($r['items'] as $item) {
                            $stmtItem->execute([$r['id'], $item['name'], $item['qty']]);
                        }
                    }
                }
            }
        }

        // Migrate Logs (Optional, but good for history)
        $logJson = @file_get_contents(__DIR__ . '/../log.json');
        if ($logJson) {
            $logs = json_decode($logJson, true);
            if (is_array($logs)) {
                $stmtLog = self::$pdo->prepare("INSERT INTO logs (action, item, value, client_id, created_at) VALUES (?, ?, ?, ?, ?)");
                // Limit to last 1000 to avoid huge DB on start
                $logs = array_slice($logs, -1000);
                foreach ($logs as $l) {
                    $val = is_array($l['value']) ? json_encode($l['value']) : $l['value'];
                    $stmtLog->execute([
                        $l['action'],
                        $l['item'],
                        $val,
                        $l['client'] ?? null,
                        $l['time'] ?? date('c')
                    ]);
                }
            }
        }
    }
}
