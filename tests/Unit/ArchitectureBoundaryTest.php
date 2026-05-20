<?php

declare(strict_types=1);

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class ArchitectureBoundaryTest extends TestCase
{
    public function test_domain_does_not_depend_on_outer_layers(): void
    {
        $this->assertNoForbiddenImports('src/Domain', [
            'Application\\',
            'App\\',
            'Illuminate\\',
            'Infrastructure\\',
        ]);
    }

    public function test_application_does_not_depend_on_framework_or_infrastructure(): void
    {
        $this->assertNoForbiddenImports('src/Application', [
            'App\\',
            'Illuminate\\',
            'Infrastructure\\',
        ]);
    }

    /**
     * @param  array<int, string>  $forbiddenImports
     */
    private function assertNoForbiddenImports(string $directory, array $forbiddenImports): void
    {
        $violations = [];

        foreach ($this->phpFiles(base_path($directory)) as $file) {
            $imports = $this->imports($file);

            foreach ($imports as $import) {
                foreach ($forbiddenImports as $forbiddenImport) {
                    if (str_starts_with($import, $forbiddenImport)) {
                        $violations[] = sprintf(
                            '%s imports %s',
                            str_replace(base_path().'/', '', $file->getPathname()),
                            $import,
                        );
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * @return iterable<int, SplFileInfo>
     */
    private function phpFiles(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function imports(SplFileInfo $file): array
    {
        preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($file->getPathname()), $matches);

        return $matches[1] ?? [];
    }
}
