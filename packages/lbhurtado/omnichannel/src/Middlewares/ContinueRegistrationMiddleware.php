<?php

namespace LBHurtado\OmniChannel\Middlewares;

use LBHurtado\OmniChannel\Rules\DoesNotMatchAppDomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Closure;

class ContinueRegistrationMiddleware implements SMSMiddlewareInterface
{
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        $user = User::where('mobile', $from)->first();

        $appDomain = strtolower(parse_url(config('app.url'), PHP_URL_HOST));

        // Only trigger middleware if email ends with the app domain
        if ($user && str_ends_with($user->email, "@{$appDomain}")) {
            $key = "pending_email:{$from}";

            if (!Cache::has($key)) {
                Cache::put($key, true, now()->addMinutes(5));

                Log::info("📨 Prompting {$from} for missing email.");
                return response()->json([
                    'message' => "Hi! Almost done. Please reply with your email address to complete registration."
                ]);
            }

            $validator = Validator::make(['email' => $message], [
                'email' => ['required', 'email', 'unique:users,email', new DoesNotMatchAppDomain],
            ]);

            if ($validator->fails()) {
                Log::warning("⚠️ Invalid email attempt from {$from}: {$message}");

                return response()->json([
                    'message' => "Oops! That doesn't look like a valid or unused email. Try again."
                ]);
            }

            $user->email = strtolower($message);
            $user->save();
            Cache::forget($key);

            Log::info("✅ Email updated for {$from}: {$user->email}");

            return response()->json([
                'message' => "Thanks! You're now fully registered as {$user->email}."
            ]);
        }

        return $next($message, $from, $to);
    }
}
