import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';
import 'virtual:speculum-extensions';
import Routes from './routes';
import { getExtensionRoutes } from './extensions/registry';
import AppShell from './App.vue';
import RelatedEntries from './components/RelatedEntries.vue';
import IndexScreen from './components/IndexScreen.vue';
import PreviewScreen from './components/PreviewScreen.vue';
import Alert from './components/Alert.vue';
import CopyClipboard from './components/CopyClipboard.vue';
import Stacktrace from './components/Stacktrace.vue';
import ExceptionCodePreview from './components/ExceptionCodePreview.vue';
import SchemeToggler from './components/SchemeToggler.vue';
import AttributeRow from './components/AttributeRow.vue';
import EntryLink from './components/EntryLink.vue';
import EntryTitle from './components/EntryTitle.vue';
import TimeAgoCell from './components/TimeAgoCell.vue';
import ViewLinkCell from './components/ViewLinkCell.vue';
import StatusBadge from './components/StatusBadge.vue';
import FlagBadge from './components/FlagBadge.vue';
import MutedTextCell from './components/MutedTextCell.vue';
import { spaBasePath } from './utils/api';

window.Speculum = window.Speculum || { path: 'speculum', timezone: 'UTC', recording: true, root: '' };
window.Speculum.basePath = spaBasePath().replace(/\/$/, '') || '';

const router = createRouter({
    history: createWebHistory(spaBasePath()),
    routes: [...Routes, ...getExtensionRoutes()],
});

const app = createApp(AppShell);

app.config.globalProperties.Speculum = window.Speculum;

app.use(router);

app.component('VueJsonPretty', VueJsonPretty);
app.component('RelatedEntries', RelatedEntries);
app.component('IndexScreen', IndexScreen);
app.component('PreviewScreen', PreviewScreen);
app.component('Alert', Alert);
app.component('CopyClipboard', CopyClipboard);
app.component('StackTrace', Stacktrace);
app.component('ExceptionCodePreview', ExceptionCodePreview);
app.component('SchemeToggler', SchemeToggler);
app.component('AttributeRow', AttributeRow);
app.component('EntryLink', EntryLink);
app.component('EntryTitle', EntryTitle);
app.component('TimeAgoCell', TimeAgoCell);
app.component('ViewLinkCell', ViewLinkCell);
app.component('StatusBadge', StatusBadge);
app.component('FlagBadge', FlagBadge);
app.component('MutedTextCell', MutedTextCell);

app.mount('#speculum');
