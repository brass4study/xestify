<?php

declare(strict_types=1);

namespace Xestify\plugins;

use Xestify\exceptions\HookException;

/**
 * HookDispatcher — registers and executes named hooks.
 *
 * Hooks follow a before.../after... naming convention:
 *   - beforeXxx hooks: if a callback throws, the exception propagates and blocks the operation.
 *   - afterXxx  hooks: if a callback throws, a warning is logged and execution continues.
 *
 * Callbacks are executed in ascending priority order (lower number = first).
 * Multiple callbacks at the same priority are executed in registration order.
 */
class HookDispatcher
{
    /** @var array<string, list<array{callback: callable, priority: int}>> */
    private array $hooks = [];

    /**
     * Register a callback for a given hook name.
     *
     * @param string   $hook     Hook name, e.g. 'beforeSave', 'afterSave'.
     * @param callable $callback Callback receiving a context array (passed by reference).
     * @param int      $priority Lower value = executed first. Default 10.
     */
    public function register(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][] = ['callback' => $callback, 'priority' => $priority];
    }

    /**
     * Execute all callbacks registered for a hook in priority order.
     *
     * @param string $hook    Hook name.
     * @param array  $context Mutable context array passed to each callback.
     * @return array          The final context after all callbacks have run.
     * @throws HookException  If a beforeXxx callback throws (operation must be blocked).
     */
    public function execute(string $hook, array $context = []): array
    {
        if (!isset($this->hooks[$hook])) {
            return $context;
        }

        $sorted = $this->hooks[$hook];
        usort($sorted, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $isBefore = str_starts_with($hook, 'before');

        foreach ($sorted as $entry) {
            $context = $this->invokeCallback($entry['callback'], $context, $hook, $isBefore);
        }

        return $context;
    }

    /**
     * Apply a filter hook: callbacks receive and return an array value,
     * accumulating items across all registered callbacks.
     *
     * Each callback receives ($items, $args) and must return an array.
     * Callbacks are executed in ascending priority order.
     *
     * @param string $hook  Hook name, e.g. 'registerTabs', 'registerActions'.
     * @param array  $items Initial value (usually empty array).
     * @param array  $args  Extra read-only arguments passed to each callback.
     * @return array        The accumulated result after all callbacks.
     */
    public function applyFilter(string $hook, array $items = [], array $args = []): array
    {
        if (!isset($this->hooks[$hook])) {
            return $items;
        }

        $sorted = $this->hooks[$hook];
        usort($sorted, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        foreach ($sorted as $entry) {
            try {
                $result = ($entry['callback'])($items, $args);
                if (is_array($result)) {
                    $items = $result;
                }
            } catch (\Exception | \Error $e) {
                $this->logWarning($hook, $e->getMessage());
            }
        }

        return $items;
    }

    /**
     * Invoke a single callback, handling exceptions per hook convention.
     *
     * @throws HookException When a beforeXxx callback throws.
     */
    private function invokeCallback(callable $callback, array $context, string $hook, bool $isBefore): array
    {
        try {
            $result = $callback($context);
            return is_array($result) ? $result : $context;
        } catch (HookException $e) {
            if ($isBefore) {
                throw $e;
            }
            $this->logWarning($hook, $e->getMessage());
            return $context;
        } catch (\Exception | \Error $e) {
            if ($isBefore) {
                throw new HookException(
                    "Hook '{$hook}' blocked operation: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
            $this->logWarning($hook, $e->getMessage());
            return $context;
        }
    }

    /**
     * Emit a warning to STDERR for afterXxx hook failures (non-blocking).
     */
    private function logWarning(string $hook, string $message): void
    {
        $line = date('Y-m-d H:i:s') . " [WARN] Hook '{$hook}' failed (non-blocking): {$message}\n";
        fwrite(STDERR, $line);
    }
}

