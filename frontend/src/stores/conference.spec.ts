import 'fake-indexeddb/auto';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { db } from '@/db';
import { useConferenceStore } from '@/stores/conference';
import { useConnectivityStore } from '@/stores/connectivity';
import type { Snapshot } from '@/types/models';

const { fetchSnapshot } = vi.hoisted(() => ({ fetchSnapshot: vi.fn() }));

vi.mock('@/services/api/snapshot', () => ({ fetchSnapshot }));

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
    rooms: [],
    speakers: [],
    sessions: [],
  };
}

describe('conference store', () => {
  beforeEach(async () => {
    setActivePinia(createPinia());
    await db.snapshots.clear();
    fetchSnapshot.mockReset();
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
});
