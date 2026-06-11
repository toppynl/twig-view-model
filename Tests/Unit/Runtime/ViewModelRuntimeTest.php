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
use Toppy\TwigViewModel\ViewModelResult;

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

        static::assertInstanceOf(ViewModelResult::class, $result);
        static::assertSame($data, $result->data);
        static::assertNull($result->error);
    }

    public function testViewReturnsNullDataAndNullErrorOnNoDataException(): void
    {
        $manager = $this->createStub(ViewModelManagerInterface::class);
        $manager->method('get')->willThrowException(new NoDataException('App\\ViewModel\\Test'));

        $runtime = new ViewModelRuntime($manager);

        /** @var class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>> $class */
        $class = 'App\\ViewModel\\Test';
        $result = $runtime->view([], $class);

        static::assertNull($result->data);
        static::assertNull($result->error);
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

        static::assertNull($result->data);
        static::assertInstanceOf(ViewModelError::class, $result->error);
        static::assertSame('RESOLUTION_FAILED', $result->error->code);
        static::assertSame('API timeout', $result->error->message);
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

        static::assertNull($result->data);
        static::assertInstanceOf(ViewModelError::class, $result->error);
        static::assertSame('UNKNOWN', $result->error->code);
    }

    public function testViewReturnsNoDataResultWhenLazyProxyResolutionHasNoData(): void
    {
        // The real (non-decorated) manager returns an UNINITIALIZED lazy
        // proxy: truthy, with the failure only thrown on first property
        // access inside the template — escaping view()'s catch entirely.
        // view() must surface NoData as {data: null, error: null} here, not
        // as a mid-render exception.
        $manager = $this->createRealManager(static function (): object {
            throw new NoDataException(LazyRuntimeStubViewModel::class, 'nothing to show');
        });
        $manager->preload(LazyRuntimeStubViewModel::class);

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], LazyRuntimeStubViewModel::class);

        static::assertNull($result->data, 'NoData must yield null data, not a truthy uninitialized proxy');
        static::assertNull($result->error);
    }

    public function testViewReturnsErrorWhenLazyProxyResolutionFails(): void
    {
        $manager = $this->createRealManager(static function (): object {
            throw new \RuntimeException('CMS exploded');
        });
        $manager->preload(LazyRuntimeStubViewModel::class);

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], LazyRuntimeStubViewModel::class);

        static::assertNull($result->data, 'A failed resolution must yield null data, not a truthy proxy');
        static::assertInstanceOf(ViewModelError::class, $result->error);
    }

    public function testViewReturnsDataFromLazyProxyOnSuccess(): void
    {
        $manager = $this->createRealManager(static function (): object {
            $data = new LazyRuntimeStubData();
            $data->name = 'resolved';
            return $data;
        });
        $manager->preload(LazyRuntimeStubViewModel::class);

        $runtime = new ViewModelRuntime($manager);

        $result = $runtime->view([], LazyRuntimeStubViewModel::class);

        static::assertInstanceOf(LazyRuntimeStubData::class, $result->data);
        static::assertSame('resolved', $result->data->name);
        static::assertNull($result->error);
    }

    /**
     * Real ViewModelManager (lazy-proxy path) wrapping a view model whose
     * async resolution runs the given factory.
     *
     * @param \Closure(): object $factory
     */
    private function createRealManager(\Closure $factory): \Toppy\AsyncViewModel\ViewModelManager
    {
        $viewModel = new LazyRuntimeStubViewModel($factory);

        $container = $this->createStub(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $contextResolver = $this->createStub(\Toppy\AsyncViewModel\Context\ContextResolverInterface::class);
        $contextResolver
            ->method('getViewContext')
            ->willReturn(\Toppy\AsyncViewModel\Context\ViewContext::create('EUR', 'en', false, false, null));
        $contextResolver
            ->method('getRequestContext')
            ->willReturn(\Toppy\AsyncViewModel\Context\RequestContext::create([], 'test'));

        return new \Toppy\AsyncViewModel\ViewModelManager(
            $container,
            new \Toppy\AsyncViewModel\Profiler\NullViewModelProfiler(),
            $contextResolver,
        );
    }
}

/** Data class for the lazy-proxy runtime tests. */
final class LazyRuntimeStubData
{
    public string $name = '';
}

/**
 * View model whose async resolution runs the given factory.
 *
 * @implements \Toppy\AsyncViewModel\AsyncViewModel<LazyRuntimeStubData>
 *
 * @mago-expect analysis:less-specific-nested-return-statement
 *
 * The factory closure deliberately returns `object` so the same stub can
 * throw or return data per test.
 */
final class LazyRuntimeStubViewModel implements \Toppy\AsyncViewModel\AsyncViewModel
{
    public function __construct(
        private readonly \Closure $factory,
    ) {}

    /**
     * @return Future<LazyRuntimeStubData>
     */
    #[\Override]
    public function resolve(
        \Toppy\AsyncViewModel\Context\ViewContext $viewContext,
        \Toppy\AsyncViewModel\Context\RequestContext $requestContext,
    ): \Amp\Future {
        return \Amp\async($this->factory);
    }
}
