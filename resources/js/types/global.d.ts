import type { route as routeFn } from 'ziggy-js';
import { AxiosStatic } from 'axios';
import { Echo } from 'laravel-echo';

declare global {
    const route: typeof routeFn;

    interface Window {
        axios: AxiosStatic;
        Echo?: Echo;
        Pusher?: any;
    }
}
