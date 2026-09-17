/** Thin JSON fetch wrapper that attaches Laravel's CSRF cookie, for use outside Inertia's own form/visit cycle. */

export class ApiError extends Error {
    readonly status: number;
    readonly errors: Record<string, string[]>;

    constructor(status: number, body: { message?: string; errors?: Record<string, string[]> } | null) {
        super(body?.message ?? `Request failed with status ${status}`);
        this.status = status;
        this.errors = body?.errors ?? {};
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            ...options.headers,
        },
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new ApiError(response.status, body);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}
