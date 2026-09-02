<?php
declare(strict_types=1);

namespace Crustum\Speculum\Event;

use Cake\Event\Event;

/**
 * Typed flush request dispatched by long-working host tools to persist
 * the Speculum entry queue ASAP (replaces direct `Speculum::store()` calls).
 *
 * @extends \Cake\Event\Event<object>
 */
final class SpeculumFlushEvent extends Event
{
    public const FLUSH = 'Speculum.flush';

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Event data; `throttled => true` defers to the worker flush policy.
     * @param object|null $subject Optional event subject.
     */
    public function __construct(array $data = [], ?object $subject = null)
    {
        parent::__construct(self::FLUSH, $subject, $data);
    }

    /**
     * Whether the flush should honor worker interval/limit instead of writing immediately.
     *
     * @return bool
     */
    public function isThrottled(): bool
    {
        return ($this->getData('throttled') ?? false) === true;
    }
}
