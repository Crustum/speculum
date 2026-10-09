import mail from './screens/mail/index.vue';
import mailPreview from './screens/mail/preview.vue';
import exceptions from './screens/exceptions/index.vue';
import exceptionsPreview from './screens/exceptions/preview.vue';
import logs from './screens/logs/index.vue';
import logsPreview from './screens/logs/preview.vue';
import vardumps from './screens/vardumps/index.vue';
import vardumpsPreview from './screens/vardumps/preview.vue';
import notifications from './screens/notifications/index.vue';
import notificationsPreview from './screens/notifications/preview.vue';
import jobs from './screens/jobs/index.vue';
import jobsPreview from './screens/jobs/preview.vue';
import batches from './screens/batches/index.vue';
import batchesPreview from './screens/batches/preview.vue';
import broadcasts from './screens/broadcasts/index.vue';
import broadcastsPreview from './screens/broadcasts/preview.vue';
import authorizationPreview from './screens/authorization/preview.vue';
import authorization from './screens/authorization/index.vue';
import events from './screens/events/index.vue';
import eventsPreview from './screens/events/preview.vue';
import ai from './screens/ai/index.vue';
import aiPreview from './screens/ai/preview.vue';
import aiInvocation from './screens/ai/invocation.vue';
import cache from './screens/cache/index.vue';
import cachePreview from './screens/cache/preview.vue';
import queries from './screens/queries/index.vue';
import queriesPreview from './screens/queries/preview.vue';
import models from './screens/models/index.vue';
import modelsPreview from './screens/models/preview.vue';
import mongo from './screens/mongo/index.vue';
import mongoPreview from './screens/mongo/preview.vue';
import mongoQueries from './screens/mongo-queries/index.vue';
import mongoQueriesPreview from './screens/mongo-queries/preview.vue';
import mongoQueryLogs from './screens/mongo-query-logs/index.vue';
import mongoQueryLogsPreview from './screens/mongo-query-logs/preview.vue';
import requests from './screens/requests/index.vue';
import requestsPreview from './screens/requests/preview.vue';
import commands from './screens/commands/index.vue';
import commandsPreview from './screens/commands/preview.vue';
import schedule from './screens/schedule/index.vue';
import schedulePreview from './screens/schedule/preview.vue';
import searches from './screens/searches/index.vue';
import searchesPreview from './screens/searches/preview.vue';
import monitoring from './screens/monitoring/index.vue';
import views from './screens/views/index.vue';
import viewsPreview from './screens/views/preview.vue';
import httpClients from './screens/http-clients/index.vue';
import httpClientsPreview from './screens/http-clients/preview.vue';
import blazecast from './screens/blazecast/index.vue';
import blazecastPreview from './screens/blazecast/preview.vue';

export default [
    { path: '/', redirect: '/requests' },
    { path: '/mail/:id', name: 'mail-preview', component: mailPreview },
    { path: '/mail', name: 'mail', component: mail },
    { path: '/exceptions/:id', name: 'exception-preview', component: exceptionsPreview },
    { path: '/exceptions', name: 'exceptions', component: exceptions },
    { path: '/logs/:id', name: 'log-preview', component: logsPreview },
    { path: '/logs', name: 'logs', component: logs },
    { path: '/vardumps/:id', name: 'vardump-preview', component: vardumpsPreview },
    { path: '/vardumps', name: 'vardumps', component: vardumps },
    { path: '/notifications/:id', name: 'notification-preview', component: notificationsPreview },
    { path: '/notifications', name: 'notifications', component: notifications },
    { path: '/jobs/:id', name: 'job-preview', component: jobsPreview },
    { path: '/jobs', name: 'jobs', component: jobs },
    { path: '/batches/:id', name: 'batch-preview', component: batchesPreview },
    { path: '/batches', name: 'batches', component: batches },
    { path: '/broadcasts/:id', name: 'broadcast-preview', component: broadcastsPreview },
    { path: '/broadcasts', name: 'broadcasts', component: broadcasts },
    { path: '/authorization/:id', name: 'authorization-preview', component: authorizationPreview },
    { path: '/authorization', name: 'authorization', component: authorization },
    { path: '/blazecast/:id', name: 'blazecast-preview', component: blazecastPreview },
    { path: '/blazecast', name: 'blazecast', component: blazecast },
    { path: '/blazecast-messages/:id', redirect: (to) => ({ name: 'blazecast-preview', params: { id: to.params.id } }) },
    { path: '/blazecast-messages', redirect: '/blazecast' },
    { path: '/blazecast-deliveries/:id', redirect: (to) => ({ name: 'blazecast-preview', params: { id: to.params.id } }) },
    { path: '/blazecast-deliveries', redirect: '/blazecast' },
    { path: '/events/:id', name: 'event-preview', component: eventsPreview },
    { path: '/events', name: 'events', component: events },
    { path: '/ai/:id', name: 'ai-preview', component: aiPreview },
    { path: '/ai-invocation/:id', name: 'ai-invocation', component: aiInvocation },
    { path: '/ai', name: 'ai', component: ai },
    { path: '/cache/:id', name: 'cache-preview', component: cachePreview },
    { path: '/cache', name: 'cache', component: cache },
    { path: '/queries/:id', name: 'query-preview', component: queriesPreview },
    { path: '/queries', name: 'queries', component: queries },
    { path: '/models/:id', name: 'model-preview', component: modelsPreview },
    { path: '/models', name: 'models', component: models },
    { path: '/mongo/:id', name: 'mongo-preview', component: mongoPreview },
    { path: '/mongo', name: 'mongo', component: mongo },
    { path: '/mongo-queries/:id', name: 'mongo-query-preview', component: mongoQueriesPreview },
    { path: '/mongo-queries', name: 'mongo-queries', component: mongoQueries },
    { path: '/mongo-query-logs/:id', name: 'mongo-query-log-preview', component: mongoQueryLogsPreview },
    { path: '/mongo-query-logs', name: 'mongo-query-logs', component: mongoQueryLogs },
    { path: '/requests/:id', name: 'request-preview', component: requestsPreview },
    { path: '/requests', name: 'requests', component: requests },
    { path: '/commands/:id', name: 'command-preview', component: commandsPreview },
    { path: '/commands', name: 'commands', component: commands },
    { path: '/schedule/:id', name: 'schedule-preview', component: schedulePreview },
    { path: '/schedule', name: 'schedule', component: schedule },
    { path: '/searches/:id', name: 'searches-preview', component: searchesPreview },
    { path: '/searches', name: 'searches', component: searches },
    { path: '/monitored-tags', name: 'monitored-tags', component: monitoring },
    { path: '/views/:id', name: 'view-preview', component: viewsPreview },
    { path: '/views', name: 'views', component: views },
    { path: '/http-clients/:id', name: 'http-client-preview', component: httpClientsPreview },
    { path: '/http-clients', name: 'http-clients', component: httpClients },
];
