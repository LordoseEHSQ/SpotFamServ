<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Infrastructure\Storage;

use App\Module\AudioExtractor\Infrastructure\Storage\FilesystemAudioStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilesystemAudioStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sfx_store_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $f) {
            if (is_string($f) && is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($this->dir);
    }

    private function storage(): FilesystemAudioStorage
    {
        return new FilesystemAudioStorage($this->dir);
    }

    private function tempSource(string $content = 'data'): string
    {
        $src = tempnam(sys_get_temp_dir(), 'sfx_src_');
        self::assertIsString($src);
        file_put_contents($src, $content);

        return $src;
    }

    public function test_store_then_list_and_total_size(): void
    {
        $storage = $this->storage();
        $stored = $storage->store($this->tempSource('hello'), 'song.mp3');

        $this->assertSame('song.mp3', $stored->name);
        $this->assertSame('audio/mpeg', $stored->mimeType);

        $list = $storage->list();
        $this->assertCount(1, $list);
        $this->assertSame('song.mp3', $list[0]->name);
        $this->assertSame(5, $storage->totalSizeBytes());
    }

    public function test_store_resolves_name_collisions(): void
    {
        $storage = $this->storage();
        $a = $storage->store($this->tempSource(), 'dup.mp3');
        $b = $storage->store($this->tempSource(), 'dup.mp3');

        $this->assertSame('dup.mp3', $a->name);
        $this->assertSame('dup_2.mp3', $b->name);
        $this->assertCount(2, $storage->list());
    }

    public function test_absolute_path_resolves_known_file(): void
    {
        $storage = $this->storage();
        $storage->store($this->tempSource(), 'track.wav');

        $path = $storage->absolutePath('track.wav');
        $this->assertIsString($path);
        $this->assertStringEndsWith('/track.wav', $path);
    }

    public function test_delete_removes_file(): void
    {
        $storage = $this->storage();
        $storage->store($this->tempSource(), 'gone.mp3');

        $this->assertTrue($storage->delete('gone.mp3'));
        $this->assertCount(0, $storage->list());
        $this->assertFalse($storage->delete('gone.mp3'));
    }

    #[DataProvider('traversalNames')]
    public function test_path_traversal_is_rejected(string $maliciousName): void
    {
        $storage = $this->storage();
        // Seed a known file so a successful traversal could otherwise resolve.
        $storage->store($this->tempSource(), 'safe.mp3');

        $this->assertNull($storage->absolutePath($maliciousName));
        $this->assertFalse($storage->delete($maliciousName));
    }

    /** @return iterable<string, array{string}> */
    public static function traversalNames(): iterable
    {
        yield 'parent traversal' => ['../safe.mp3'];
        yield 'deep traversal' => ['../../etc/passwd'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'backslash' => ['..\\safe.mp3'];
        yield 'dot dot' => ['..'];
        yield 'nested slash' => ['sub/safe.mp3'];
    }
}
