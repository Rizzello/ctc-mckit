import { defineStore } from 'pinia';
import {
  currentUser,
  getAuthLifecycleGeneration,
  invalidateAuthLifecycle,
  isAuthLifecycleCurrent,
  StaleAuthValidationError,
} from '@/services/api/auth';
import { ApiError } from '@/services/api/client';
import { useConferenceStore } from './conference';

export const useConnectivityStore = defineStore('connectivity', {
  state: () => ({
    online: navigator.onLine,
    syncing: false,
    lastSyncAt: null as string | null,
    syncError: false,
    initialized: false,
    authenticated: null as boolean | null,
  }),
  getters: { offline: (state) => !state.online },
  actions: {
    initialise(): void {
      if (this.initialized) {
        return;
      }

      this.initialized = true;
      window.addEventListener('online', () => {
        void this.handleOnline();
      });
      window.addEventListener('offline', () => {
        this.handleOffline();
      });
    },
    handleOnline(): Promise<void> {
      this.online = true;

      return this.revalidateAndRefresh();
    },
    handleOffline(): void {
      this.online = false;
    },
    resetAuthentication(): void {
      invalidateAuthLifecycle();
      this.authenticated = false;
    },
    async revalidateAndRefresh(): Promise<void> {
      const conference = useConferenceStore();
      const generation = getAuthLifecycleGeneration();

      try {
        const user = (await currentUser()).data;
        if (!isAuthLifecycleCurrent(generation)) {
          return;
        }

        this.authenticated = true;

        if (conference.currentUser && conference.currentUser.id !== user.id) {
          await conference.clear();
        }

        await conference.refresh();
      } catch (error) {
        if (error instanceof StaleAuthValidationError || !isAuthLifecycleCurrent(generation)) {
          return;
        }

        if (error instanceof ApiError && [401, 403].includes(error.status ?? 0)) {
          this.authenticated = false;
          this.syncError = false;

          return;
        }

        this.syncError = true;
      }
    },
  },
});
