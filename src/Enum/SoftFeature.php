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

    case Ai;

    case Authorization;

    case CakeDCAuth;

    case CrustumQueue;

    case DereuromarkQueue;

    case Explorator;

    case Mongo;

    case CrustumMongo;

    case Notification;

    case Queuesadilla;

    case Schedule;
}
