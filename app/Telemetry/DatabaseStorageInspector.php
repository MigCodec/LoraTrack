<?php

declare(strict_types=1);

namespace App\Telemetry;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseStorageInspector
{
    public function inspect(): DatabaseStorageUsage
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->inspectMySql(),
            'sqlite' => $this->inspectSqlite(),
            default => throw new RuntimeException("No existe un medidor seguro de almacenamiento para {$driver}."),
        };
    }

    private function inspectMySql(): DatabaseStorageUsage
    {
        $database = (string) DB::connection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(data_length + index_length), 0) AS database_bytes
             FROM information_schema.tables WHERE table_schema = ?',
            [$database],
        );
        $dataDirectory = (string) (DB::selectOne('SELECT @@datadir AS path')->path ?? '');
        $freeBytes = $this->freeBytesForPath($dataDirectory);

        return $this->usage((int) ($row->database_bytes ?? 0), $freeBytes, 'mysql_datadir');
    }

    private function inspectSqlite(): DatabaseStorageUsage
    {
        $path = (string) config('database.connections.sqlite.database');
        if ($path === '' || $path === ':memory:' || ! is_file($path)) {
            throw new RuntimeException('SQLite no tiene un archivo medible en este entorno.');
        }

        return $this->usage((int) filesize($path), $this->freeBytesForPath(dirname($path)), 'sqlite_file');
    }

    protected function freeBytesForPath(string $path): int
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new RuntimeException('El volumen de la base de datos no es accesible desde la aplicación.');
        }
        $freeBytes = disk_free_space($resolved);
        if ($freeBytes === false) {
            throw new RuntimeException('No fue posible obtener el espacio libre del volumen de la base de datos.');
        }

        return (int) $freeBytes;
    }

    private function usage(int $databaseBytes, int $freeBytes, string $source): DatabaseStorageUsage
    {
        $measurableCapacity = $databaseBytes + $freeBytes;
        if ($measurableCapacity <= 0) {
            throw new RuntimeException('La capacidad medible de almacenamiento es inválida.');
        }

        return new DatabaseStorageUsage(
            databaseBytes: $databaseBytes,
            freeBytes: $freeBytes,
            utilizationPercent: round(($databaseBytes / $measurableCapacity) * 100, 2),
            source: $source,
        );
    }
}
