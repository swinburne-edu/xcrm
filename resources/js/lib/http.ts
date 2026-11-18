/**
 * Small JSON helper for endpoints that are not Inertia pages (the timeline). Sends the Laravel XSRF cookie
 * back as X-XSRF-TOKEN. Rejects with { status, errors } so forms can show validation messages.
 */
export type HttpError = { status: number; message: string; errors: Record<string, string> };

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function requestJson<T>(method: 'GET' | 'POST', url: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method === 'POST' ? { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() } : {}),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}) as Record<string, unknown>);
        const raw = (payload.errors ?? {}) as Record<string, string[]>;
        const errors = Object.fromEntries(Object.entries(raw).map(([key, messages]) => [key, messages[0] ?? '']));

        throw { status: response.status, message: (payload.message as string) ?? 'Có lỗi xảy ra.', errors } satisfies HttpError;
    }

    return (await response.json()) as T;
}
