import { describe, expect, it, beforeEach, vi } from 'vitest';
import { currentUser, invalidateAuthLifecycle, StaleAuthValidationError } from './auth';

const { api } = vi.hoisted(() => ({ api: vi.fn() }));

vi.mock('./client', () => ({ api }));

describe('authentication validation lifecycle', () => {
  beforeEach(() => {
    api.mockReset();
    invalidateAuthLifecycle();
  });

  it('discards a stale /me response after the lifecycle is invalidated', async () => {
    let resolveUser: (value: { data: { id: number } }) => void = () => undefined;
    api.mockReturnValue(
      new Promise((resolve) => {
        resolveUser = resolve;
      }),
    );

    const request = currentUser();
    invalidateAuthLifecycle();
    resolveUser({ data: { id: 1 } });

    await expect(request).rejects.toBeInstanceOf(StaleAuthValidationError);
  });

  it('does not reuse an invalidated request for a new lifecycle', async () => {
    let resolveOldUser: (value: { data: { id: number } }) => void = () => undefined;
    api.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveOldUser = resolve;
      }),
    );
    const oldRequest = currentUser();

    invalidateAuthLifecycle();
    api.mockResolvedValueOnce({ data: { id: 2 } });
    const newRequest = currentUser();
    resolveOldUser({ data: { id: 1 } });

    await expect(oldRequest).rejects.toBeInstanceOf(StaleAuthValidationError);
    await expect(newRequest).resolves.toEqual({ data: { id: 2 } });
    expect(api).toHaveBeenCalledTimes(2);
  });
});
