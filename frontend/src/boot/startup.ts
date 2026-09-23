import { boot } from 'quasar/wrappers';
import { Notify } from 'quasar';
import type { Pinia } from 'pinia';
import { useConferenceStore } from '@/stores/conference';
import { useConnectivityStore } from '@/stores/connectivity';

export default boot(async ({ store }: { store: Pinia }) => {
  Notify.setDefaults({
    position: 'top-right',
    timeout: 4000,
    progress: true,
    actions: [{ icon: 'close', color: 'white', 'aria-label': 'Dismiss notification' }],
  });

  const connectivity = useConnectivityStore(store);
  connectivity.initialise();
  const conference = useConferenceStore(store);
  await conference.hydrate();
  if (connectivity.online) {
    void connectivity.revalidateAndRefresh();
  }
  if ('storage' in navigator) void navigator.storage.persist();
});
