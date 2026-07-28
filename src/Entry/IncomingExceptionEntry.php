<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry;

use Override;
use Throwable;

/**
 * Incoming exception entry with family hash support.
 */
class IncomingExceptionEntry extends IncomingEntry
{
    /**
     * Underlying throwable for this exception entry.
     *
     * @var \Throwable
     */
    public Throwable $exception;

    /**
     * Create an incoming exception entry.
     *
     * @param \Throwable $exception Underlying exception.
     * @param array<string, mixed> $content Entry content.
     */
    public function __construct(Throwable $exception, array $content)
    {
        $this->exception = $exception;
        parent::__construct($content);
    }

    /**
     * Create a new incoming exception entry instance.
     *
     * @param mixed ...$arguments Constructor arguments.
     * @return static
     */
    #[Override]
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Determine whether this entry is a reportable exception.
     *
     * @return bool
     */
    #[Override]
    public function isReportableException(): bool
    {
        return true;
    }

    /**
     * Determine whether this entry is an exception.
     *
     * @return bool
     */
    #[Override]
    public function isException(): bool
    {
        return true;
    }

    /**
     * Build a family hash from the exception file and line.
     *
     * @return string|null
     */
    #[Override]
    public function familyHash(): ?string
    {
        return md5(($this->content['file'] ?? '') . ($this->content['line'] ?? ''));
    }
}
