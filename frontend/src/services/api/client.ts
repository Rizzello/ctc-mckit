export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status?: number,
    public readonly validation?: Record<string, string[]>,
  ) {
    super(message);
  }
}

function csrfToken(): string | undefined {
  return document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];
}

async function ensureCsrfCookie(): Promise<void> {
  if (csrfToken()) {
    return;
  }

  await fetch('/csrf-cookie', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  try {
    if (init.method && init.method !== 'GET') {
      await ensureCsrfCookie();
    }

    const response = await fetch(`/api/v1${path}`, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(init.method && init.method !== 'GET'
          ? {
              'Content-Type': 'application/json',
              'X-XSRF-TOKEN': decodeURIComponent(csrfToken() ?? ''),
            }
          : {}),
        ...init.headers,
      },
      ...init,
    });
    if (response.status === 204) return undefined as T;
    const body: unknown = await response.json();
    if (!response.ok) {
      const error = body as { message?: string; errors?: Record<string, string[]> };
      throw new ApiError(
        error.message ?? 'The request could not be completed.',
        response.status,
        error.errors,
      );
    }
    return body as T;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    throw new ApiError('The network connection is unavailable.');
  }
}
