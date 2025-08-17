<?php
declare(strict_types=1);

if (!function_exists('app')) {
    function app(): App\Application {
        return App\Application::getInstance();
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('dd')) {
    function dd(...$vars): void {
        foreach ($vars as $var) {
            var_dump($var);
        }
        exit(1);
    }
}