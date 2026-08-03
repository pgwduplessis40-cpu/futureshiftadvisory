<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class DatabaseSchema
{
    /**
     * @var array<string, bool>
     */
    private array $tables = [];

    /**
     * @var array<string, bool>
     */
    private array $columns = [];

    public function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }

    public function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return $this->columns[$key] ??= Schema::hasColumn($table, $column);
    }
}
