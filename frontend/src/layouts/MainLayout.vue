<template>
  <q-layout view="lHh Lpr lFf">
    <q-header bordered class="bg-white text-dark">
      <q-toolbar>
        <q-btn
          flat
          round
          dense
          icon="menu"
          aria-label="Open navigation"
          @click="drawer = !drawer"
        />
        <q-toolbar-title>MC Kit</q-toolbar-title>
        <q-badge :color="connectionColor" :label="connectionLabel" />
      </q-toolbar>
    </q-header>
    <q-drawer v-model="drawer" bordered>
      <q-list padding>
        <q-item
          to="/agenda"
          active-class="bg-blue-1 text-primary"
          clickable
          v-ripple
          @click="drawer = false"
          ><q-item-section>Agenda</q-item-section></q-item
        >
        <q-item
          to="/sessions"
          active-class="bg-blue-1 text-primary"
          clickable
          v-ripple
          @click="drawer = false"
          ><q-item-section>Sessions</q-item-section></q-item
        >
        <q-item
          to="/live"
          active-class="bg-blue-1 text-primary"
          clickable
          v-ripple
          @click="drawer = false"
          ><q-item-section>Live</q-item-section></q-item
        >
        <template v-if="conference.currentUser?.is_admin">
          <q-separator class="q-my-sm" />
          <q-item
            to="/admin/users"
            active-class="bg-blue-1 text-primary"
            clickable
            v-ripple
            @click="drawer = false"
            ><q-item-section>Users</q-item-section></q-item
          >
          <q-item
            to="/admin/sync"
            active-class="bg-blue-1 text-primary"
            clickable
            v-ripple
            @click="drawer = false"
            ><q-item-section>Sync</q-item-section></q-item
          >
        </template>
        <q-separator class="q-my-sm" />
        <q-item clickable v-ripple :disable="connectivity.offline" @click="signOut"
          ><q-item-section>Sign out</q-item-section></q-item
        >
      </q-list>
    </q-drawer>
    <q-page-container><router-view /></q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Notify } from 'quasar';
import { logout } from '@/services/api/auth';
import { resetSessionValidation } from '@/router';
import { useConferenceStore } from '@/stores/conference';
import { useConnectivityStore } from '@/stores/connectivity';
const drawer = ref(false);
const router = useRouter();
const conference = useConferenceStore();
const connectivity = useConnectivityStore();
const connectionColor = computed(() => {
  if (connectivity.offline) return 'orange';

  return connectivity.syncError ? 'warning' : 'positive';
});
const connectionLabel = computed(() => {
  if (connectivity.offline) return 'Offline';

  return connectivity.syncError ? 'Connection unavailable' : 'Online';
});
async function signOut() {
  try {
    await logout();
    await conference.clear();
    resetSessionValidation();
    await router.replace('/login');
  } catch {
    Notify.create({ type: 'negative', message: 'Sign out requires a connection.' });
  }
}
</script>
