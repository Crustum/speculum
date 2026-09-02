<?php
declare(strict_types=1);

namespace Crustum\Speculum\Enum;

/**
 * Lifecycle status values stored on job entry content.
 */
enum JobStatus: string
{
    case Failed = 'failed';

    case Pending = 'pending';

    case Processed = 'processed';
}
