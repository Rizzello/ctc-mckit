import { defineRouter } from '#q-app';
import {
  createMemoryHistory,
  createRouter,
  createWebHashHistory,
  createWebHistory,
} from 'vue-router';
import { useConnectivityStore } from '@/stores/connectivity';
import { useConferenceStore } from '@/stores/conference';
import { currentUser } from '@/services/api/auth';
import { ApiError } from '@/services/api/client';

import routes from './routes';

let sessionValidated = false;

/*
 * If not building with SSR mode, you can
 * directly export the Router instantiation;
 *
 * The function below can be async too; either use
 * async/await or return a Promise which resolves
 * with the Router instance.
 */

export default defineRouter(({ store }) => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory;

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,

    // Leave this as is and make changes in quasar.conf.js instead!
    // quasar.conf.js -> build -> vueRouterMode
    // quasar.conf.js -> build -> publicPath
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
  });

  Router.beforeEach(async (to) => {
    const conference = useConferenceStore(store);
    const connectivity = useConnectivityStore(store);
    const isGuestRoute = to.path.startsWith('/login');

    if (isGuestRoute) {
      return true;
    }

    if (!connectivity.online) {
      if (conference.currentUser && (!to.meta.requiresAdmin || conference.currentUser.is_admin)) {
        return true;
      }

      return to.meta.requiresAdmin ? '/agenda' : '/login';
    }

    if (!sessionValidated) {
      try {
        const user = (await currentUser()).data;

        if (conference.currentUser?.id !== user.id) {
          await conference.clear();
        }

        if (!conference.currentUser) {
          await conference.refresh();
        }

        sessionValidated = true;
      } catch (error) {
        connectivity.syncError = true;

        if (error instanceof ApiError && [401, 403].includes(error.status ?? 0)) {
          await conference.clear();
          sessionValidated = false;

          return '/login';
        }

        return conference.currentUser ? true : '/login';
      }
    }

    return to.meta.requiresAdmin && !conference.currentUser?.is_admin ? '/agenda' : true;
  });

  return Router;
});
