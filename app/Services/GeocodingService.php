<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Http;

/**
 * Google Geocoding wrapper + in-service-area check.
 *
 * The service-area config supports two shapes:
 *   - {"type": "zip_codes", "codes": ["80301", "80302", ...]}
 *   - {"type": "radius", "center_lat": 40.0, "center_lng": -105.3, "radius_km": 25}
 */
class GeocodingService
{
    public function geocode(string $address): ?array
    {
        $key = (string) config('services.google.geocoding_api_key');
        if ($key === '') {
            return null;
        }

        $resp = Http::timeout(5)->get(
            'https://maps.googleapis.com/maps/api/geocode/json',
            ['address' => $address, 'key' => $key],
        );

        if (! $resp->ok()) {
            return null;
        }

        $body = $resp->json();
        $first = $body['results'][0] ?? null;
        if (! $first) {
            return null;
        }

        $location = $first['geometry']['location'] ?? null;
        $postal = null;
        foreach ($first['address_components'] ?? [] as $c) {
            if (in_array('postal_code', $c['types'] ?? [], true)) {
                $postal = $c['short_name'] ?? null;
                break;
            }
        }

        return [
            'formatted' => $first['formatted_address'] ?? $address,
            'lat' => $location['lat'] ?? null,
            'lng' => $location['lng'] ?? null,
            'postal_code' => $postal,
        ];
    }

    public function isInServiceArea(Business $business, array $geocoded): bool
    {
        $area = $business->service_area ?? [];
        $type = $area['type'] ?? null;

        if ($type === 'zip_codes') {
            $zip = $geocoded['postal_code'] ?? null;

            return $zip !== null && in_array($zip, $area['codes'] ?? [], true);
        }

        if ($type === 'radius') {
            $lat = $geocoded['lat'] ?? null;
            $lng = $geocoded['lng'] ?? null;
            if ($lat === null || $lng === null) {
                return false;
            }
            $km = $this->haversineKm(
                (float) $area['center_lat'],
                (float) $area['center_lng'],
                (float) $lat,
                (float) $lng,
            );

            return $km <= (float) $area['radius_km'];
        }

        // No service-area config = no enforcement.
        return true;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }
}
