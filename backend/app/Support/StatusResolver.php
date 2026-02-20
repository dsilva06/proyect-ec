<?php

namespace App\Support;

use App\Models\Status;

class StatusResolver
{
    protected static array $cache = [];

    public static function getId(string $module, string $code): ?int
    {
        $key = "{$module}.{$code}";
        if (! array_key_exists($key, static::$cache)) {
            static::$cache[$key] = Status::query()
                ->where('module', $module)
                ->where('code', $code)
                ->value('id');
        }

        return static::$cache[$key];
    }

    public static function getByModule(string $module)
    {
        return Status::query()
            ->where('module', $module)
            ->orderBy('sort_order')
            ->get();
    }
}
