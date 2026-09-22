import { defineStore } from 'pinia';
import { db, replaceSnapshot } from '@/db';
import { fetchSnapshot } from '@/services/api/snapshot';
import type { ConferenceSession, Snapshot } from '@/types/models';
import { useConnectivityStore } from './connectivity';

export const useConferenceStore = defineStore('conference', {
  state: () => ({ snapshot: null as Snapshot | null, ready: false, noOfflineSnapshot: false }),
  getters: {
    sessions: (state) => state.snapshot?.sessions ?? [],
    rooms: (state) => state.snapshot?.rooms ?? [],
    currentUser: (state) => state.snapshot?.current_user ?? null,
    assignedSessions(): ConferenceSession[] {
      return this.sessions.filter((session) =>
        session.mcs.some((mc) => mc.id === this.currentUser?.id),
      );
    },
    sessionById: (state) => (id: number) =>
      state.snapshot?.sessions.find((session) => session.id === id),
  },
  actions: {
    async hydrate(): Promise<void> {
      const stored = await db.snapshots.get('active');
      if (stored) this.snapshot = stored.payload;
      this.ready = true;
      this.noOfflineSnapshot = !stored;
    },
    async refresh(): Promise<void> {
      const connectivity = useConnectivityStore();
      if (!connectivity.online) return;
      connectivity.syncing = true;
      connectivity.syncError = false;
      try {
        const payload = await fetchSnapshot();
        const stored = await db.snapshots.get('active');
        if (stored && stored.ownerId !== payload.current_user.id) await db.snapshots.clear();
        await replaceSnapshot({
          key: 'active',
          ownerId: payload.current_user.id,
          version: payload.version,
          generatedAt: payload.generated_at,
          payload,
        });
        this.snapshot = payload;
        this.noOfflineSnapshot = false;
        connectivity.lastSyncAt = payload.generated_at;
      } catch {
        connectivity.syncError = true;
      } finally {
        connectivity.syncing = false;
      }
    },
    async clear(): Promise<void> {
      await db.snapshots.clear();
      this.snapshot = null;
      this.noOfflineSnapshot = true;
    },
  },
});
