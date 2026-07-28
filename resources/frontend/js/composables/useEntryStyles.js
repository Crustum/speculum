export function useEntryStyles() {
    function cacheActionTypeClass(type) {
        if (type === 'hit') {
            return 'success';
        }

        if (type === 'set') {
            return 'info';
        }

        if (type === 'forget') {
            return 'warning';
        }

        if (type === 'missed') {
            return 'danger';
        }

        return 'secondary';
    }

    function composerTypeClass(type) {
        if (type === 'composer') {
            return 'info';
        }

        if (type === 'creator') {
            return 'success';
        }

        return 'secondary';
    }

    function gateResultClass(result) {
        if (result === 'allowed') {
            return 'success';
        }

        if (result === 'denied') {
            return 'danger';
        }

        return 'secondary';
    }

    function jobStatusClass(status) {
        if (status === 'pending') {
            return 'secondary';
        }

        if (status === 'processed') {
            return 'success';
        }

        if (status === 'failed') {
            return 'danger';
        }

        return 'secondary';
    }

    function logLevelClass(level) {
        if (level === 'debug') {
            return 'success';
        }

        if (level === 'info') {
            return 'info';
        }

        if (level === 'notice') {
            return 'secondary';
        }

        if (level === 'warning') {
            return 'warning';
        }

        if (
            level === 'error' ||
            level === 'critical' ||
            level === 'alert' ||
            level === 'emergency'
        ) {
            return 'danger';
        }

        return 'secondary';
    }

    function modelActionClass(action) {
        if (action === 'created') {
            return 'success';
        }

        if (action === 'updated') {
            return 'info';
        }

        if (action === 'retrieved') {
            return 'secondary';
        }

        if (action === 'deleted' || action === 'forceDeleted') {
            return 'danger';
        }

        return 'secondary';
    }

    function requestStatusClass(status) {
        if (!status) {
            return 'danger';
        }

        if (status < 300) {
            return 'success';
        }

        if (status < 400) {
            return 'info';
        }

        if (status < 500) {
            return 'warning';
        }

        return 'danger';
    }

    function requestMethodClass(method) {
        if (method === 'GET' || method === 'OPTIONS') {
            return 'secondary';
        }

        if (method === 'POST' || method === 'PATCH' || method === 'PUT') {
            return 'info';
        }

        if (method === 'DELETE') {
            return 'danger';
        }

        return 'secondary';
    }

    return {
        cacheActionTypeClass,
        composerTypeClass,
        gateResultClass,
        jobStatusClass,
        logLevelClass,
        modelActionClass,
        requestStatusClass,
        requestMethodClass,
    };
}
