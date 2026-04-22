<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel;

readonly class ViewModelResult
{
    public function __construct(
        public ?object $data,
        public ?ViewModelError $error,
    ) {}
}
