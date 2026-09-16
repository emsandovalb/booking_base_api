<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\MediaController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_route_serves_public_storage_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'courts/test-media.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO4BqfQAAAAASUVORK5CYII=')
        );
        $this->assertTrue(Storage::disk('public')->exists('courts/test-media.png'));
        $this->assertTrue(is_file(Storage::disk('public')->path('courts/test-media.png')));
        $request = \Illuminate\Http\Request::create('/api/v1/media', 'GET', [
            'path' => '/storage/courts/test-media.png',
        ]);
        $this->assertSame('/storage/courts/test-media.png', $request->query('path'));
        $controllerResponse = app(MediaController::class)->show($request);
        $this->assertSame(200, $controllerResponse->getStatusCode());

        $response = $this->get('/api/v1/media?path=' . rawurlencode('/storage/courts/test-media.png'));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $response->assertHeader('content-length');
        $response->assertHeader('cache-control');
        $response->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_media_route_rejects_traversal_attempts(): void
    {
        $this->assertMediaPathRejected('../../env');
        $this->assertMediaPathRejected('..\\env');
        $this->assertMediaPathRejected('/etc/passwd');
        $this->assertMediaPathRejected('C:\\Windows\\win.ini');
        $this->assertMediaPathRejected('/storage/.env');
        $this->assertMediaPathRejected('/storage/framework/cache/data');
        $this->assertMediaPathRejected('/storage/courts/test-media.txt');
        $this->assertMediaPathRejected('/storage/courts/folder/');
    }

    public function test_media_path_normalizer_rejects_null_bytes_and_encoded_equivalents(): void
    {
        $controller = new MediaController();
        $invoke = function (string $value) use ($controller): ?string {
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('normalizePath');
            $method->setAccessible(true);

            return $method->invoke($controller, $value);
        };

        $this->assertNull($invoke("courts/test\0.png"));
        $this->assertNull($invoke('%2e%2e%2f.env'));
        $this->assertNull($invoke('courts/%2e%2e/%2eenv'));
        $this->assertSame('courts/test image.png', $invoke('courts/test%20image.png'));
    }

    public function test_media_path_normalizer_accepts_allowed_public_image_paths(): void
    {
        $controller = new MediaController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('normalizePath');
        $method->setAccessible(true);

        $this->assertSame('courts/service image ñ.png', $method->invoke($controller, '/storage/courts/service image ñ.png'));
        $this->assertSame('avatars/avatar 01.webp', $method->invoke($controller, 'avatars/avatar 01.webp'));
    }

    private function assertMediaPathRejected(string $path): void
    {
        $response = $this->get('/api/v1/media', ['path' => $path]);

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'Media not found']);
    }
}
