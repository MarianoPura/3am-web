<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Native PHP template renderer with layouts, sections and partials.
 *
 * No template engine. PHP is already a template language, and adding Twig or
 * Blade would mean a compile step, a cache directory to invalidate, and a
 * second syntax for the team to know.
 *
 * The cost is that escaping is manual — there is no auto-escaping safety net.
 * That trade is only acceptable because the rule is absolute and enforced in
 * review: **never echo a variable without an escaper.** Use e() for HTML text,
 * e_attr() inside an attribute, e_url() in a URL, e_js() in a script literal.
 * Picking the wrong one is the usual way an "escaped" template still has a hole.
 */
final class View
{
    /** @var array<string, string> Rendered named sections. */
    private array $sections = [];

    /** @var list<string> Open section stack, for nested @section handling. */
    private array $sectionStack = [];

    private ?string $layout = null;

    /** @var array<string, mixed> Data shared with every view (nav, settings). */
    private array $shared = [];

    public function __construct(
        private readonly string $viewPath,
    ) {
    }

    /** Make a value available to every template — site settings, nav, etc. */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render a template to a string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $content = $this->renderFile($template, $data);

        // A template that called $this->extend() defers to its layout, which is
        // rendered with the sections the child populated. Resolved iteratively
        // so a layout can itself extend another.
        while ($this->layout !== null) {
            $layout       = $this->layout;
            $this->layout = null;

            $this->sections['content'] ??= $content;
            $content = $this->renderFile($layout, $data);
        }

        return $content;
    }

    /**
     * Render a full page into an HTML Response.
     *
     * @param array<string, mixed> $data
     */
    public function response(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->render($template, $data), $status);
    }

    /**
     * Include a partial from inside a template.
     *
     * Partials get their own scope plus whatever is explicitly passed, so a
     * partial cannot silently depend on a variable that happens to exist in the
     * parent — which is what makes them safe to reuse.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->resolve($template);

        // Buffer depth is captured so that a throw mid-template can unwind
        // cleanly. Without this, an exception inside a view leaves a partial
        // page in the output buffer and the error page renders inside it.
        $depth = ob_get_level();
        ob_start();

        try {
            (function () use ($file, $data): void {
                extract($this->shared, EXTR_SKIP);
                extract($data, EXTR_SKIP);
                require $file;
            })();
        } catch (Throwable $e) {
            while (ob_get_level() > $depth) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';

        // realpath collapses any traversal, and the prefix check then confirms
        // the result is still inside the view directory. Template names come
        // from application code today, but a view name assembled from a request
        // parameter is exactly the kind of shortcut that shows up later.
        $real = realpath($file);
        $root = realpath($this->viewPath);

        if ($real === false || $root === false || !str_starts_with($real, $root)) {
            throw new RuntimeException(sprintf('View "%s" not found.', $template));
        }

        return $real;
    }

    // ─────────────────────────────────────────────────────────
    // Template-facing API
    // ─────────────────────────────────────────────────────────

    /** Declare the layout this template renders into. */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function start(string $section): void
    {
        $this->sectionStack[] = $section;
        ob_start();
    }

    public function end(): void
    {
        if ($this->sectionStack === []) {
            throw new RuntimeException('end() called with no open section.');
        }

        $section = array_pop($this->sectionStack);
        $this->sections[$section] = (string) ob_get_clean();
    }

    /** Output a section, with an optional fallback when the child set none. */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }
}
