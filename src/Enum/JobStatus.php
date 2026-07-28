<?php
declare(strict_types=1);

namespace Crustum\Speculum\Enum;

/**
 * Lifecycle status values stored on job entry content.
 */
enum JobStatus: string
{
    case Pending = 'pending';

    case Processed = 'processed';

    case Failed = 'failed';
}
