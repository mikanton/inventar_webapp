<?php

class Router
{
    private $routes = [];
    private $currentRoute = null;

    public function add($method, $path, $callback)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['route'] ?? 'home';

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                $this->currentRoute = $path; // Store the matched route
                call_user_func($route['callback']);
                return;
            }
        }

        // Fallback for API
        if ($path === 'api') {
            $this->currentRoute = 'api'; // Store the matched route
            require __DIR__ . '/../api.php';
            return;
        }

        // 404
        http_response_code(404);
        echo "404 Not Found";
    }

    public function is($route)
    {
        return $this->currentRoute === $route;
    }
}
