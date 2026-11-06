<?php

namespace Omnibridge\Doctrine\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The package's two parts (omnisense/omnibase's plan "omnibase agnostic", step F3):
 * the generic one (src/ outside src/Omnibase), which any framework reads and which
 * names nothing of omnibase, and omnibase's (src/Omnibase, Omnibridge\Doctrine\Omnibase\),
 * moved from the core and loaded only with it - each of its files declares the class its
 * path names, in the namespace its directory names.
 */
final class OmnibasePartTest extends TestCase
{
    public function testTheGenericPartNamesNothingOfOmnibase(): void
    {
        $named = [];
        foreach (self::files(\dirname(__DIR__).'/src') as $relative => $code) {
            if (str_starts_with($relative, 'Omnibase/')) {
                continue;
            }
            // Code only: a docblock may say which omnibase class extends one of these.
            $tokens = array_filter(\PhpToken::tokenize($code), static fn (\PhpToken $token) => !$token->is([\T_COMMENT, \T_DOC_COMMENT]));
            if (preg_match('/\bBase\\\\/', implode('', array_map(static fn (\PhpToken $token) => $token->text, $tokens)))) {
                $named[] = $relative;
            }
        }

        self::assertSame([], $named);
    }

    public function testEachFileOfOmnibasesPartDeclaresTheClassItsPathNames(): void
    {
        $files = self::files(\dirname(__DIR__).'/src/Omnibase');
        self::assertNotEmpty($files);

        $wrong = [];
        foreach ($files as $relative => $code) {
            $expected = 'Omnibridge\\Doctrine\\Omnibase\\'.str_replace('/', '\\', substr($relative, 0, -4));
            preg_match('/^namespace ([^;]+);/m', $code, $namespace);
            // Indented where the declaration is conditional (Repository\User\CoreComplaints: the core's interface where it has one).
            preg_match('/^[ ]*(?:(?:abstract|final|readonly) )*(?:class|interface|trait|enum) (\w+)/m', $code, $class);
            $declared = ($namespace[1] ?? '').'\\'.($class[1] ?? '');
            if ($declared !== $expected) {
                $wrong[] = $relative.' declares '.$declared;
            }
        }

        self::assertSame([], $wrong);
    }

    /** @return array<string, string> path relative to $dir => code */
    private static function files(string $dir): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                $files[substr($file->getPathname(), \strlen($dir) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }
}
