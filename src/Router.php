<?php
declare(strict_types=1);

namespace TEC;

/** Associe « MÉTHODE /chemin/{id} » à une fonction. Les {id} sont passés à la fonction. */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function get(string $p, callable $h): void
    {
        $this->add('GET', $p, $h);
    }

    public function post(string $p, callable $h): void
    {
        $this->add('POST', $p, $h);
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$m, $regex, $handler]) {
            if ($m === $method && preg_match($regex, $path, $mm)) {
                $args = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler(...array_values($args));
                return;
            }
        }
        Http::abort(404, 'Cette page n\'existe pas.');
    }
}
