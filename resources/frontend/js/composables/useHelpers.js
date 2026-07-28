import { getCurrentInstance } from 'vue';

let debounceTimer = null;

export function useHelpers() {
    function truncate(string, length = 70) {
        if (!string) {
            return '';
        }

        const value = String(string);

        if (value.length <= length) {
            return value;
        }

        return value.slice(0, Math.max(0, length - 3)).replace(/,? +$/, '') + '...';
    }

    function debouncer(callback, wait = 500) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => callback(), wait);
    }

    function alertError(message) {
        const root = getCurrentInstance()?.proxy?.$root;

        if (!root?.alert) {
            return;
        }

        root.alert.type = 'error';
        root.alert.autoClose = false;
        root.alert.message = message;
    }

    function alertSuccess(message, autoClose) {
        const root = getCurrentInstance()?.proxy?.$root;

        if (!root?.alert) {
            return;
        }

        root.alert.type = 'success';
        root.alert.autoClose = autoClose;
        root.alert.message = message;
    }

    function alertConfirm(message, success, failure) {
        const root = getCurrentInstance()?.proxy?.$root;

        if (!root?.alert) {
            return;
        }

        root.alert.type = 'confirmation';
        root.alert.autoClose = false;
        root.alert.message = message;
        root.alert.confirmationProceed = success;
        root.alert.confirmationCancel = failure;
    }

    return {
        truncate,
        debouncer,
        alertError,
        alertSuccess,
        alertConfirm,
    };
}
