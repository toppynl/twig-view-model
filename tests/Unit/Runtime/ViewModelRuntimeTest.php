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

final class ViewModelRuntimeTest extends TestCase
{
    public function testViewReturnsDataWhenResolved(): void
    {
        $data = new \stdClass();
        $data->value = 'test';

        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::complete($data));

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], 'App\\ViewModel\\Test');

        $this->assertSame($data, $result);
    }

    public function testViewReturnsNullOnNoDataException(): void
    {
        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::error(new NoDataException('App\\ViewModel\\Test')));

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], 'App\\ViewModel\\Test');

        $this->assertNull($result);
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

    public function testViewRethrowsViewModelResolutionException(): void
    {
        $manager = $this->createMock(ViewModelManagerInterface::class);
        $manager->method('preloadWithFuture')
            ->willReturn(Future::error(new ViewModelResolutionException(
                viewModelClass: 'App\\ViewModel\\Test',
                message: 'API timeout',
            )));

        $runtime = new ViewModelRuntime($manager);

        $this->expectException(ViewModelResolutionException::class);
        $this->expectExceptionMessage('API timeout');

        $runtime->view([], 'App\\ViewModel\\Test');
    }
}
