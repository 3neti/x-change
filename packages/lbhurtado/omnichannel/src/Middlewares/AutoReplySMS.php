<?php

namespace LBHurtado\OmniChannel\Middlewares;

use LBHurtado\OmniChannel\Contracts\AutoReplyInterface;
use LBHurtado\OmniChannel\Middlewares\StoreSMS;
use LBHurtado\OmniChannel\Middlewares\LogSMS;
use Illuminate\Support\Facades\Log;
use Closure;

class AutoReplySMS implements SMSMiddlewareInterface
{
    protected function getHandlers(): array
    {
        $defaults = [
            'HELP' => \LBHurtado\OmniChannel\Handlers\AutoReplies\HelpAutoReply::class,
            'PING' => \LBHurtado\OmniChannel\Handlers\AutoReplies\PingAutoReply::class,
        ];

        return array_merge(
            $defaults,
            config('omnichannel.handlers.auto_replies', [])
        );
    }

    public function handle(string $message, string $from, string $to, Closure $next)
    {
        $keyword  = strtoupper(strtok($message, " "));
        $handlers = $this->getHandlers();

        if (isset($handlers[$keyword])) {
            $handlerClass = $handlers[$keyword];

            if (class_exists($handlerClass) && is_subclass_of($handlerClass, AutoReplyInterface::class)) {
                /** @var AutoReplyInterface $handler */
                $handler = new $handlerClass();
                $reply   = $handler->reply($from, $to, $message);

                if ($reply !== null) {
                    Log::info("AutoReply Sent", compact('from', 'to', 'reply'));

                    // always *store* and *post‐log* before returning:
                    (new StoreSMS())->handle($message, $from, $to, fn() => null);
                    (new LogSMS())->handle($message, $from, $to, fn() => null);

                    return response()->json(['message' => $reply]);
                }
            }
        }

        // no auto‐reply: just continue down the pipeline
        Log::info("🛠 Running AutoReplySMS Middleware", compact('message', 'from', 'to'));

        return $next($message, $from, $to);
    }
}
