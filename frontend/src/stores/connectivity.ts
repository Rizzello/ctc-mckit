import { defineStore } from 'pinia';

export const useConnectivityStore = defineStore('connectivity', {
  state: () => ({
    online: navigator.onLine,
    syncing: false,
    lastSyncAt: null as string | null,
    syncError: false,
  }),
  getters: { offline: (state) => !state.online },
  actions: {
    initialise() {
      window.addEventListener('online', () => {
        this.online = true;
      });
      window.addEventListener('offline', () => {
        this.online = false;
      });
    },
  },
});
