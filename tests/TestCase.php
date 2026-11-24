<?php

declare(strict_types=1);

namespace Patylook\Tests;

use Throwable;

/** Base class of the tests: public methods named test* are run, with assertion helpers. */
abstract class TestCase
{
    public int $assertions = 0;

    /** Runs before each test method. */
    protected function setUp(): void
    {
    }

    /** Runs after each test method, even when it failed. */
    protected function tearDown(): void
    {
    }

    /** Called by the runner. @return array{passed: int, failed: list<string>, skipped: list<string>} */
    public function run(): array
    {
        $result = ['passed' => 0, 'failed' => [], 'skipped' => []];
        foreach (get_class_methods($this) as $method) {
            if (!str_starts_with($method, 'test')) {
                continue;
            }
            $name = static::class . '::' . $method;
            try {
                $this->setUp();
                $this->$method();
                $result['passed']++;
            } catch (SkipTest $e) {
                $result['skipped'][] = $name . ' (' . $e->getMessage() . ')';
            } catch (Throwable $e) {
                $result['failed'][] = $name . ': ' . $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']';
            } finally {
                try {
                    $this->tearDown();
                } catch (Throwable) {
                }
            }
        }
        return $result;
    }

    protected function skip(string $reason): never
    {
        throw new SkipTest($reason);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(sprintf(
                '%sesperado %s, obtido %s',
                $message === '' ? '' : $message . ': ',
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    }

    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        $this->assertions++;
        if ($condition !== true) {
            throw new AssertionFailed($message === '' ? 'esperado true' : $message);
        }
    }

    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        $this->assertions++;
        if ($condition !== false) {
            throw new AssertionFailed($message === '' ? 'esperado false' : $message);
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(($message === '' ? '' : $message . ': ') . sprintf('"%s" não encontrado em "%s"', $needle, mb_substr($haystack, 0, 300)));
        }
    }

    /**
     * @template T of Throwable
     * @param class-string<T> $class
     * @return T
     */
    protected function assertThrows(string $class, callable $fn, string $message = ''): Throwable
    {
        $this->assertions++;
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $class) {
                return $e;
            }
            throw new AssertionFailed(($message === '' ? '' : $message . ': ') . sprintf('esperada %s, lançada %s (%s)', $class, $e::class, $e->getMessage()));
        }
        throw new AssertionFailed(($message === '' ? '' : $message . ': ') . sprintf('esperada %s, nada foi lançado', $class));
    }
}
