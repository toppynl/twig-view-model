<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\ViewModelResolutionException;
use Toppy\TwigViewModel\ViewModelError;

final class ViewModelErrorTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $error = new ViewModelError(
            code: 'NOT_FOUND',
            message: 'Product not found',
        );

        $this->assertSame('NOT_FOUND', $error->code);
        $this->assertSame('Product not found', $error->message);
        $this->assertNull($error->context);
    }

    public function testConstructorWithContext(): void
    {
        $error = new ViewModelError(
            code: 'RATE_LIMITED',
            message: 'Too many requests',
            context: ['retryAfter' => 30],
        );

        $this->assertSame(['retryAfter' => 30], $error->context);
    }

    public function testJsonSerialize(): void
    {
        $error = new ViewModelError(
            code: 'TIMEOUT',
            message: 'Request timed out',
            context: ['elapsed' => 5000],
        );

        $json = $error->jsonSerialize();

        $this->assertSame([
            'code' => 'TIMEOUT',
            'message' => 'Request timed out',
            'context' => ['elapsed' => 5000],
        ], $json);
    }

    public function testFromExceptionWithViewModelResolutionException(): void
    {
        $exception = new ViewModelResolutionException(
            viewModelClass: 'App\\ViewModel\\Stock',
            message: 'API call failed',
        );

        $error = ViewModelError::fromException($exception);

        $this->assertSame('RESOLUTION_FAILED', $error->code);
        $this->assertSame('API call failed', $error->message);
    }

    public function testFromExceptionWithHttpException(): void
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Product not found');

        $error = ViewModelError::fromException($exception);

        $this->assertSame('NOT_FOUND', $error->code);
        $this->assertSame('Product not found', $error->message);
    }

    public function testFromExceptionWithTimeoutException(): void
    {
        $exception = new \Symfony\Component\HttpClient\Exception\TimeoutException('Connection timed out');

        $error = ViewModelError::fromException($exception);

        $this->assertSame('TIMEOUT', $error->code);
    }

    public function testFromExceptionWithGenericException(): void
    {
        $exception = new \RuntimeException('Something broke');

        $error = ViewModelError::fromException($exception);

        $this->assertSame('UNKNOWN', $error->code);
        $this->assertSame('Something broke', $error->message);
    }
}
