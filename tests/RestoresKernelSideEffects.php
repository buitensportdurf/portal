<?php

namespace App\Tests;

/**
 * For kernel tests: puts back the error and exception handlers and PHP's default locale as they
 * were before the test. Booting the kernel installs handlers (more of them when it has to build
 * the cache first), and requests set the locale, which would leak into later tests.
 */
trait RestoresKernelSideEffects
{
    private mixed $errorHandler;
    private mixed $exceptionHandler;
    private string $phpLocale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorHandler = self::currentErrorHandler();
        $this->exceptionHandler = self::currentExceptionHandler();
        $this->phpLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Locale::setDefault($this->phpLocale);
        // Bounded, in case a handler was replaced rather than stacked
        for ($i = 0; $i < 10 && self::currentErrorHandler() !== $this->errorHandler; $i++) {
            restore_error_handler();
        }
        for ($i = 0; $i < 10 && self::currentExceptionHandler() !== $this->exceptionHandler; $i++) {
            restore_exception_handler();
        }
    }

    // get_error_handler() and get_exception_handler() only exist from PHP 8.5
    private static function currentErrorHandler(): mixed
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }

    private static function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
