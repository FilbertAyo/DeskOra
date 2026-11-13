export {};

declare global {
    interface Window {
        route: (name: string, params?: Record<string, unknown> | unknown[], absolute?: boolean) => string;
    }
}
