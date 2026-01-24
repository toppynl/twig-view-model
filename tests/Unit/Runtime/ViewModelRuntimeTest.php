<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Tests\Unit\Runtime;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\NoDataException;
use Toppy\AsyncViewModel\Exception\ViewModelNotPreloadedException;
use Toppy\AsyncViewModel\Exception\ViewModelResolutionException;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigViewModel\Twig\Runtime\ViewModelRuntime;
use Toppy\TwigViewModel\ViewModelError;

final class ViewModelRuntimeTest extends TestCase
{
    public function testViewReturnsDataAndNullError(): void
    {
        $data = new \stdClass();
        $data->value = 'test';

        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::complete($data));

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], 'App\\ViewModel\\Test');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($data, $result[0]);
        $this->assertNull($result[1]);
    }

    public function testViewReturnsNullDataAndNullErrorOnNoDataException(): void
    {
        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::error(new NoDataException('App\\ViewModel\\Test')));

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], 'App\\ViewModel\\Test');

        $this->assertNull($result[0]);
        $this->assertNull($result[1]);
    }

    public function testViewReturnsNullDataAndErrorOnResolutionException(): void
    {
        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::error(new ViewModelResolutionException(
                viewModelClass: 'App\\ViewModel\\Test',
                message: 'API timeout',
            )));

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], 'App\\ViewModel\\Test');

        $this->assertNull($result[0]);
        $this->assertInstanceOf(ViewModelError::class, $result[1]);
        $this->assertSame('RESOLUTION_FAILED', $result[1]->code);
        $this->assertSame('API timeout', $result[1]->message);
    }

    public function testViewRethrowsViewModelNotPreloadedException(): void
    {
        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willThrowException(new ViewModelNotPreloadedException('App\\ViewModel\\Test'));

        $runtime = new ViewModelRuntime($manager);

        $this->expectException(ViewModelNotPreloadedException::class);

        $runtime->view([], 'App\\ViewModel\\Test');
    }

    public function testViewReturnsErrorForGenericException(): void
    {
        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::error(new \RuntimeException('Something broke')));

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], 'App\\ViewModel\\Test');

        $this->assertNull($result[0]);
        $this->assertInstanceOf(ViewModelError::class, $result[1]);
        $this->assertSame('UNKNOWN', $result[1]->code);
    }
}
