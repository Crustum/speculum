import axios from 'axios';

function speculumBasePath() {
    const path = window.Speculum?.path ?? 'speculum';

    if (path === '' || path === '/') {
        return '';
    }

    return '/' + String(path).replace(/^\/+|\/+$/g, '');
}

export function apiBaseUrl() {
    return `${speculumBasePath()}/api`;
}

export function spaBasePath() {
    const base = speculumBasePath();

    return base === '' ? '/' : `${base}/`;
}

const token = document.head.querySelector('meta[name="csrf-token"]');

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

const api = axios.create({
    baseURL: apiBaseUrl(),
});

api.interceptors.request.use((config) => {
    config.baseURL = apiBaseUrl();

    return config;
});

export default api;
