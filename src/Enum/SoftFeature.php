<?php
declare(strict_types=1);

namespace Crustum\Speculum\Enum;

/**
 * Soft features Speculum can enable when the host provides them.
 */
enum SoftFeature
{
    case Batch;

    case BlazeCast;

    case Broadcasting;

    case CakeQueue;

    case CrustumQueue;

    case DereuromarkQueue;

    case Mongo;

    case Notification;

    case Queuesadilla;

    case Schedule;
}
