export function route(name: string, params?: Record<string, unknown>): string {
    return window.route(name, params ?? {}, false);
}
