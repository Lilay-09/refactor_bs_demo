<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    // public function fromShortUrl(string $shortUrl): array
    // {


    //     $parsed = parse_url($shortUrl);
    //     parse_str($parsed['query'] ?? '', $query);

    //     if (
    //         isset($parsed['host'], $query['q']) &&
    //         Str::contains($parsed['host'], 'maps.google.com') &&
    //         preg_match('/^-?\d+\.\d+,-?\d+\.\d+$/', $query['q'])
    //     ) {
    //         [$lat, $lng] = explode(',', $query['q']);

    //         $address = $this->reverseGeocode($lat, $lng);

    //         return [
    //             'lat'     => (float) $lat,
    //             'lng'     => (float) $lng,
    //             'address' => $address,
    //         ];
    //     }

    //     // 1) HEAD to get the redirect location
    //     $resp = Http::withOptions(['allow_redirects' => false])
    //                 ->head($shortUrl);
    //     $location = $resp->header('Location')
    //         ?? throw new RuntimeException("No redirect for {$shortUrl}");
    //     // if (! str_starts_with($location, 'https://www.google.com/maps')) {
    //     //     throw new RuntimeException("Unexpected redirect URL: {$location}");
    //     // }
    //     if (! preg_match('#^https://www\.google\.com(?:\.kh)?/maps#', $location)) {
    //         throw new RuntimeException("Unexpected redirect URL: {$location}");
    //     }
    //     if (! preg_match('#(?:@|search/)(-?\d+\.\d+),[+\s]?(-?\d+\.\d+)#', $location, $m)) {
    //         return [
    //             'lat' => 0,
    //             'lng' => 0,
    //             'address' => null,
    //         ];
    //     }
    //     // 2) extract lat/lng from the final URL
    //     [, $lat, $lng] = $m;

    //     // 3) reverse-geocode via Nominatim
    //     $address = $this->reverseGeocode($lat, $lng);
    //     // $embedUrl = "https://www.google.com/maps?q={$lat},{$lng}&hl=en&z=14&output=embed";

    //     return [
    //         'lat'     => (float) $lat,
    //         'lng'     => (float) $lng,
    //         'address' => $address,
    //         // 'embedUrl' => $embedUrl
    //     ];
    // }
    public function fromShortUrl(string $shortUrl): array
    {
        $parsed = parse_url($shortUrl);
        parse_str($parsed['query'] ?? '', $query);

        if (
            isset($parsed['host'], $query['q']) &&
            Str::contains($parsed['host'], 'maps.google.com') &&
            preg_match('/^-?\d+\.\d+,-?\d+\.\d+$/', $query['q'])
        ) {
            [$lat, $lng] = explode(',', $query['q']);

            $address = $this->reverseGeocode($lat, $lng);

            return [
                'error' => false,
                'lat'     => (float) $lat,
                'lng'     => (float) $lng,
                'address' => $address,
            ];
        }

        // 2) Attempt to follow short URL redirect safely
        try {
            $resp = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            ])->withOptions([
                'allow_redirects' => false
            ])->get($shortUrl);

            $location = $resp->header('Location') ?? null;

            if (!$location || !preg_match('#^https://www\.google\.com(?:\.kh)?/maps#', $location)) {
                return [
                    'error'   => true,
                    'lat'     => 0,
                    'lng'     => 0,
                    'address' => null,
                    'message' => 'This pin URL is not supported or blocked',
                ];
            }

            // 3) Extract lat/lng from the final URL
            if (!preg_match('#(?:@|search/)(-?\d+\.\d+),[+\s]?(-?\d+\.\d+)#', $location, $matches)) {
                return [
                    'error'   => false,
                    'lat'     => 0,
                    'lng'     => 0,
                    'address' => null,
                    'message' => 'No coordinates found in URL',
                ];
            }

            [, $lat, $lng] = $matches;
            $address = $this->reverseGeocode($lat, $lng);

            return [
                'error'   => false,
                'lat'     => (float) $lat,
                'lng'     => (float) $lng,
                'address' => $address,
            ];
        } catch (Exception $e) {
            // Graceful fallback if Google blocks the request
            Log::error("Blocked Google Maps URL: {$shortUrl}. Error: {$e->getMessage()}");
            return [
                'error'   => true,
                'lat'     => 0,
                'lng'     => 0,
                'address' => null,
                'message' => 'Blocked by Google or invalid URL',
            ];
        }
    }

    /**
     * Given lat & lng, reverse‐geocode to an address.
     *
     * @param  float|string  $lat
     * @param  float|string  $lng
     * @return string
     * @throws RuntimeException
     */
    /**
     * Given lat & lng, reverse-geocode to an address.
     *
     * @param  float|string  $lat
     * @param  float|string  $lng
     * @return string
     */
    public function reverseGeocode($lat, $lng): string
    {
        try {
            $osm = Http::withHeaders([
                    'User-Agent' => 'MyLaravelApp/1.0 (help@example.com)',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat'            => $lat,
                    'lon'            => $lng,
                    'format'         => 'json',
                    'addressdetails' => 1,
                ])
                ->throw() // throws if HTTP client fails (4xx, 5xx)
                ->json();
            return data_get($osm, 'display_name', '');
        } catch (Exception $e) {
            // Optionally log the error
            Log::error("Reverse geocode failed: {$e->getMessage()} for {$lat},{$lng}");
            return '';
        }
    }

    /**
     * Accept raw lat/lng, return lat, lng, address.
     */
    public function fromCoords(float $lat, float $lng,$isReverseAddress=false): array
    {
        return [
            'lat'     => $lat,
            'lng'     => $lng,
            'address' => ($isReverseAddress && $lat && $lng) ?$this->reverseGeocode($lat, $lng):null,
            'mapUrl'  => ($lat && $lng)
            ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}"
            : null,
        ];
    }
}
