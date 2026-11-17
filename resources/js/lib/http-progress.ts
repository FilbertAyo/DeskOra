import { progress } from '@inertiajs/react';
import type { AxiosInstance, InternalAxiosRequestConfig } from 'axios';

type ProgressConfig = InternalAxiosRequestConfig & { skipProgress?: boolean };

/**
 * Drive Inertia's top progress bar for non-Inertia Axios / fetch traffic.
 * Inertia visits already animate the bar via createInertiaApp({ progress }).
 */
export function bindHttpProgress(axiosInstance: AxiosInstance): void {
    let pending = 0;

    const start = () => {
        if (pending === 0) {
            progress.start();
        }
        pending += 1;
    };

    const done = () => {
        pending = Math.max(0, pending - 1);
        if (pending === 0) {
            progress.finish();
        }
    };

    axiosInstance.interceptors.request.use((config: ProgressConfig) => {
        if (!config.skipProgress) {
            start();
        }
        return config;
    });

    axiosInstance.interceptors.response.use(
        (response) => {
            if (!(response.config as ProgressConfig).skipProgress) {
                done();
            }
            return response;
        },
        (error) => {
            if (!(error?.config as ProgressConfig | undefined)?.skipProgress) {
                done();
            }
            return Promise.reject(error);
        },
    );

    const nativeFetch = window.fetch.bind(window);
    window.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const headers = new Headers(init?.headers ?? (input instanceof Request ? input.headers : undefined));
        // Skip Inertia visits (they already drive the bar) and opt-outs.
        const skip =
            headers.get('X-Inertia') === 'true' ||
            headers.get('X-Skip-Progress') === '1' ||
            init?.keepalive === true;

        if (!skip) {
            start();
        }
        try {
            return await nativeFetch(input, init);
        } finally {
            if (!skip) {
                done();
            }
        }
    };
}
