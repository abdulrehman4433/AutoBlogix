<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConnectionLogStatus;
use App\Enums\WebsiteStatus;
use App\Models\Website;

/**
 * State transitions for the plugin's connection endpoints
 * (connect / verify / disconnect / heartbeat).
 */
class WordPressConnectionService
{
    /**
     * Full handshake: website becomes connected and version info is stored.
     *
     * @param  array{wordpress_version: string, plugin_version: string}  $payload
     * @return array<string, mixed>
     */
    public function connect(Website $website, array $payload, ?string $ipAddress = null): array
    {
        $website->status = WebsiteStatus::Connected;
        $website->wordpress_version = $payload['wordpress_version'];
        $website->plugin_version = $payload['plugin_version'];
        $website->last_connected_at = now();
        $website->last_sync_at = now();
        $website->save();

        $this->log(
            $website,
            'connect',
            sprintf('Plugin connected (WordPress %s, plugin %s).', $payload['wordpress_version'], $payload['plugin_version']),
            $ipAddress,
        );

        return [
            'website_id' => $website->id,
            'name' => $website->name,
            'status' => $website->status->value,
            'timezone' => $website->timezone,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Read-only credential check (plugin "Test connection" action).
     * Returns site info without changing state.
     *
     * @return array<string, mixed>
     */
    public function verify(Website $website, ?string $ipAddress = null): array
    {
        $this->log($website, 'verify', 'Credentials verified.', $ipAddress);

        return [
            'website_id' => $website->id,
            'name' => $website->name,
            'status' => $website->status->value,
            'timezone' => $website->timezone,
            'wordpress_version' => $website->wordpress_version,
            'plugin_version' => $website->plugin_version,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Plugin-initiated disconnect.
     *
     * @return array<string, mixed>
     */
    public function disconnect(Website $website, ?string $ipAddress = null): array
    {
        $website->status = WebsiteStatus::Disconnected;
        $website->save();

        $this->log($website, 'disconnect', 'Plugin disconnected from AutoBlogix.', $ipAddress);

        return [
            'website_id' => $website->id,
            'status' => $website->status->value,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Keep-alive: refreshes versions and last_sync_at, reconnecting the site
     * if it was pending/disconnected/error. Routine heartbeats write no log
     * row — only an actual status change is recorded.
     *
     * @param  array{wordpress_version: string, plugin_version: string}  $payload
     * @return array<string, mixed>
     */
    public function heartbeat(Website $website, array $payload, ?string $ipAddress = null): array
    {
        $statusChanged = $website->status !== WebsiteStatus::Connected;

        $website->status = WebsiteStatus::Connected;
        $website->wordpress_version = $payload['wordpress_version'];
        $website->plugin_version = $payload['plugin_version'];
        $website->last_sync_at = now();

        if ($statusChanged) {
            $website->last_connected_at = now();
        }

        $website->save();

        if ($statusChanged) {
            $this->log($website, 'heartbeat', 'Website reconnected via heartbeat.', $ipAddress);
        }

        return [
            'website_id' => $website->id,
            'status' => $website->status->value,
            'last_sync_at' => $website->last_sync_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function log(Website $website, string $action, string $message, ?string $ipAddress): void
    {
        $website->connectionLogs()->create([
            'action' => $action,
            'status' => ConnectionLogStatus::Success,
            'message' => $message,
            'ip_address' => $ipAddress,
        ]);
    }
}
