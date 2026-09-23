import { defineStore } from 'pinia';
import { db, replaceSnapshot } from '@/db';
import { fetchSnapshot } from '@/services/api/snapshot';
import type { ConferenceSession, Snapshot } from '@/types/models';
import { useConnectivityStore } from './connectivity';

let lifecycleGeneration = 0;
let refreshState: { generation: number; promise: Promise<void> } | null = null;
let durableWrite: Promise<void> | null = null;

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
      if (!connectivity.online) {
        return;
      }

      const generation = lifecycleGeneration;
      if (refreshState?.generation === generation) {
        return refreshState.promise;
      }

      const promise = this.performRefresh(generation).finally(() => {
        if (refreshState?.generation === generation) {
          refreshState = null;
        }
      });
      refreshState = { generation, promise };

      return promise;
    },
    async performRefresh(generation: number): Promise<void> {
      const connectivity = useConnectivityStore();
      connectivity.syncing = true;
      connectivity.syncError = false;
      try {
        const payload = await fetchSnapshot();
        if (generation !== lifecycleGeneration) {
          return;
        }

        const write = replaceSnapshot({
          key: 'active',
          ownerId: payload.current_user.id,
          version: payload.version,
          generatedAt: payload.generated_at,
          payload,
        });
        durableWrite = write;
        try {
          await write;
        } finally {
          if (durableWrite === write) {
            durableWrite = null;
          }
        }

        if (generation !== lifecycleGeneration) {
          return;
        }

        this.snapshot = payload;
        this.noOfflineSnapshot = false;
        connectivity.lastSyncAt = payload.generated_at;
      } catch {
        if (generation === lifecycleGeneration) {
          connectivity.syncError = true;
        }
      } finally {
        if (generation === lifecycleGeneration) {
          connectivity.syncing = false;
        }
      }
    },
    async clear(): Promise<void> {
      lifecycleGeneration += 1;
      refreshState = null;

      if (durableWrite) {
        await durableWrite;
      }

      await db.snapshots.clear();
      this.snapshot = null;
      this.noOfflineSnapshot = true;
      useConnectivityStore().syncing = false;
    },
  },
});
