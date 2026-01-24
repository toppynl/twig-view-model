<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel;

/**
 * Template-facing error representation for ViewModel resolution failures.
 *
 * Provides structured error information with codes for template-level handling.
 */
final class ViewModelError implements \JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?array $context = null,
    ) {}

    /**
     * Create error from an exception, mapping to appropriate error code.
     */
    public static function fromException(\Throwable $e): self
    {
        $code = match (true) {
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 'NOT_FOUND',
            $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException => 'FORBIDDEN',
            $e instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException => 'UNAUTHORIZED',
            $e instanceof \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException => 'SERVICE_UNAVAILABLE',
            $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException => 'RATE_LIMITED',
            $e instanceof \Symfony\Component\HttpClient\Exception\TimeoutException => 'TIMEOUT',
            $e instanceof \Toppy\AsyncViewModel\Exception\ViewModelResolutionException => 'RESOLUTION_FAILED',
            default => 'UNKNOWN',
        };

        $context = null;
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
            if ($retryAfter !== null) {
                $context = ['retryAfter' => (int) $retryAfter];
            }
        }

        return new self(
            code: $code,
            message: $e->getMessage(),
            context: $context,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
