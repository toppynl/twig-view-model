<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\NoDataException;
use Toppy\AsyncViewModel\Exception\ViewModelNotPreloadedException;
use Toppy\AsyncViewModel\Exception\ViewModelResolutionException;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigViewModel\Twig\Runtime\ViewModelRuntime;
use Toppy\TwigViewModel\ViewModelError;

/** Tests for ViewModelRuntime */
final class ViewModelRuntimeTest extends TestCase
{
    public function testViewReturnsDataAndNullError(): void
    {
        $data = new \stdClass();
        $data->value = 'test';

        $manager = $this->createStub(ViewModelManagerInterface::class);
        $manager->method('get')->willReturn($data);

        $runtime = new ViewModelRuntime($manager);

        /** @var class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>> $class */
        $class = 'App\\ViewModel\\Test';
        $result = $runtime->view([], $class);

        static::assertCount(2, $result);
        static::assertSame($data, $result[0]);
        static::assertNull($result[1]);
    }

    public function testViewReturnsNullDataAndNullErrorOnNoDataException(): void
    {
        $manager = $this->createStub(ViewModelManagerInterface::class);
        $manager->method('get')->willThrowException(new NoDataException('App\\ViewModel\\Test'));

        $runtime = new ViewModelRuntime($manager);

        /** @var class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>> $class */
        $class = 'App\\ViewModel\\Test';
        $result = $runtime->view([], $class);

        static::assertNull($result[0]);
        static::assertNull($result[1]);
    }

    public function testViewReturnsNullDataAndErrorOnResolutionException(): void
    {
        $manager = $this->createStub(ViewModelManagerInterface::class);
        $manager
            ->method('get')
            ->willThrowException(new ViewModelResolutionException(
                viewModelClass: 'App\\ViewModel\\Test',
                message: 'API timeout',
            ));

        $runtime = new ViewModelRuntime($manager);

        /** @var class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>> $class */
        $class = 'App\\ViewModel\\Test';
        $result = $runtime->view([], $class);

        static::assertNull($result[0]);
        static::assertInstanceOf(ViewModelError::class, $result[1]);
        static::assertSame('RESOLUTION_FAILED', $result[1]->code);
        static::assertSame('API timeout', $result[1]->message);
    }

    public function testViewRethrowsViewModelNotPreloadedException(): void
    {
        $manager = $this->createStub(ViewModelManagerInterface::class);
        $manager->method('get')->willThrowException(new ViewModelNotPreloadedException('App\\ViewModel\\Test'));

        $runtime = new ViewModelRuntime($manager);

        static::expectException(ViewModelNotPreloadedException::class);

        /** @var class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>> $class */
        $class = 'App\\ViewModel\\Test';
        $runtime->view([], $class);
    }

    public function testViewReturnsErrorForGenericException(): void
    {
        $manager = $this->createStub(ViewModelManagerInterface::class);
        $manager->method('get')->willThrowException(new \RuntimeException('Something broke'));

        $runtime = new ViewModelRuntime($manager);

        /** @var class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>> $class */
        $class = 'App\\ViewModel\\Test';
        $result = $runtime->view([], $class);

        static::assertNull($result[0]);
        static::assertInstanceOf(ViewModelError::class, $result[1]);
        static::assertSame('UNKNOWN', $result[1]->code);
    }
}
