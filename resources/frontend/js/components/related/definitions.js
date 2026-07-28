import BatchesTable from './BatchesTable.vue';
import BlazeCastTable from './BlazeCastTable.vue';
import BroadcastsTable from './BroadcastsTable.vue';
import CacheTable from './CacheTable.vue';
import EventsTable from './EventsTable.vue';
import ExceptionsTable from './ExceptionsTable.vue';
import HttpClientsTable from './HttpClientsTable.vue';
import JobsTable from './JobsTable.vue';
import LogsTable from './LogsTable.vue';
import MailsTable from './MailsTable.vue';
import ModelsTable from './ModelsTable.vue';
import MongoTable from './MongoTable.vue';
import NotificationsTable from './NotificationsTable.vue';
import QueriesTable from './QueriesTable.vue';
import ScheduleTable from './ScheduleTable.vue';
import VarDumpsTable from './VarDumpsTable.vue';
import ViewsTable from './ViewsTable.vue';

/**
 * Related Entries tab definitions (sorted alphabetically by title at runtime).
 */
export const relatedTabDefinitions = [
    {
        type: 'batches',
        title: 'Batches',
        component: BatchesTable,
        match: (item) => item.type === 'batch',
    },
    {
        type: 'blazecast',
        title: 'BlazeCast',
        component: BlazeCastTable,
        match: (item) => ['bc_delivery', 'bc_message', 'bc_connection'].includes(item.type),
    },
    {
        type: 'broadcasts',
        title: 'Broadcasts',
        component: BroadcastsTable,
        match: (item) => item.type === 'broadcast',
    },
    {
        type: 'cache',
        title: 'Cache',
        component: CacheTable,
        match: (item) => item.type === 'cache',
    },
    {
        type: 'events',
        title: 'Events',
        component: EventsTable,
        match: (item) => item.type === 'event',
    },
    {
        type: 'exceptions',
        title: 'Exceptions',
        component: ExceptionsTable,
        match: (item) => item.type === 'exception',
    },
    {
        type: 'http_clients',
        title: 'HTTP Client',
        component: HttpClientsTable,
        match: (item) => item.type === 'http_client',
    },
    {
        type: 'jobs',
        title: 'Jobs',
        component: JobsTable,
        match: (item) => item.type === 'job',
    },
    {
        type: 'logs',
        title: 'Logs',
        component: LogsTable,
        match: (item) => item.type === 'log',
    },
    {
        type: 'mails',
        title: 'Mail',
        component: MailsTable,
        match: (item) => item.type === 'mail',
    },
    {
        type: 'models',
        title: 'Models',
        component: ModelsTable,
        match: (item) => item.type === 'model',
    },
    {
        type: 'mongo',
        title: 'Mongo',
        component: MongoTable,
        match: (item) => item.type === 'mongo',
    },
    {
        type: 'notifications',
        title: 'Notifications',
        component: NotificationsTable,
        match: (item) => item.type === 'notification',
    },
    {
        type: 'queries',
        title: 'Queries',
        component: QueriesTable,
        match: (item) => item.type === 'query',
    },
    {
        type: 'schedule',
        title: 'Schedule',
        component: ScheduleTable,
        match: (item) => item.type === 'schedule',
    },
    {
        type: 'vardumps',
        title: 'Var Dumps',
        component: VarDumpsTable,
        match: (item) => item.type === 'vardump',
    },
    {
        type: 'views',
        title: 'Views',
        component: ViewsTable,
        match: (item) => item.type === 'view',
    },
];
