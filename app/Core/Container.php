<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Service registry and config store.
 *
 * Deliberately not a dependency-injection container. There is no autowiring, no
 * reflection, no attribute scanning — services are registered explicitly in
 * bootstrap.php and fetched by class name. On a site of this size that is the
 * whole benefit of a container (one place to see how the application is wired)
 * without the cost (magic that makes a stack trace unreadable at 3am).
 */
final class Container
{
    private static ?self $instance = null;

    /** @var array<string, callable(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $resolved = [];

    /** @var array<string, mixed> Config, keyed by file: ['app' => [...]] */
    private array $config = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Test seam — lets a test swap the whole container and put it back. */
    public static function setInstance(?self $container): void
    {
        self::$instance = $container;
    }

    /**
     * Register a lazily-constructed singleton.
     *
     * @param callable(self): object $factory
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->resolved[$id]);
    }

    /** Register an already-constructed instance. */
    public function set(string $id, object $instance): void
    {
        $this->resolved[$id] = $instance;
    }

    /**
     * @template T of object
     * @param  class-string<T>|string $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->resolved[$id])) {
            /** @var T */
            return $this->resolved[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(sprintf('Service "%s" is not registered.', $id));
        }

        /** @var T */
        return $this->resolved[$id] = ($this->factories[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->resolved[$id]);
    }

    // ─────────────────────────────────────────────────────────
    // Config
    // ─────────────────────────────────────────────────────────

    /**
     * Load every config/*.php file, keyed by filename.
     *
     * Loaded once at boot and never re-read. Application code reads config(),
     * not env(), so that a missing environment variable fails here at startup
     * rather than randomly at the point of first use.
     */
    public function loadConfig(string $directory): void
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $this->config[basename($file, '.php')] = require $file;
        }
    }

    /** Dot-notation lookup: config('app.url'), config('media.disk'). */
    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
