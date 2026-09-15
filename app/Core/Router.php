<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Segment-based router.
 *
 * Routes are declared literally with typed placeholders — `/work/{slug}` — and
 * compiled to a regex once per request. No annotations, no attribute scanning,
 * no route cache to invalidate: routes/web.php is the complete and readable
 * list of every URL this application answers.
 *
 * Placeholder types constrain the match at the routing layer, which means a
 * controller receiving {id:int} has already been guaranteed a numeric value and
 * a garbage URL 404s instead of reaching a query.
 */
final class Router
{
    /** @var array<string, list<array{regex:string, params:list<string>, handler:mixed, name:?string, middleware:list<string>}>> */
    private array $routes = [];

    /** @var array<string, string> name => path template, for url generation */
    private array $named = [];

    /** Middleware applied to every route in the current group(). */
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    private const PATTERNS = [
        'int'  => '[0-9]+',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'year' => '[0-9]{4}',
        'any'  => '[^/]+',
    ];

    public function get(string $path, mixed $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Group routes behind a shared prefix and middleware stack.
     *
     * Used for the admin panel, so that auth and the IP allowlist are attached
     * to every admin route by construction. A new admin route cannot be added
     * without them, which is the point.
     */
    public function group(string $prefix, array $middleware, callable $routes): void
    {
        $previousPrefix     = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix     = $previousPrefix . '/' . trim($prefix, '/');
        $this->groupMiddleware = [...$previousMiddleware, ...$middleware];

        $routes($this);

        $this->groupPrefix     = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /** Name the most recently added route, for url() generation. */
    public function name(string $name): self
    {
        $method = array_key_last($this->routes);
        $index  = array_key_last($this->routes[$method]);

        $this->routes[$method][$index]['name'] = $name;
        $this->named[$name] = $this->routes[$method][$index]['template'];

        return $this;
    }

    private function add(string $method, string $path, mixed $handler): self
    {
        $template = $this->groupPrefix . '/' . trim($path, '/');
        $template = '/' . trim($template, '/');

        [$regex, $params] = $this->compile($template);

        $this->routes[$method][] = [
            'template'   => $template,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'name'       => null,
            'middleware' => $this->groupMiddleware,
        ];

        return $this;
    }

    /**
     * Compile `/work/{slug}` to a regex plus the ordered parameter names.
     *
     * @return array{0:string, 1:list<string>}
     */
    private function compile(string $template): array
    {
        $params = [];
        $regex  = '';
        $offset = 0;

        // Walk the placeholders in order. Everything between them is literal
        // and gets quoted; each placeholder becomes a capture group. Splitting
        // it this way keeps quoted text and pattern text from ever mixing,
        // which is where the subtle bugs in hand-written routers live.
        preg_match_all(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([a-z]+))?\}#',
            $template,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            [$placeholder, $position] = $match[0];

            $regex   .= preg_quote(substr($template, $offset, $position - $offset), '#');
            $offset   = $position + strlen($placeholder);

            $name = $match[1][0];
            $type = $match[2][0] ?? 'slug';

            if (!isset(self::PATTERNS[$type])) {
                throw new RuntimeException(
                    sprintf('Unknown route parameter type "%s" in "%s".', $type, $template)
                );
            }

            $params[] = $name;
            $regex   .= '(' . self::PATTERNS[$type] . ')';
        }

        $regex .= preg_quote(substr($template, $offset), '#');

        return ['#^' . $regex . '$#', $params];
    }

    /**
     * Match a request.
     *
     * @return array{handler:mixed, params:array<string,string>, middleware:list<string>}|null
     */
    public function match(Request $request): ?array
    {
        $path   = $request->path();
        $method = $request->method();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) === 1) {
                array_shift($matches);

                return [
                    'handler'    => $route['handler'],
                    'params'     => array_combine($route['params'], $matches) ?: [],
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return null;
    }

    /**
     * Was the path routable under a different method?
     *
     * Lets the front controller answer 405 instead of 404, which is the honest
     * status and saves a real debugging session when a form posts to a GET-only
     * route.
     */
    public function matchesOtherMethod(Request $request): bool
    {
        foreach ($this->routes as $method => $routes) {
            if ($method === $request->method()) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $request->path()) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generate a URL from a route name.
     *
     * Named routes mean a URL structure can be changed in routes/web.php
     * without hunting through templates for hard-coded paths.
     *
     * @param array<string, string|int> $params
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new RuntimeException(sprintf('Route "%s" is not defined.', $name));
        }

        $path = $this->named[$name];

        foreach ($params as $key => $value) {
            $path = preg_replace('#\{' . preg_quote((string) $key, '#') . '(?::[a-z]+)?\}#', (string) $value, $path) ?? $path;
        }

        if (str_contains($path, '{')) {
            throw new RuntimeException(sprintf('Missing parameters for route "%s".', $name));
        }

        return url($path);
    }
}
