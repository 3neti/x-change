<?php

namespace LBHurtado\OmniChannel\Handlers\AutoReplies;

use LBHurtado\OmniChannel\Contracts\AutoReplyInterface;

class PingAutoReply implements AutoReplyInterface
{
    public function reply(string $from, string $to, string $message): string
    {
        $uptime = $this->getUptime();
        $loadAverage = $this->getLoadAverage();
        $memoryUsage = $this->getMemoryUsage();
        $timestamp = now()->format('Y-m-d H:i:s');

        return "PONG! Uptime: {$uptime} Memory Usage: {$memoryUsage} Load Average: {$loadAverage} Timestamp: {$timestamp}";
    }

    /**
     * Get the system uptime.
     */
    private function getUptime(): string
    {
        if (function_exists('shell_exec')) {
            $uptime = shell_exec("uptime -p");
            return $uptime ? trim($uptime) : "Unknown";
        }
        return "Not available";
    }

    /**
     * Get system load average.
     */
    private function getLoadAverage(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load ? implode(", ", array_map(fn ($v) => round($v, 2), $load)) : "Unknown";
        }
        return "Not available";
    }

    /**
     * Get memory usage.
     */
    private function getMemoryUsage(): string
    {
        if (function_exists('memory_get_usage')) {
            $memory = memory_get_usage(true) / 1024 / 1024; // Convert to MB
            return round($memory, 2) . " MB";
        }
        return "Not available";
    }
}
