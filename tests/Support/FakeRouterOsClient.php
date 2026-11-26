<?php

namespace Tests\Support;

use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\RouterOsClient;
use Throwable;

/**
 * Router MikroTik palsu di memori: menyimpan baris per menu (/ppp/secret, /ip/hotspot/user, ...)
 * dan menerapkan print/add/set/remove agar test memeriksa keadaan akhir router, bukan urutan panggilan.
 */
class FakeRouterOsClient implements RouterOsClient
{
    /** @var list<array{path: string, attributes: array<string, string|int>, where: array<string, string|int>}> */
    public array $commands = [];

    /** @var array<string, list<array<string, string>>> */
    public array $tables = [];

    /**
     * Jawaban tetap per perintah (mis. /interface/monitor-traffic) untuk perintah yang bukan print/add/set/remove.
     *
     * @var array<string, list<array<string, string>>>
     */
    public array $responses = [];

    public ?Throwable $failWith = null;

    private int $nextId = 1;

    public function command(string $path, array $attributes = [], array $where = []): array
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->commands[] = ['path' => $path, 'attributes' => $attributes, 'where' => $where];

        if (isset($this->responses[$path])) {
            return $this->responses[$path];
        }

        $menu = substr($path, 0, strrpos($path, '/'));
        $action = substr($path, strrpos($path, '/') + 1);
        $rows = $this->tables[$menu] ?? [];

        if ($path === '/system/identity/print') {
            return [['name' => 'FakeRouter']];
        }

        switch ($action) {
            case 'print':
                return array_values(array_filter($rows, function (array $row) use ($where): bool {
                    foreach ($where as $key => $value) {
                        if (($row[$key] ?? null) !== (string) $value) {
                            return false;
                        }
                    }

                    return true;
                }));
            case 'add':
                $this->tables[$menu][] = ['.id' => '*'.$this->nextId++, ...array_map('strval', $attributes)];

                return [];
            case 'set':
                // Menu singleton (mis. /interface/l2tp-server/server) di-set tanpa .id.
                if (! isset($attributes['.id'])) {
                    $this->tables[$menu][0] = [...($rows[0] ?? []), ...array_map('strval', $attributes)];

                    return [];
                }

                foreach ($rows as $index => $row) {
                    if ($row['.id'] === $attributes['.id']) {
                        $this->tables[$menu][$index] = [...$row, ...array_map('strval', $attributes)];

                        return [];
                    }
                }

                throw new MikrotikCommandException("no such item {$attributes['.id']}");
            case 'remove':
                $this->tables[$menu] = array_values(array_filter($rows, fn (array $row): bool => $row['.id'] !== $attributes['.id']));

                return [];
        }

        return [];
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function seed(string $menu, array $attributes): void
    {
        $this->tables[$menu][] = ['.id' => '*'.$this->nextId++, ...$attributes];
    }

    /**
     * @return list<array<string, string>>
     */
    public function rows(string $menu): array
    {
        return $this->tables[$menu] ?? [];
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_column($this->commands, 'path');
    }
}
