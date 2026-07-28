<?php
declare(strict_types=1);

namespace Queue\Model\Entity;

/**
 * Test stub for SoftFeature::DereuromarkQueue / DereuromarkJobWatcher tests.
 */
class QueuedJob
{
    public int|string|null $id = null;

    public ?string $job_task = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = null;

    public ?int $attempts = null;

    public ?string $job_group = null;

    /**
     * @param array<string, mixed> $properties Entity properties.
     */
    public function __construct(array $properties = [])
    {
        foreach ($properties as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            $this->{$key} = $value;
        }
    }
}
