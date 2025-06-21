<?php

namespace LBHurtado\OmniChannel\Handlers;

use Symfony\Component\Console\Input\{InputDefinition, InputOption, StringInput};
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;
use Propaganistas\LaravelPhone\Rules\Phone;
//use App\Actions\SendRegistrationFeedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
//use App\Events\RegisteredViaSMS;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use App\Models\User;

class SMSRegister implements SMSHandlerInterface
{
    /**
     * Handle SMS user registration.
     *
     * Expected syntax:
     * - Unified format (supports both self and admin registration):
     *   REGISTER [mobile] [--email|-e <email>] [--name|-n "Full Name"] [--password|-p "Secret"]
     *
     * Examples:
     *   REGISTER 09171234567 -e"user@example.com" -n"Juan Dela Cruz" -p"Secret123"
     *   REGISTER -n"Maria" -e"maria@example.com" (self-registers using sender's mobile)
     */
    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        $extra = $values['extra'] ?? '';

        Log::info('📨 Processing SMS registration', [
            'from' => $from,
            'to' => $to,
            'input' => $values,
            'extra' => $extra,
        ]);

        // Parse extras and merge
        $extras = $this->parseExtras($extra);
        Log::debug('📦 Parsed extras from SMS', $extras);

        // If mobile is not given in command, fallback to sender's number
        $mobile = $values['mobile'] ?? $from;
        $values = array_merge(['mobile' => $mobile], $values, $extras);

        $rules = [
            'mobile' => ['required', (new Phone)->type('mobile')->country('PH'), Rule::unique(User::class)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', Rules\Password::defaults()],
        ];

        try {
            $validated = Validator::make($values, $rules)->validate();

            $appDomain = strtolower(parse_url(config('app.url'), PHP_URL_HOST));
            $mobile = $validated['mobile'];
            $email = $validated['email'] ?? "{$mobile}@{$appDomain}";
            $name = $validated['name'] ?? $email;
            $password = $validated['password'] ?? 'password';

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => Hash::make($password),
            ]);

//            RegisteredViaSMS::dispatch($user);

            Log::info('✅ User registered successfully', [
                'id' => $user->id,
                'email' => $user->email,
                'mobile' => $user->mobile,
            ]);

            // Determine if this is self-registration or third-party
            $selfRegistration = $from === $mobile;

            if ($selfRegistration) {
                return response()->json([
                    'message' => "Registered: {$user->name} <{$user->email}>"
                ]);
            }

            // Notify the registered user via SMS
//            SendRegistrationFeedback::run($user);

            return response()->json([
                'message' => "Registration complete. We've notified {$user->mobile} with their account details."
            ]);
        } catch (\Throwable $th) {
            Log::error('❌ SMS registration failed', [
                'exception' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            report($th);

            return response()->json([
                'message' => "Syntax: REGISTER [mobile] [-e\"Email\"] [-n\"Full Name\"] [-p\"Password\"]",
            ]);
        }
    }

    /**
     * Parse --key or -k style parameters from extra.
     * Supports quoted or unquoted values for: --email / -e, --name / -n, --password / -p
     */
    protected function parseExtras(string $extra): array
    {
        $definition = new InputDefinition([
            new InputOption('email', 'e', InputOption::VALUE_REQUIRED),
            new InputOption('name', 'n', InputOption::VALUE_REQUIRED),
            new InputOption('password', 'p', InputOption::VALUE_REQUIRED),
        ]);

        // Fix missing space after short options (e.g., -elbhurtado@example.com)
        $extra = preg_replace_callback('/-(\w)([^\s"]+)/', function ($m) {
            return "-{$m[1]} \"{$m[2]}\"";
        }, $extra);

        try {
            $input = new StringInput($extra);
            $input->bind($definition);

            $results = [
                'email' => $input->getOption('email'),
                'name' => $input->getOption('name'),
                'password' => $input->getOption('password'),
            ];

            Log::debug('📦 Parsed extras from SMS', array_filter($results));
            return array_filter($results);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Failed to parse SMS extras', [
                'extra' => $extra,
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
