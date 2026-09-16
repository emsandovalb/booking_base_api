<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const ALLOWED_PREFIXES = [
        'avatars/',
        'courts/',
        'teams/',
        'tournaments/',
    ];

    private const ALLOWED_EXTENSIONS = [
        'gif',
        'jpeg',
        'jpg',
        'png',
        'webp',
    ];

    public function show(Request $request)
    {
        $rawPath = (string) $request->query('path', '');
        $normalized = $this->normalizePath($rawPath);
        $disk = Storage::disk('public');

        if ($normalized === null) {
            return $this->notFound();
        }

        $headers = [
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $mimeType = $disk->mimeType($normalized);
        if (is_string($mimeType) && $mimeType !== '') {
            $headers['Content-Type'] = $mimeType;
        }

        if (!$disk->exists($normalized)) {
            return $this->notFound();
        }

        $contents = $disk->get($normalized);
        if ($contents === false) {
            return $this->notFound();
        }

        $headers['Content-Length'] = (string) strlen($contents);

        return response($contents, 200, $headers);
    }

    private function normalizePath(string $path): ?string
    {
        $decoded = trim($path);
        if ($decoded === '') {
            return null;
        }

        for ($i = 0; $i < 2; $i++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        if (str_contains($decoded, "\0") || preg_match('/[\x00-\x1F\x7F]/u', $decoded)) {
            return null;
        }

        if (Str::contains($decoded, ['\\', ':', '%', '..'])) {
            return null;
        }

        $decoded = ltrim($decoded, '/');
        if (Str::startsWith($decoded, 'storage/')) {
            $decoded = Str::after($decoded, 'storage/');
        }

        if ($decoded === '' || str_ends_with($decoded, '/')) {
            return null;
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            if ($segment !== trim($segment)) {
                return null;
            }
        }

        $extension = strtolower(pathinfo($decoded, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (Str::startsWith($decoded, $prefix)) {
                return $decoded;
            }
        }

        return null;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Media not found'], 404);
    }
}
