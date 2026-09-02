<?php
declare(strict_types=1);

namespace Crustum\Speculum\Enum;

/**
 * Speculum entry types matching the Vue UI resources and storage type column.
 */
enum EntryType: string
{
    case Ai = 'ai';

    case Authorization = 'authorization';

    case Batch = 'batch';

    case BlazeCastDelivery = 'bc_delivery';

    case BlazeCastMessage = 'bc_message';

    case BlazeCastConnection = 'bc_connection';

    case Broadcast = 'broadcast';

    case Cache = 'cache';

    case CakeDCAuth = 'cakedc_auth';

    case Command = 'command';

    case Event = 'event';

    case Exception = 'exception';

    case Explorator = 'explorator';

    case HttpClient = 'http_client';

    case Job = 'job';

    case Log = 'log';

    case Mail = 'mail';

    case Model = 'model';

    case Mongo = 'mongo';

    case MongoQuery = 'mongo_query';

    case MongoQueryLog = 'mongo_query_log';

    case Notification = 'notification';

    case Query = 'query';

    case Redis = 'redis';

    case Request = 'request';

    case ScheduledTask = 'schedule';

    case VarDump = 'vardump';

    case View = 'view';
}
