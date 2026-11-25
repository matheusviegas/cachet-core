<?php

namespace Cachet\View;

use Cachet\Settings\AppSettings;
use Cachet\Settings\ThemeSettings;
use Cachet\Status;
use Illuminate\Support\Facades\Storage;

/**
 * Picks the favicon for the status page and the dashboard.
 *
 *   dynamic favicon on and not operational -> status icon (partial outage, major outage, maintenance)
 *   uploaded favicon still on the disk      -> uploaded favicon
 *   otherwise                               -> Cachet's favicon
 */
final class Favicon
{
    /**
     * The icon types the upload accepts, by file extension.
     *
     * SVG is left out: an SVG can carry scripts, and uploads are served from the app's origin.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'png' => 'image/png',
        'ico' => 'image/x-icon',
    ];

    /**
     * The MIME types an uploaded favicon may have.
     *
     * @var list<string>
     */
    public const UPLOAD_MIME_TYPES = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'];

    public function __construct(
        private readonly AppSettings $appSettings,
        private readonly ThemeSettings $themeSettings,
        private readonly Status $status,
    ) {}

    /**
     * The status icon shown during incidents and maintenance, when dynamic favicons are on.
     */
    public function statusUrl(): ?string
    {
        if (! $this->appSettings->dynamic_favicon) {
            return null;
        }

        $favicon = $this->status->current()->getFavicon();

        return $favicon ? asset('vendor/cachethq/cachet/'.$favicon) : null;
    }

    /**
     * The uploaded favicon, unless it is missing from the uploads disk.
     *
     * @return array{url: string, type: string}|null
     */
    public function custom(): ?array
    {
        $path = $this->themeSettings->favicon;
        $disk = Storage::disk((string) config('cachet.uploads.disk', 'public'));

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        return [
            'url' => $disk->url($path),
            'type' => self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'image/png',
        ];
    }

    /**
     * The favicon of the dashboard, which does not follow the system status.
     */
    public function dashboardUrl(): string
    {
        return $this->custom()['url'] ?? asset('vendor/cachethq/cachet/favicon.ico');
    }

    /**
     * The icon added to home screens on iOS.
     */
    public function appleTouchIconUrl(): string
    {
        return $this->custom()['url'] ?? asset('vendor/cachethq/cachet/apple-touch-icon.png');
    }
}
