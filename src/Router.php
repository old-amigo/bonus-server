<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer;

class Router
{
    /**
     * @var array<callable>
     */
    private array $handlers = [];

    private const METHOD_GET = 'GET';

    private const METHOD_POST = 'POST';

    /**
     * @param string $path
     * @param callable $handler
     * @return void
     */
    public function get(string $path, callable $handler): void
    {
        $this->addHandler(self::METHOD_GET, $path, $handler);
    }

    /**
     * @param string $path
     * @param callable $handler
     * @return void
     */
    public function post(string $path, callable $handler): void
    {
        $this->addHandler(self::METHOD_POST, $path, $handler);
    }

    /**
     * @param string $method
     * @param string $path
     * @param callable $handler
     * @return void
     */
    private function addHandler(string $method, string $path, callable $handler): void
    {
        $this->handlers[$method . $path] = [
            'path' => $path,
            'method' => $method,
            'handler' => $handler
        ];
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI']);
        $requestPath = $requestUri['path'];
        $method = $_SERVER['REQUEST_METHOD'];

        $callback = null;
        foreach ($this->handlers as $handler) {
            if ($handler['path'] === $requestPath && $handler['method'] === $method) {
                $callback = $handler['handler'];
            }
        }

        if (!$callback) {
            header("HTTP/1.0 404 Not Found");
            return;
        }

        $callback(array_merge($_GET, $_POST));
    }
}