<?php

declare(strict_types=1);

namespace WatchScraper\Database;

use PDO;
use WatchScraper\Support\Env;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = Env::string('DB_PATH', dirname(__DIR__, 2) . '/storage/database.sqlite');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA busy_timeout = 5000');

        return self::$pdo;
    }

    public static function migrate(): void
    {
        $lockPath = dirname(__DIR__, 2) . '/storage/migrate.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }

        $pdo = self::pdo();
        try {
            $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    url TEXT NOT NULL,
    category TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS scrape_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    source_count INTEGER NOT NULL DEFAULT 0,
    success_count INTEGER NOT NULL DEFAULT 0,
    failure_count INTEGER NOT NULL DEFAULT 0,
    total_products INTEGER NOT NULL DEFAULT 0,
    new_products INTEGER NOT NULL DEFAULT 0,
    duration_ms INTEGER,
    trigger_type TEXT NOT NULL DEFAULT 'http'
);
CREATE TABLE IF NOT EXISTS scrape_source_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scrape_run_id INTEGER NOT NULL REFERENCES scrape_runs(id) ON DELETE CASCADE,
    source_id INTEGER NOT NULL REFERENCES sources(id),
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    http_status INTEGER,
    product_count INTEGER NOT NULL DEFAULT 0,
    new_product_count INTEGER NOT NULL DEFAULT 0,
    duration_ms INTEGER,
    error_type TEXT,
    error_message TEXT
);
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_id INTEGER NOT NULL REFERENCES sources(id),
    external_id TEXT,
    identity_key TEXT NOT NULL,
    product_name TEXT,
    model_name TEXT,
    reference_number TEXT,
    price_text TEXT,
    price_jpy INTEGER,
    product_url TEXT,
    image_url TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    first_seen_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(source_id, identity_key)
);
CREATE TABLE IF NOT EXISTS scrape_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scrape_run_id INTEGER NOT NULL REFERENCES scrape_runs(id) ON DELETE CASCADE,
    scrape_source_run_id INTEGER NOT NULL REFERENCES scrape_source_runs(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    source_id INTEGER NOT NULL REFERENCES sources(id),
    product_name TEXT,
    model_name TEXT,
    reference_number TEXT,
    price_text TEXT,
    price_jpy INTEGER,
    product_url TEXT,
    image_url TEXT,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scrape_run_id INTEGER NOT NULL REFERENCES scrape_runs(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    item_count INTEGER NOT NULL,
    payload TEXT,
    status TEXT NOT NULL,
    http_status INTEGER,
    error_message TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_products_active ON products(is_active, last_seen_at);
CREATE INDEX IF NOT EXISTS idx_source_runs_status ON scrape_source_runs(source_id, status, finished_at);
CREATE INDEX IF NOT EXISTS idx_scrape_items_source_run ON scrape_items(scrape_source_run_id, source_id);
SQL);
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
