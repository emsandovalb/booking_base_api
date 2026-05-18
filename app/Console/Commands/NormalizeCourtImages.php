<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Court;

class NormalizeCourtImages extends Command
{
    protected $signature = 'courts:normalize-images {--dry-run}';
    protected $description = 'Normalize courts.images to a JSON array (wrap or decode when stored as string)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $count = 0; $updated = 0;

        Court::chunk(200, function ($chunk) use (&$count, &$updated, $dry) {
            foreach ($chunk as $court) {
                $count++;
                $normalized = $this->normalizeImages($court->getAttribute('images'));
                if ($normalized === false) {
                    continue;
                }
                $updated++;
                if (!$dry) {
                    $court->images = $normalized;
                    $court->save();
                }
            }
        });

        $this->info("Scanned: {$count} courts. Normalized: {$updated}. Dry-run: " . ($dry ? 'yes' : 'no'));
        return self::SUCCESS;
    }

    private function normalizeImages($img)
    {
        if ($img === null || $img === '') {
            return false;
        }
        if (is_array($img)) {
            $converted = [];
            foreach ($img as $item) {
                $value = $this->convertEntry($item);
                if ($value) $converted[] = $value;
            }
            return $converted;
        }
        if (is_string($img)) {
            $trim = trim($img);
            if ($trim === '') return [];
            if (str_starts_with($trim, '[')) {
                try {
                    $decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $this->normalizeImages($decoded);
                    }
                } catch (\Throwable $e) {
                    $this->warn("Failed to decode JSON for court {$img}: {$e->getMessage()}");
                }
            }
            $value = $this->convertEntry($trim);
            if ($value) return [$value];
            return [];
        }
        return false;
    }

    private function convertEntry($value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                $nested = $this->convertEntry($v);
                if ($nested) return $nested;
            }
            return null;
        }
        if (!is_string($value)) {
            return null;
        }
        $trim = trim($value);
        if ($trim === '') return null;
        if (Str::startsWith($trim, ['http://', 'https://', '/storage/'])) {
            return $trim;
        }
        if (Str::startsWith($trim, 'data:image')) {
            $parts = explode(',', $trim, 2);
            if (count($parts) !== 2) return null;
            $meta = $parts[0];
            $data = $parts[1];
            $mime = 'image/jpeg';
            if (preg_match('/data:(.*?);base64/', $meta, $matches)) {
                $mime = $matches[1] ?: $mime;
            }
            $binary = base64_decode($data, true);
            if ($binary === false) return null;
            return $this->storeBinaryImage($binary, $mime);
        }
        if (strlen($trim) > 100 && preg_match('/^[A-Za-z0-9+\/]+=*$/', $trim)) {
            $binary = base64_decode($trim, true);
            if ($binary !== false) {
                return $this->storeBinaryImage($binary, 'image/jpeg');
            }
        }
        if (is_file($trim)) {
            $mime = mime_content_type($trim) ?: 'image/jpeg';
            $binary = @file_get_contents($trim);
            if ($binary !== false) {
                return $this->storeBinaryImage($binary, $mime);
            }
        }
        return $trim;
    }

    private function storeBinaryImage(string $binary, string $mime): string
    {
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $name = 'courts/' . Str::uuid() . '.' . $ext;
        Storage::disk('public')->put($name, $binary);
        return Storage::url($name);
    }
}
