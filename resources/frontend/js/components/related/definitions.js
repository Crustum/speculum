import BatchesTable from './BatchesTable.vue';
import BlazeCastTable from './BlazeCastTable.vue';
import BroadcastsTable from './BroadcastsTable.vue';
import CacheTable from './CacheTable.vue';
import AiTable from './AiTable.vue';
import AuthorizationTable from './AuthorizationTable.vue';
import EventsTable from './EventsTable.vue';
import ExceptionsTable from './ExceptionsTable.vue';
import HttpClientsTable from './HttpClientsTable.vue';
import JobsTable from './JobsTable.vue';
import LogsTable from './LogsTable.vue';
import MailsTable from './MailsTable.vue';
import ModelsTable from './ModelsTable.vue';
import MongoTable from './MongoTable.vue';
import MongoQueriesTable from './MongoQueriesTable.vue';
import MongoQueryLogsTable from './MongoQueryLogsTable.vue';
import NotificationsTable from './NotificationsTable.vue';
import QueriesTable from './QueriesTable.vue';
import ScheduleTable from './ScheduleTable.vue';
import SearchesTable from './SearchesTable.vue';
import VarDumpsTable from './VarDumpsTable.vue';
import ViewsTable from './ViewsTable.vue';

/**
 * Related Entries tab definitions (sorted alphabetically by title at runtime).
 */
export const relatedTabDefinitions = [
    {
        type: 'ai',
        title: 'AI',
        component: AiTable,
        match: (item) => item.type === 'ai',
    },
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
        type: 'authorization',
        title: 'Authorization',
        component: AuthorizationTable,
        match: (item) => ['cakedc_auth', 'authorization'].includes(item.type),
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
        type: 'mongo_queries',
        title: 'Mongo Queries',
        component: MongoQueriesTable,
        match: (item) => item.type === 'mongo_query',
    },
    {
        type: 'mongo_query_logs',
        title: 'Mongo Query Logs',
        component: MongoQueryLogsTable,
        match: (item) => item.type === 'mongo_query_log',
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
        type: 'searches',
        title: 'Searches',
        component: SearchesTable,
        match: (item) => item.type === 'explorator',
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
