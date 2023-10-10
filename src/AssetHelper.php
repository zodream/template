<?php
declare(strict_types=1);
namespace Zodream\Template;

class AssetHelper {

    public static function clear(array $items): array {
        $data = [];
        foreach ($items as $key => $_) {
            $data[$key] = [];
        }
        return $data;
    }

    public static function isEmpty(array $items): bool {
        foreach ($items as $item) {
            if (!empty($item)) {
                return false;
            }
        }
        return true;
    }

    public static function merge(array $base, array $args, bool $append = true): array {
        foreach ($args as $key => $item) {
            if (empty($item)) {
                continue;
            }
            $base[$key] = static::mergeChild($base[$key] ?? [], $item, $append);
        }
        return $base;
    }

    protected static function mergeChild(array $base, array $args, bool $append): array {
        if (empty($base)) {
            return $args;
        }
        foreach ($args as $key => $item) {
            if (!isset($base[$key])) {
                $base[$key] = $item;
                continue;
            }
            if (!is_array($item) || !is_array($base[$key])) {
                continue;
            }
            $base[$key] = $append
                ? array_merge($base[$key], $item)
                : array_merge($item, $base[$key]);
        }
        return $base;
    }
}