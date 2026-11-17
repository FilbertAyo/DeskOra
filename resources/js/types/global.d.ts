import type { AxiosInstance } from 'axios';

export {};

declare global {
    interface Window {
        axios: AxiosInstance;
        route: (name: string, params?: Record<string, unknown> | unknown[], absolute?: boolean) => string;
    }
}
