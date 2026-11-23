<?php

class Hook
{

    const SIGNAL_STOP = '__hook_stop__';

    const SIGNAL_BREAK = '__hook_break__';

    private static array $actions = [];
    private static array $filters = [];
    private static array $owners = [];
    private static array $suspended = [];
    private static array $triggered = [];
    private static bool $halted = false;

    private static function owner(?string $owner): string
    {
        if (is_string($owner) && $owner !== '') return $owner;
        if (class_exists('Extension')) {
            $cur = Extension::current();
            if ($cur !== '') return $cur;
        }
        return '';
    }

    public static function addAction(string $hook, callable $callback, int $priority = 10, ?string $owner = null): void
    {
        $o = self::owner($owner);
        self::$actions[$hook][$priority][] = ['cb' => $callback, 'owner' => $o];
        if ($o !== '') self::$owners[$o][$hook . '|action'] = true;
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10, ?string $owner = null): void
    {
        $o = self::owner($owner);
        self::$filters[$hook][$priority][] = ['cb' => $callback, 'owner' => $o];
        if ($o !== '') self::$owners[$o][$hook . '|filter'] = true;
    }

    private static function isStop($value): bool
    {
        return $value === self::SIGNAL_STOP;
    }

    private static function isBreak($value): bool
    {
        return $value === self::SIGNAL_BREAK;
    }

    public static function doAction(string $hook, ...$args): bool
    {
        self::$triggered[$hook . '|action'] = (self::$triggered[$hook . '|action'] ?? 0) + 1;
        if (empty(self::$actions[$hook])) return true;
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $owner = $entry['owner'] ?? '';
                if ($owner !== '' && in_array($owner, self::$suspended, true)) continue;
                try {
                    $result = call_user_func_array($entry['cb'], $args);
                } catch (\Throwable $e) {
                    error_log("[hook:{$hook}] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    continue;
                }
                if (self::isStop($result)) return false;
                if (self::isBreak($result)) break;
            }
            if (self::$halted) return false;
        }
        return !self::$halted;
    }

    public static function applyFilters(string $hook, $value, ...$args)
    {
        self::$triggered[$hook . '|filter'] = (self::$triggered[$hook . '|filter'] ?? 0) + 1;
        if (empty(self::$filters[$hook])) return $value;
        ksort(self::$filters[$hook]);
        foreach (self::$filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $owner = $entry['owner'] ?? '';
                if ($owner !== '' && in_array($owner, self::$suspended, true)) continue;
                try {
                    $result = call_user_func($entry['cb'], $value, ...$args);
                } catch (\Throwable $e) {
                    error_log("[filter:{$hook}] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    continue;
                }
                if (self::isStop($result)) return $value;
                if (self::isBreak($result)) break;
                $value = $result;
            }
        }
        return $value;
    }

    public static function halt(string $output = ''): void
    {
        self::$halted = true;
        if ($output !== '') {
            echo $output;
        }
        exit;
    }

    public static function halted(): bool
    {
        return self::$halted;
    }

    public static function suspendOwner(string $owner): void
    {
        if ($owner === '' || in_array($owner, self::$suspended, true)) return;
        self::$suspended[] = $owner;
    }

    public static function resumeOwner(string $owner): void
    {
        $idx = array_search($owner, self::$suspended, true);
        if ($idx !== false) {
            unset(self::$suspended[$idx]);
            self::$suspended = array_values(self::$suspended);
        }
    }

    public static function removeOwner(string $owner): void
    {
        if ($owner === '') return;
        foreach (self::$actions as $hook => $priorities) {
            foreach ($priorities as $p => $list) {
                $keep = array_values(array_filter($list, function ($entry) use ($owner) {
                    return ($entry['owner'] ?? '') !== $owner;
                }));
                if ($keep) {
                    self::$actions[$hook][$p] = $keep;
                } else {
                    unset(self::$actions[$hook][$p]);
                }
            }
            if (empty(self::$actions[$hook])) unset(self::$actions[$hook]);
        }
        foreach (self::$filters as $hook => $priorities) {
            foreach ($priorities as $p => $list) {
                $keep = array_values(array_filter($list, function ($entry) use ($owner) {
                    return ($entry['owner'] ?? '') !== $owner;
                }));
                if ($keep) {
                    self::$filters[$hook][$p] = $keep;
                } else {
                    unset(self::$filters[$hook][$p]);
                }
            }
            if (empty(self::$filters[$hook])) unset(self::$filters[$hook]);
        }
        unset(self::$owners[$owner]);
    }

    public static function getOwnerHooks(string $owner): array
    {
        return array_keys(self::$owners[$owner] ?? []);
    }

    public static function getAllHooks(): array
    {
        $actions = [];
        foreach (self::$actions as $hook => $priorities) {
            $count = 0;
            foreach ($priorities as $list) $count += count($list);
            $actions[$hook] = $count;
        }
        $filters = [];
        foreach (self::$filters as $hook => $priorities) {
            $count = 0;
            foreach ($priorities as $list) $count += count($list);
            $filters[$hook] = $count;
        }
        return [
            'actions'   => array_keys($actions),
            'filters'   => array_keys($filters),
            'counts'    => ['actions' => $actions, 'filters' => $filters],
            'triggered' => self::$triggered,
            'owners'    => array_keys(self::$owners),
            'suspended' => self::$suspended,
        ];
    }

    public static function triggerCount(string $hook, string $type = 'action'): int
    {
        return (int)(self::$triggered[$hook . '|' . $type] ?? 0);
    }

    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
        self::$owners = [];
        self::$suspended = [];
        self::$triggered = [];
        self::$halted = false;
    }
}
