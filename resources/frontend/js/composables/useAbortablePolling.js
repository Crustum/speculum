import { getCurrentInstance, onBeforeUnmount, ref } from 'vue';
import { useAlert } from './useAlert';

export function useAbortablePolling() {
    const { alertError } = useAlert();
    const requestController = ref(new AbortController());

    function signal() {
        return requestController.value.signal;
    }

    function abortRequests() {
        requestController.value.abort();
    }

    function resetRequests() {
        abortRequests();
        requestController.value = new AbortController();
    }

    function mayRetry(error, requestSignal) {
        const activeSignal = requestSignal ?? signal();

        if (activeSignal.aborted) {
            return false;
        }

        if (error?.response) {
            alertError(
                'Speculum stopped listening for new entries. The server returned a '
                    + error.response.status
                    + ' response.'
            );

            return false;
        }

        return true;
    }

    if (getCurrentInstance()) {
        onBeforeUnmount(abortRequests);
    }

    return {
        requestController,
        signal,
        abortRequests,
        resetRequests,
        mayRetry,
    };
}
