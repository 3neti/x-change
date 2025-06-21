<?php

namespace LBHurtado\OmniChannel\Services;

use LBHurtado\OmniChannel\Middlewares\SMSMiddlewareInterface;
use Closure;

class SMSRouterService
{
    /**
     * Registered routes for SMS patterns.
     * @var array<string, mixed>
     */
    protected array $routes = [];

    /**
     * Middleware stack for each route.
     * @var array<string, array>
     */
    protected array $middlewares = [];

    /**
     * Register an SMS command pattern with its handler and middleware.
     *
     * @param string $pattern The pattern defining the SMS command structure.
     * @param mixed $handler The handler (callable or class name).
     * @param array $middlewares Array of middleware classes.
     *
     * @throws \InvalidArgumentException If middleware does not implement the required interface.
     */
    public function register(string $pattern, $handler, array $middlewares = []): void
    {
        foreach ($middlewares as $middleware) {
            if (!is_subclass_of($middleware, SMSMiddlewareInterface::class)) {
                throw new \InvalidArgumentException("Middleware must implement SMSMiddlewareInterface.");
            }
        }

        $this->routes[$pattern] = $handler;
        $this->middlewares[$pattern] = $middlewares;
    }

    /**
     * Handle an incoming SMS message.
     *
     * @param string $message The received SMS message.
     * @param string $from The sender's phone number.
     * @param string $to The recipient's phone number.
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(string $message, string $from, string $to)
    {
        foreach ($this->routes as $pattern => $handler) {
            $regex = $this->convertPatternToRegex($pattern);

            if (preg_match($regex, $message, $initialMatches)) {
                // Build a final callback that will re-match against any
                // middleware-mutated $message before invoking the handler.
                $final = function (string $msg, string $from, string $to) use ($regex, $handler) {
                    // re-run the pattern match
                    preg_match($regex, $msg, $matches);
                    array_shift($matches); // drop the full match
                    return $this->executeHandler($handler, $matches, $from, $to);
                };

                return $this->applyMiddleware($pattern, $message, $from, $to, $final);
            }
        }

        return response()->json(['message' => 'Unknown command. Please try again.'], 404);
    }
//    public function handle(string $message, string $from, string $to)
//    {
//        foreach ($this->routes as $pattern => $handler) {
//            $regex = $this->convertPatternToRegex($pattern);
//
//            if (preg_match($regex, $message, $matches)) {
//                array_shift($matches); // Remove full match
//
//                return $this->applyMiddleware($pattern, $message, $from, $to, function ($message, $from, $to) use ($handler, $matches) {
//                    return $this->executeHandler($handler, $matches, $from, $to);
//                });
//            }
//        }
//
//        return response()->json(['message' => 'Unknown command. Please try again.'], 404);
//    }

    /**
     * Apply middleware stack for a given route before executing the handler.
     *
     * @param string $pattern The matched route pattern.
     * @param string $message The received SMS message.
     * @param string $from The sender's phone number.
     * @param string $to The recipient's phone number.
     * @param Closure $next The next execution step.
     *
     * @return mixed
     */
    protected function applyMiddleware(string $pattern, string $message, string $from, string $to, Closure $next)
    {
        $middlewareStack = $this->middlewares[$pattern] ?? [];

        $pipeline = array_reduce(
            array_reverse($middlewareStack),
            fn($nextLayer, $middleware) => function ($message, $from, $to) use ($middleware, $nextLayer) {
                $middlewareInstance = new $middleware();
                return $middlewareInstance->handle($message, $from, $to, $nextLayer);
            },
            $next
        );

        return $pipeline($message, $from, $to);
    }

    /**
     * Execute the assigned handler for a matched pattern.
     *
     * @param mixed $handler The handler (callable or class name).
     * @param array $values Captured values from the SMS message.
     * @param string $from Sender's phone number.
     * @param string $to Recipient's phone number.
     * @return mixed
     */
    protected function executeHandler($handler, array $values, string $from, string $to)
    {
        if (is_callable($handler)) {
            return $handler($values, $from, $to);
        }

        if (is_string($handler) && class_exists($handler)) {
            $handlerInstance = new $handler;
            if (method_exists($handlerInstance, '__invoke')) {
                return $handlerInstance($values, $from, $to);
            }
            if (method_exists($handlerInstance, 'handle')) {
                return $handlerInstance->handle($values, $from, $to);
            }
        }

        return response()->json(['error' => 'Invalid handler'], 500);
    }

    /**
     * Convert a pattern string into a regex.
     *
     * @param string $pattern The pattern string.
     * @return string The generated regex.
     */
    protected function convertPatternToRegex(string $pattern): string
    {
        // 31 Mar 2023
        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $var = $matches[1];
            return ($var === 'message' || $var === 'extra')
                ? '(?P<' . $var . '>.+)' // ✅ capture multi-word trailing values
                : '(?P<' . $var . '>\S+)';
        }, $pattern);

        // Handle optional parameters (including `extra?`) — fix for `extra?`
        $pattern = preg_replace_callback('/\s*\{(\w+)\?\}/', function ($matches) {
            $var = $matches[1];
            if ($var === 'extra') {
                // Match a leading space followed by a greedy capture
                return '(?:\s+(?P<extra>.+))?';
            }
            return '(?:\s+(?P<' . $var . '>\S+))?';
        }, $pattern);

//        // Convert required parameters: {voucher} → (?P<voucher>\S+)
//        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
//            return ($matches[1] === 'message')
//                ? '(?P<message>.+)'  // Capture full message with spaces
//                : '(?P<' . $matches[1] . '>\S+)'; // Default to single-word values
//        }, $pattern);
//
//        // Convert optional parameters: {mobile?} → (?:\s+(?P<mobile>\S+))?
//        $pattern = preg_replace('/\s*\{(\w+)\?\}/', '(?:\s+(?P<$1>\S+))?', $pattern);

        return '/^' . trim($pattern) . '$/i';
    }
}
