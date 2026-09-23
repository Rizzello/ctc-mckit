import 'fake-indexeddb/auto';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { db } from '@/db';
import { useConferenceStore } from '@/stores/conference';
import { useConnectivityStore } from '@/stores/connectivity';
import { ApiError } from '@/services/api/client';
import type { Snapshot } from '@/types/models';

const { authState, currentUser, fetchSnapshot } = vi.hoisted(() => {
  const authState = { generation: 0 };

  return {
    authState,
    currentUser: vi.fn(),
    fetchSnapshot: vi.fn(),
    getAuthLifecycleGeneration: vi.fn(() => authState.generation),
    isAuthLifecycleCurrent: vi.fn((generation: number) => generation === authState.generation),
    invalidateAuthLifecycle: vi.fn(() => {
      authState.generation += 1;
    }),
  };
});

vi.mock('@/services/api/snapshot', () => ({ fetchSnapshot }));
vi.mock('@/services/api/auth', () => ({
  currentUser,
  getAuthLifecycleGeneration: () => authState.generation,
  invalidateAuthLifecycle: () => {
    authState.generation += 1;
  },
  isAuthLifecycleCurrent: (generation: number) => generation === authState.generation,
  StaleAuthValidationError: class extends Error {},
}));

function snapshot(ownerId: number, version = 'version-1'): Snapshot {
  return {
    version,
    generated_at: '2027-10-14T09:00:00+02:00',
    current_user: {
      id: ownerId,
      name: `MC ${ownerId}`,
      email: `mc-${ownerId}@example.test`,
      is_admin: false,
      enabled: true,
    },
    rooms: [{ id: 1, sessionize_id: 'room-1', name: 'Main room' }],
    speakers: [
      {
        id: 1,
        sessionize_id: 'speaker-1',
        name: 'Speaker One',
        tagline: null,
        bio: null,
        photo_url: null,
        links: [],
      },
    ],
    sessions: [
      {
        id: 1,
        sessionize_id: 'session-1',
        title: 'Opening session',
        description: null,
        room_id: 1,
        starts_at: '2027-10-14T09:00:00+02:00',
        ends_at: '2027-10-14T10:00:00+02:00',
        status: 'confirmed',
        is_confirmed: true,
        is_service_session: false,
        is_plenum_session: false,
        categories: ['Keynote'],
        mc_description: null,
        mc_script: null,
        room: { id: 1, sessionize_id: 'room-1', name: 'Main room' },
        speakers: [],
        mcs: [],
        notes: [],
      },
    ],
  };
}

describe('conference store', () => {
  beforeEach(async () => {
    setActivePinia(createPinia());
    await db.snapshots.clear();
    fetchSnapshot.mockReset();
    currentUser.mockReset();
    authState.generation = 0;
    currentUser.mockResolvedValue({
      data: {
        id: 1,
        name: 'MC 1',
        email: 'mc-1@example.test',
        is_admin: false,
        enabled: true,
      },
    });
    useConnectivityStore().online = true;
  });

  afterEach(async () => {
    await db.snapshots.clear();
  });

  it('hydrates the durable snapshot before attempting a network refresh', async () => {
    const storedSnapshot = snapshot(1);
    await db.snapshots.put({
      key: 'active',
      ownerId: 1,
      version: storedSnapshot.version,
      generatedAt: storedSnapshot.generated_at,
      payload: storedSnapshot,
    });

    const conference = useConferenceStore();
    await conference.hydrate();

    expect(conference.currentUser?.id).toBe(1);
    expect(conference.ready).toBe(true);
    expect(conference.noOfflineSnapshot).toBe(false);
  });

  it('preserves the previous durable snapshot when refresh fails', async () => {
    const storedSnapshot = snapshot(1);
    await db.snapshots.put({
      key: 'active',
      ownerId: 1,
      version: storedSnapshot.version,
      generatedAt: storedSnapshot.generated_at,
      payload: storedSnapshot,
    });
    fetchSnapshot.mockRejectedValue(new Error('Network unavailable'));

    const conference = useConferenceStore();
    await conference.hydrate();
    await conference.refresh();

    expect(conference.currentUser?.id).toBe(1);
    expect((await db.snapshots.get('active'))?.ownerId).toBe(1);
    expect(useConnectivityStore().syncError).toBe(true);
  });

  it('replaces a different owner snapshot before exposing the fresh user state', async () => {
    const previousSnapshot = snapshot(1);
    const freshSnapshot = snapshot(2, 'version-2');
    await db.snapshots.put({
      key: 'active',
      ownerId: 1,
      version: previousSnapshot.version,
      generatedAt: previousSnapshot.generated_at,
      payload: previousSnapshot,
    });
    fetchSnapshot.mockResolvedValue(freshSnapshot);

    const conference = useConferenceStore();
    await conference.hydrate();
    await conference.refresh();

    expect(conference.currentUser?.id).toBe(2);
    expect((await db.snapshots.get('active'))?.ownerId).toBe(2);
  });

  it('discards a refresh that finishes after the conference lifecycle is cleared', async () => {
    const storedSnapshot = snapshot(1);
    await db.snapshots.put({
      key: 'active',
      ownerId: 1,
      version: storedSnapshot.version,
      generatedAt: storedSnapshot.generated_at,
      payload: storedSnapshot,
    });
    let resolveRefresh: (value: Snapshot) => void = () => undefined;
    fetchSnapshot.mockReturnValue(
      new Promise<Snapshot>((resolve) => {
        resolveRefresh = resolve;
      }),
    );

    const conference = useConferenceStore();
    await conference.hydrate();
    const refresh = conference.refresh();
    await conference.clear();
    resolveRefresh(snapshot(1, 'version-2'));
    await refresh;

    expect(conference.snapshot).toBeNull();
    expect(await db.snapshots.get('active')).toBeUndefined();
  });

  it('does not let an old owner refresh leak into a new lifecycle', async () => {
    const storedSnapshot = snapshot(1);
    const freshSnapshot = snapshot(2, 'version-2');
    await db.snapshots.put({
      key: 'active',
      ownerId: 1,
      version: storedSnapshot.version,
      generatedAt: storedSnapshot.generated_at,
      payload: storedSnapshot,
    });

    let resolveOldRefresh: (value: Snapshot) => void = () => undefined;
    fetchSnapshot.mockReturnValueOnce(
      new Promise<Snapshot>((resolve) => {
        resolveOldRefresh = resolve;
      }),
    );

    const conference = useConferenceStore();
    await conference.hydrate();
    const oldRefresh = conference.refresh();
    await conference.clear();

    fetchSnapshot.mockResolvedValueOnce(freshSnapshot);
    const newRefresh = conference.refresh();
    resolveOldRefresh(storedSnapshot);
    await Promise.all([oldRefresh, newRefresh]);

    expect(conference.snapshot?.current_user.id).toBe(2);
    expect((await db.snapshots.get('active'))?.ownerId).toBe(2);
  });

  it('refreshes once when connectivity returns and deduplicates concurrent refreshes', async () => {
    let resolveRefresh: (value: Snapshot) => void = () => undefined;
    fetchSnapshot.mockReturnValue(
      new Promise<Snapshot>((resolve) => {
        resolveRefresh = resolve;
      }),
    );

    const conference = useConferenceStore();
    const connectivity = useConnectivityStore();
    connectivity.online = false;
    await conference.hydrate();

    const firstRefresh = connectivity.handleOnline();
    const secondRefresh = conference.refresh();

    expect(fetchSnapshot).toHaveBeenCalledTimes(1);
    resolveRefresh(snapshot(1, 'version-2'));
    await Promise.all([firstRefresh, secondRefresh]);
    expect(conference.snapshot?.version).toBe('version-2');
    expect(currentUser).toHaveBeenCalledTimes(1);
  });

  it('marks the session invalid and skips snapshot refresh when reconnect auth fails', async () => {
    currentUser.mockRejectedValue(new ApiError('Unauthenticated', 401));

    const connectivity = useConnectivityStore();
    connectivity.online = false;
    await connectivity.handleOnline();

    expect(connectivity.authenticated).toBe(false);
    expect(fetchSnapshot).not.toHaveBeenCalled();
  });

  it('ignores a reconnect auth result after the authentication lifecycle is reset', async () => {
    let resolveUser: (value: { data: ReturnType<typeof snapshot>['current_user'] }) => void = () =>
      undefined;
    currentUser.mockReturnValue(
      new Promise((resolve) => {
        resolveUser = resolve;
      }),
    );

    const connectivity = useConnectivityStore();
    connectivity.authenticated = true;
    const revalidation = connectivity.revalidateAndRefresh();

    connectivity.resetAuthentication();
    resolveUser({ data: snapshot(1).current_user });
    await revalidation;

    expect(connectivity.authenticated).toBe(false);
    expect(fetchSnapshot).not.toHaveBeenCalled();
  });

  it('keeps a new authentication lifecycle authoritative over an old result', async () => {
    let resolveOldUser: (value: {
      data: ReturnType<typeof snapshot>['current_user'];
    }) => void = () => undefined;
    currentUser.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveOldUser = resolve;
      }),
    );

    const connectivity = useConnectivityStore();
    const oldRevalidation = connectivity.revalidateAndRefresh();

    connectivity.resetAuthentication();
    currentUser.mockResolvedValueOnce({ data: snapshot(2).current_user });
    fetchSnapshot.mockResolvedValueOnce(snapshot(2, 'version-2'));
    const newRevalidation = connectivity.revalidateAndRefresh();
    resolveOldUser({ data: snapshot(1).current_user });

    await Promise.all([oldRevalidation, newRevalidation]);

    expect(connectivity.authenticated).toBe(true);
    expect(fetchSnapshot).toHaveBeenCalledTimes(1);
  });
});
