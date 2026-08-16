<?php

declare(strict_types=1);

namespace WatchScraper\Repository;

use PDO;

final class SourceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function sync(array $siteConfigs): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO sources (name, url, category, enabled, created_at, updated_at)
             VALUES (:name, :url, :category, :enabled, :created_at, :updated_at)
             ON CONFLICT(name) DO UPDATE SET
                url = excluded.url,
                category = excluded.category,
                enabled = excluded.enabled,
                updated_at = excluded.updated_at'
        );
        foreach ($siteConfigs as $site) {
            $stmt->execute([
                'name' => $site['name'],
                'url' => $site['url'],
                'category' => $site['category'],
                'enabled' => !empty($site['enabled']) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function enabledWithConfig(array $siteConfigs): array
    {
        $this->sync($siteConfigs);
        $byName = [];
        foreach ($this->all() as $source) {
            $byName[$source['name']] = $source;
        }

        $enabled = [];
        foreach ($siteConfigs as $site) {
            if (empty($site['enabled']) || !isset($byName[$site['name']])) {
                continue;
            }
            $enabled[] = array_merge($site, ['id' => (int) $byName[$site['name']]['id']]);
        }
        return $enabled;
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM sources ORDER BY category, name')->fetchAll();
    }
}
