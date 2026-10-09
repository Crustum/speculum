import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h, reactive } from 'vue';
import { ALERT_KEY } from '../composables/useAlert';
import { useAbortablePolling } from '../composables/useAbortablePolling';

function mountPolling() {
    const alertState = reactive({ type: '', autoClose: true, message: '' });
    let polling = null;

    const Harness = defineComponent({
        setup() {
            polling = useAbortablePolling();

            return () => h('div');
        },
    });

    const wrapper = mount(Harness, {
        global: {
            provide: {
                [ALERT_KEY]: alertState,
            },
        },
    });

    return { alertState, polling, wrapper };
}

function networkError() {
    const error = new Error('Network Error');

    return error;
}

function serverError(status = 500) {
    const error = new Error('Request failed with status code ' + status);
    error.response = { status };

    return error;
}

describe('useAbortablePolling.mayRetry', () => {
    it('rejects retries when the signal is already aborted', () => {
        const { polling } = mountPolling();
        polling.abortRequests();

        expect(polling.mayRetry(networkError(), polling.signal())).toBe(false);
    });

    it('rejects retries and alerts when the server answered', () => {
        const { alertState, polling } = mountPolling();

        expect(polling.mayRetry(serverError(419), polling.signal())).toBe(false);
        expect(alertState.type).toBe('error');
        expect(alertState.autoClose).toBe(false);
        expect(alertState.message).toContain('Speculum stopped listening for new entries.');
        expect(alertState.message).toContain('419');
    });

    it('allows retries for network failures without alerting', () => {
        const { alertState, polling } = mountPolling();

        expect(polling.mayRetry(networkError(), polling.signal())).toBe(true);
        expect(alertState.message).toBe('');
    });
});

describe('useAbortablePolling controller lifecycle', () => {
    it('resets to a live controller after aborting the previous one', () => {
        const { polling } = mountPolling();
        const previous = polling.signal();

        polling.resetRequests();

        expect(previous.aborted).toBe(true);
        expect(polling.signal()).not.toBe(previous);
        expect(polling.signal().aborted).toBe(false);
    });

    it('aborts in-flight requests on unmount', () => {
        const { polling, wrapper } = mountPolling();
        const active = polling.signal();

        wrapper.unmount();

        expect(active.aborted).toBe(true);
    });
});
