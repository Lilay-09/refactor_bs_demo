<?php

namespace App\Services;
use Http;
use RuntimeException;
class GeoResolverService
{
    // Your service methods go here
    /**
     * From a `maps.app.goo.gl` short URL, resolve to lat/lng + address.
     *
     * @param  string  $shortUrl
     * @return array{lat: float, lng: float, address: string}
     * @throws RuntimeException
     */
    public function fromShortUrl(string $shortUrl): array
    {
        // 1) HEAD to get the redirect location
        $resp = Http::withOptions(['allow_redirects' => false])
                    ->head($shortUrl);
        $location = $resp->header('Location')
            ?? throw new RuntimeException("No redirect for {$shortUrl}");
            // \Log::info($location);
        if (! str_starts_with($location, 'https://www.google.com/maps')) {
            throw new RuntimeException("Unexpected redirect URL: {$location}");
        }

        // 2) extract lat/lng from the final URL
        if (! preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $location, $m)) {
            // throw new RuntimeException("Couldn't parse coords from URL: {$location}");
            return [
                'lat'     => 0,
                'lng'     => 0,
                'address' => null,
            ];
        }
        [, $lat, $lng] = $m;

        // 3) reverse-geocode via Nominatim
        $address = $this->reverseGeocode($lat, $lng);

        return [
            'lat'     => (float) $lat,
            'lng'     => (float) $lng,
            'address' => $address,
        ];
    }

    /**
     * Given lat & lng, reverse‐geocode to an address.
     *
     * @param  float|string  $lat
     * @param  float|string  $lng
     * @return string
     * @throws RuntimeException
     */
    public function reverseGeocode($lat, $lng): string
    {
        $osm = Http::withHeaders([
                'User-Agent' => 'MyLaravelApp/1.0 (help@example.com)',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat'            => $lat,
                'lon'            => $lng,
                'format'         => 'json',
                'addressdetails' => 1,
            ])
            ->throw()
            ->json();

        return data_get($osm, 'display_name')
            ?? throw new RuntimeException("No address for {$lat},{$lng}");
    }

    /**
     * Accept raw lat/lng, return lat, lng, address.
     */
    public function fromCoords(float $lat, float $lng): array
    {
        return [
            'lat'     => $lat,
            'lng'     => $lng,
            'address' => $this->reverseGeocode($lat, $lng),
        ];
    }
}
