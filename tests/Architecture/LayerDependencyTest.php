<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lightweight, dependency-free guard: the inner layers must not reference any
 * framework or provider implementation code.
 *
 * Comments are stripped before scanning, so this checks real code references
 * (use statements, type hints, FQCNs) - not prose that happens to name a vendor.
 */
final class LayerDependencyTest extends TestCase
{
    private const array FORBIDDEN_IN_INNER_LAYERS = [
        'Symfony\\',
        'Doctrine\\',
        'Twig\\',
        'Stripe\\',
        'PayPal\\',
        'Psr\\Http',
        'GuzzleHttp\\',
    ];

    #[DataProvider('innerLayerFiles')]
    public function testInnerLayerFileHasNoForbiddenDependency(string $file, string $relativePath): void
    {
        $code = self::codeWithoutComments($file);

        foreach (self::FORBIDDEN_IN_INNER_LAYERS as $namespace) {
            self::assertStringNotContainsString(
                $namespace,
                $code,
                sprintf('%s must not depend on %s', $relativePath, rtrim($namespace, '\\')),
            );
        }
    }

    public function testDomainDoesNotDependOnOtherLayers(): void
    {
        foreach (self::phpFilesIn(self::src('Domain')) as $file) {
            $code = self::codeWithoutComments($file);
            self::assertStringNotContainsString('App\\Payment\\Application', $code, basename($file));
            self::assertStringNotContainsString('App\\Payment\\Infrastructure', $code, basename($file));
            self::assertStringNotContainsString('App\\Payment\\Presentation', $code, basename($file));
        }
    }

    public function testApplicationDoesNotDependOnInfrastructureOrPresentation(): void
    {
        foreach (self::phpFilesIn(self::src('Application')) as $file) {
            $code = self::codeWithoutComments($file);
            self::assertStringNotContainsString('App\\Payment\\Infrastructure', $code, basename($file));
            self::assertStringNotContainsString('App\\Payment\\Presentation', $code, basename($file));
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function innerLayerFiles(): iterable
    {
        foreach (['Domain', 'Application'] as $layer) {
            $root = self::src($layer);
            foreach (self::phpFilesIn($root) as $file) {
                $relative = $layer . str_replace($root, '', $file);
                yield $relative => [$file, $relative];
            }
        }
    }

    private static function codeWithoutComments(string $file): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /** @return list<string> */
    private static function phpFilesIn(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private static function src(string $layer): string
    {
        return \dirname(__DIR__, 2) . '/src/Payment/' . $layer;
    }
}
