<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseSyncService
{
    private const TABLES = [
        'helpdesk_users',
        'helpdesk_settings',
        'helpdesk_notification_settings',
        'helpdesk_accomplishments',
        'migrations',
    ];

    private const MAX_ROWS_PER_TABLE = 20000;

    public function preview(string $source): array
    {
        $target = $source === 'local' ? 'aiven' : 'local';
        $sourceConnection = $this->connectionName($source);
        $targetConnection = $this->connectionName($target);
        $tables = [];
        $fingerprint = hash_init('sha256');

        foreach (self::TABLES as $table) {
            $sourceMeta = $this->tableMeta($sourceConnection, $table);
            $targetMeta = $this->tableMeta($targetConnection, $table);
            if ($sourceMeta === null || $targetMeta === null) {
                $status = $sourceMeta === null ? 'missing_source' : 'missing_target';
                $tables[] = $this->emptyTablePreview($table, $status);
                hash_update($fingerprint, $table.':'.$status);
                continue;
            }

            if (
                $sourceMeta['columns'] !== $targetMeta['columns']
                || $sourceMeta['primary_keys'] !== $targetMeta['primary_keys']
            ) {
                $tables[] = $this->emptyTablePreview($table, 'schema_mismatch');
                hash_update($fingerprint, $table.':schema_mismatch:'.serialize($sourceMeta).':'.serialize($targetMeta));
                continue;
            }

            if ($sourceMeta['primary_keys'] === []) {
                $tables[] = $this->emptyTablePreview($table, 'no_primary_key');
                hash_update($fingerprint, $table.':no_primary_key');
                continue;
            }

            if (!$this->hasTransactionalStorage($targetConnection, $table)) {
                $tables[] = $this->emptyTablePreview($table, 'non_transactional_target');
                hash_update($fingerprint, $table.':non_transactional_target');
                continue;
            }

            $sourceRows = $this->rows($sourceConnection, $table, $sourceMeta['columns'], $sourceMeta['primary_keys']);
            $targetRows = $this->rows($targetConnection, $table, $targetMeta['columns'], $targetMeta['primary_keys']);
            $sourceHashes = $this->rowHashes($sourceRows, $sourceMeta['primary_keys']);
            $targetHashes = $this->rowHashes($targetRows, $targetMeta['primary_keys']);
            $newRows = count(array_diff_key($sourceHashes, $targetHashes));
            $sameRows = 0;
            $changedRows = 0;
            foreach (array_intersect_key($sourceHashes, $targetHashes) as $key => $hash) {
                if (hash_equals($hash, $targetHashes[$key])) {
                    $sameRows++;
                } else {
                    $changedRows++;
                }
            }

            $tables[] = [
                'name' => $table,
                'status' => 'ready',
                'source_rows' => count($sourceRows),
                'target_rows' => count($targetRows),
                'new_rows' => $newRows,
                'changed_rows' => $changedRows,
                'same_rows' => $sameRows,
                'target_only_rows' => count(array_diff_key($targetHashes, $sourceHashes)),
            ];
            hash_update(
                $fingerprint,
                $table.':'.serialize($sourceMeta).':'.hash('sha256', serialize($sourceHashes)).':'.
                serialize($targetMeta).':'.hash('sha256', serialize($targetHashes))
            );
        }

        return [
            'source' => $source,
            'target' => $target,
            'tables' => $tables,
            'fingerprint' => hash_final($fingerprint),
        ];
    }

    public function cloneDatabase(array $preview, ?int $requiredAdminId = null): array
    {
        $sourceConnection = $this->connectionName($preview['source']);
        $targetConnection = $this->connectionName($preview['target']);
        $source = DB::connection($sourceConnection);
        $target = DB::connection($targetConnection);
        $backupName = 'database-backup-'.$preview['target'].'-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(6)).'.dat.gz';

        try {
            $source->beginTransaction();
            $target->beginTransaction();
            $currentPreview = $this->preview($preview['source']);
            if (!hash_equals($preview['fingerprint'], $currentPreview['fingerprint'])) {
                throw new RuntimeException('One of the databases changed during preparation. Preview the operation again.');
            }

            $syncable = array_values(array_filter(
                $currentPreview['tables'],
                static fn (array $table): bool => $table['status'] === 'ready'
            ));
            if ($syncable === [] || count($syncable) !== count($currentPreview['tables'])) {
                throw new RuntimeException('Every managed table must have a matching schema and transactional storage on both databases before cloning.');
            }
            if ($preview['target'] === 'local') {
                if ($requiredAdminId === null || !$source->table('helpdesk_users')
                    ->where('id', $requiredAdminId)
                    ->where('role', 'admin')
                    ->where('is_active', 1)
                    ->exists()) {
                    throw new RuntimeException('Restore is blocked because the Aiven copy does not contain your active administrator account.');
                }
            }

            $targetRows = [];
            $sourceRows = [];
            foreach ($syncable as $table) {
                $meta = $this->tableMeta($targetConnection, $table['name']);
                $targetRows[$table['name']] = $this->rows($targetConnection, $table['name'], $meta['columns'], $meta['primary_keys']);
                $sourceRows[$table['name']] = $this->rows($sourceConnection, $table['name'], $meta['columns'], $meta['primary_keys']);
            }

            $backupContents = gzencode(serialize([
                'created_at' => now()->toIso8601String(),
                'connection' => $preview['target'],
                'tables' => $targetRows,
            ]), 6);
            if (!is_string($backupContents) || !Storage::disk('local')->put('database-backups/'.$backupName, $backupContents)) {
                throw new RuntimeException('Could not create the private pre-clone snapshot. No rows were changed.');
            }

            foreach (array_reverse($syncable) as $table) {
                $target->table($table['name'])->delete();
            }

            $written = 0;
            foreach ($syncable as $table) {
                $rows = $sourceRows[$table['name']];
                foreach (array_chunk($rows, 500) as $batch) {
                    if ($batch !== []) {
                        $target->table($table['name'])->insert($batch);
                    }
                    $written += count($batch);
                }
            }

            $source->commit();
            $target->commit();
        } catch (\Throwable $exception) {
            if ($target->transactionLevel() > 0) {
                $target->rollBack();
            }
            if ($source->transactionLevel() > 0) {
                $source->rollBack();
            }
            if (Storage::disk('local')->exists('database-backups/'.$backupName)) {
                Storage::disk('local')->delete('database-backups/'.$backupName);
            }
            throw $exception;
        }

        return [
            'rows_written' => $written,
            'tables_cloned' => count($syncable),
            'snapshot' => $backupName,
        ];
    }

    private function emptyTablePreview(string $table, string $status): array
    {
        return [
            'name' => $table,
            'status' => $status,
            'source_rows' => 0,
            'target_rows' => 0,
            'new_rows' => 0,
            'changed_rows' => 0,
            'same_rows' => 0,
            'target_only_rows' => 0,
        ];
    }

    private function connectionName(string $connection): string
    {
        return match ($connection) {
            'local' => 'sqlite',
            'aiven' => 'mysql',
            default => throw new RuntimeException('Invalid database connection selection.'),
        };
    }

    private function tableMeta(string $connectionName, string $table): ?array
    {
        $connection = DB::connection($connectionName);
        $columns = $connection->getSchemaBuilder()->getColumns($table);
        if ($columns === []) {
            return null;
        }

        $primaryKeys = [];
        foreach ($connection->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['primary'] ?? false) {
                $primaryKeys = $index['columns'];
                break;
            }
        }

        return [
            'columns' => array_column($columns, 'name'),
            'primary_keys' => $primaryKeys,
        ];
    }

    private function hasTransactionalStorage(string $connectionName, string $table): bool
    {
        $connection = DB::connection($connectionName);
        if ($connection->getDriverName() !== 'mysql') {
            return true;
        }

        $storage = $connection->selectOne(
            'SELECT ENGINE AS engine FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return isset($storage->engine) && strtolower((string) $storage->engine) === 'innodb';
    }

    private function rows(string $connection, string $table, array $columns, array $primaryKeys): array
    {
        if ($primaryKeys === []) {
            throw new RuntimeException("Table {$table} has no primary key and cannot be cloned safely.");
        }
        $query = DB::connection($connection)->table($table);
        foreach ($primaryKeys as $key) {
            $query->orderBy($key);
        }
        $rows = $query->limit(self::MAX_ROWS_PER_TABLE + 1)->get($columns);
        if ($rows->count() > self::MAX_ROWS_PER_TABLE) {
            throw new RuntimeException("Table {$table} exceeds the safe clone limit of ".self::MAX_ROWS_PER_TABLE.' rows.');
        }

        return array_map(static fn (object $row): array => (array) $row, $rows->all());
    }

    private function rowHashes(array $rows, array $primaryKeys): array
    {
        $hashes = [];
        foreach ($rows as $row) {
            $key = serialize(array_map(static fn (string $column): mixed => $row[$column], $primaryKeys));
            $normalized = [];
            foreach ($row as $column => $value) {
                $normalized[$column] = $value === null ? null : (string) $value;
            }
            $hashes[$key] = hash('sha256', serialize($normalized));
        }

        return $hashes;
    }
}
