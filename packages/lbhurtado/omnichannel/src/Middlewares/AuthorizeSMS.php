<?php

namespace LBHurtado\OmniChannel\Middlewares;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Closure;

class AuthorizeSMS implements SMSMiddlewareInterface
{
    /**
     * Allowed numbers for each command.
     *
     * @var array<string, array>
     */
    protected array $allowedNumbers = [];

    /**
     * Constructor: Load allowed numbers from config.
     */
    public function __construct()
    {
        $this->allowedNumbers = $this->loadAllowedNumbers();
    }

    /**
     * Handle an incoming SMS.
     *
     * @param string   $message The SMS message.
     * @param string   $from    The sender's phone number.
     * @param string   $to      The recipient's phone number.
     * @param Closure  $next    The next middleware or handler.
     *
     * @return mixed
     */
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        // Extract command from the message (first word in uppercase)
        $command = strtoupper(strtok($message, ' '));

        // Check if the command is restricted
        if (isset($this->allowedNumbers[$command])) {
            // If the sender is NOT in the allowed list, deny access
            if (!in_array($from, $this->allowedNumbers[$command])) {
                Log::warning("🚫 Unauthorized SMS attempt", ['command' => $command, 'from' => $from]);

                return response()->json([
                    'message' => "Unauthorized access. You are not allowed to use this command.",
                ], 403);
            }
        }

        // Proceed to the next middleware or handler
        return $next($message, $from, $to);
    }

    /**
     * Load allowed phone numbers for each SMS command from configuration and environment variables.
     *
     * This method retrieves an associative array where:
     * - **Keys (commands)** are converted to uppercase (e.g., `"TRANSFER"`, `"GENERATE"`).
     * - **Values** are arrays of phone numbers that are authorized to use the respective command.
     * - The allowed numbers are loaded from `config/kwyc-cash.php`, which can be overridden via `.env`.
     *
     * 🔹 **Use Cases:**
     * - Restricting certain SMS commands to specific phone numbers.
     * - Dynamically adding/removing allowed numbers via `.env` without modifying the code.
     * - Supporting new SMS commands without manually updating this method.
     *
     * 🔹 **Example Configuration (`config/kwyc-cash.php`):**
     * ```php
     * return [
     *     'sms' => [
     *         'allowed' => [
     *             'transfer' => array_filter(explode(',', env('SMS_ALLOWED_TRANSFER', '09173011987,09178251991'))),
     *             'generate' => array_filter(explode(',', env('SMS_ALLOWED_GENERATE', '09178251991'))),
     *             'special'  => array_filter(explode(',', env('SMS_ALLOWED_SPECIAL', '09179998888'))),
     *         ],
     *     ],
     * ];
     * ```
     *
     * 🔹 **Expected Output (`loadAllowedNumbers()` Result):**
     * ```php
     * [
     *     'TRANSFER' => ['09173011987', '09178251991'],
     *     'GENERATE' => ['09178251991'],
     *     'SPECIAL'  => ['09179998888'],
     * ]
     * ```
     *
     * 🔹 **Example Authorization Check in Middleware:**
     * ```php
     * $command = strtoupper(strtok($message, ' '));
     * if (isset($allowedNumbers[$command]) && !in_array($from, $allowedNumbers[$command])) {
     *     return response()->json(['message' => "Unauthorized access"], 403);
     * }
     * ```
     *
     * @return array<string, array> An array where each key is an uppercase SMS command and each value is an array of authorized phone numbers.
     */
    protected function loadAllowedNumbers(): array
    {
        // Get all allowed commands from config
        $allowedCommands = config('omnichannel.allowed', []);

        // Convert keys to uppercase while ensuring values remain arrays
        return collect($allowedCommands)
            ->mapWithKeys(fn($numbers, $command) => [strtoupper($command) => is_array($numbers) ? $numbers : []])
            ->toArray();
    }
}
