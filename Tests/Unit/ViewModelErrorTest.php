<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\ViewModelResolutionException;
use Toppy\TwigViewModel\ViewModelError;

/** Tests for ViewModelError */
final class ViewModelErrorTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $error = new ViewModelError(code: 'NOT_FOUND', message: 'Product not found');

        static::assertSame('NOT_FOUND', $error->code);
        static::assertSame('Product not found', $error->message);
        static::assertNull($error->context);
    }

    public function testConstructorWithContext(): void
    {
        $error = new ViewModelError(code: 'RATE_LIMITED', message: 'Too many requests', context: ['retryAfter' => 30]);

        static::assertSame(['retryAfter' => 30], $error->context);
    }

    public function testJsonSerialize(): void
    {
        $error = new ViewModelError(code: 'TIMEOUT', message: 'Request timed out', context: ['elapsed' => 5000]);

        $json = $error->jsonSerialize();

        static::assertSame(
            [
                'code' => 'TIMEOUT',
                'message' => 'Request timed out',
                'context' => ['elapsed' => 5000],
            ],
            $json,
        );
    }

    public function testFromExceptionWithViewModelResolutionException(): void
    {
        $exception = new ViewModelResolutionException(
            viewModelClass: 'App\\ViewModel\\Stock',
            message: 'API call failed',
        );

        $error = ViewModelError::fromException($exception);

        static::assertSame('RESOLUTION_FAILED', $error->code);
        static::assertSame('API call failed', $error->message);
    }

    public function testFromExceptionWithHttpException(): void
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Product not found');

        $error = ViewModelError::fromException($exception);

        static::assertSame('NOT_FOUND', $error->code);
        static::assertSame('Product not found', $error->message);
    }

    public function testFromExceptionWithTimeoutException(): void
    {
        $exception = new \Symfony\Component\HttpClient\Exception\TimeoutException('Connection timed out');

        $error = ViewModelError::fromException($exception);

        static::assertSame('TIMEOUT', $error->code);
    }

    public function testFromExceptionWithGenericException(): void
    {
        $exception = new \RuntimeException('Something broke');

        $error = ViewModelError::fromException($exception);

        static::assertSame('UNKNOWN', $error->code);
        static::assertSame('Something broke', $error->message);
    }
}
