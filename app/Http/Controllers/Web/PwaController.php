<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Support\BrandingConfig;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(string $experience, string $slug): Response
    {
        abort_unless(in_array($experience, ['b', 'app'], true), 404);
        $business = Business::resolveBySlug($slug) ?? abort(404);
        $branding = BrandingConfig::resolveForBusiness($business);
        $name = $experience === 'app' ? $business->name.' Agenda' : $business->name;
        $start = url("/{$experience}/{$business->slug}");

        $manifest = [
            'id' => "/{$experience}/{$business->slug}",
            'name' => $name,
            'short_name' => mb_substr($name, 0, 24),
            'description' => 'Reservas rápidas y agenda móvil.',
            'start_url' => $start,
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => data_get($branding, 'colors.background', '#0b1220'),
            'theme_color' => data_get($branding, 'colors.primary', '#d7a84b'),
            'icons' => [
                ['src' => route('pwa.icon', ['slug' => $business->slug, 'size' => 192]), 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
                ['src' => route('pwa.icon', ['slug' => $business->slug, 'size' => 512]), 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function icon(string $slug, int $size): Response
    {
        $business = Business::resolveBySlug($slug) ?? abort(404);
        abort_unless(in_array($size, [192, 512], true), 404);
        $branding = BrandingConfig::resolveForBusiness($business);
        $initials = collect(preg_split('/\s+/', $business->name))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
        $initials = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $color = data_get($branding, 'colors.primary', '#d7a84b');
        $color = is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#d7a84b';
        $logoDataUri = $this->localLogoDataUri(data_get($branding, 'assets.app_icon') ?: data_get($branding, 'assets.logo_transparent'));
        $artwork = $logoDataUri
            ? '<defs><clipPath id="icon-mask"><circle cx="256" cy="256" r="174"/></clipPath></defs><image href="'.htmlspecialchars($logoDataUri, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" x="82" y="82" width="348" height="348" preserveAspectRatio="xMidYMid slice" clip-path="url(#icon-mask)"/>'
            : '<circle cx="256" cy="256" r="174" fill="'.$color.'"/><text x="256" y="292" text-anchor="middle" font-family="Arial,sans-serif" font-size="132" font-weight="700" fill="#0b1220">'.$initials.'</text>';
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 512 512"><rect width="512" height="512" rx="116" fill="#0b1220"/>{$artwork}</svg>
SVG;

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function serviceWorker(): Response
    {
        $script = <<<'JS'
const CACHE='bamos-pwa-v1';
const SHELL=['/offline'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).then(()=>self.skipWaiting())));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET') return;
  const url=new URL(event.request.url);
  if(event.request.mode==='navigate'){
    event.respondWith(fetch(event.request).catch(()=>caches.match('/offline')));
    return;
  }
  if(url.origin===location.origin&&(url.pathname.includes('/pwa/')||url.pathname.endsWith('manifest.webmanifest'))){
    event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request).then(response=>{
      const copy=response.clone(); caches.open(CACHE).then(cache=>cache.put(event.request,copy)); return response;
    })));
  }
});
JS;

        return response($script, 200, ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/', 'Cache-Control' => 'no-cache']);
    }

    private function localLogoDataUri(?string $logo): ?string
    {
        if (! is_string($logo) || $logo === '' || str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return null;
        }

        $relative = ltrim(parse_url($logo, PHP_URL_PATH) ?: $logo, '/');
        $path = str_starts_with($relative, 'storage/')
            ? storage_path('app/public/'.substr($relative, strlen('storage/')))
            : public_path($relative);
        $realPath = realpath($path);
        $publicRoot = realpath(public_path());
        $storageRoot = realpath(storage_path('app/public'));

        if (! $realPath || ! is_file($realPath) || ! (($publicRoot && str_starts_with($realPath, $publicRoot.DIRECTORY_SEPARATOR)) || ($storageRoot && str_starts_with($realPath, $storageRoot.DIRECTORY_SEPARATOR)))) {
            return null;
        }

        $mime = mime_content_type($realPath);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($realPath));
    }
}
