<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where an inventory sheet was filled in from.
 *
 * The machine is read from the request — nobody types their own IP address —
 * while the position can only be volunteered by the browser, so it is optional
 * and stays advisory. Both are carried together because they are read together:
 * a count from the shop's usual terminal, at the shop's coordinates, is the
 * ordinary case, and it is the pair that makes an unusual one visible.
 */
final class CountingContext
{
    public function __construct(
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $deviceLabel = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?int $locationAccuracy = null,
    ) {}

    /**
     * Build from the incoming request, taking the coordinates from the payload.
     *
     * @param  array{latitude?: mixed, longitude?: mixed, accuracy?: mixed}|null  $location
     */
    public static function fromRequest(Request $request, ?array $location = null): self
    {
        $userAgent = $request->userAgent();

        // A pair of coordinates is only meaningful whole: half of one says
        // nothing and would only make the sheet look better documented than it is.
        $latitude = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $longitude = isset($location['longitude']) ? (float) $location['longitude'] : null;

        if ($latitude === null || $longitude === null) {
            $latitude = $longitude = null;
        }

        return new self(
            ipAddress: $request->ip(),
            userAgent: $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            deviceLabel: self::describeDevice($userAgent),
            latitude: $latitude,
            longitude: $longitude,
            locationAccuracy: isset($location['accuracy']) ? (int) round((float) $location['accuracy']) : null,
        );
    }

    /**
     * A user agent nobody has to decode.
     *
     * Deliberately coarse: the full string is stored next to this, and the label
     * only has to be enough to tell "the warehouse laptop" from "a phone" at a
     * glance. Order matters — every Chrome string also claims to be Safari, and
     * Edge claims to be both.
     */
    public static function describeDevice(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        $browsers = [
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'Firefox' => 'Firefox',
            'Chrome' => 'Chrome',
            'Safari' => 'Safari',
        ];

        $platforms = [
            'Android' => 'Android',
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
            'Windows' => 'Windows',
            'Mac OS X' => 'macOS',
            'Macintosh' => 'macOS',
            'Linux' => 'Linux',
        ];

        $browser = null;
        foreach ($browsers as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $browser = $label;
                break;
            }
        }

        $platform = null;
        foreach ($platforms as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $platform = $label;
                break;
            }
        }

        return match (true) {
            $browser !== null && $platform !== null => "{$browser} · {$platform}",
            $browser !== null => $browser,
            $platform !== null => $platform,
            // An unrecognised agent is still worth naming: a count from a script
            // or an unusual client is exactly what an audit wants to notice.
            default => mb_substr($userAgent, 0, 60),
        };
    }

    /**
     * The columns this context contributes to a count row.
     *
     * @return array<string, string|float|int|null>
     */
    public function toAttributes(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'device_label' => $this->deviceLabel,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_accuracy_m' => $this->latitude === null ? null : $this->locationAccuracy,
        ];
    }
}
