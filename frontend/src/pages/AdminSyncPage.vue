<template>
  <q-page class="q-pa-md">
    <div class="q-mb-md">
      <div class="text-overline text-primary">Administration</div>
      <h1 class="text-h5 q-my-none">Sessionize sync</h1>
    </div>
    <q-card bordered flat>
      <q-card-section>
        <div class="row items-center justify-between">
          <div class="text-subtitle1 text-weight-bold">Configuration</div>
          <q-badge :color="status?.configured ? 'positive' : 'grey-7'">
            {{ status?.configured ? 'Configured' : 'Not configured' }}
          </q-badge>
        </div>
        <q-separator class="q-my-md" />
        <div class="text-subtitle1 text-weight-bold">Latest synchronization</div>
        <template v-if="status?.last_sync">
          <q-badge :color="statusColor(status.last_sync.status)" class="q-mt-sm">
            {{ statusLabel(status.last_sync.status) }}
          </q-badge>
          <div v-if="status.last_sync.started_at" class="q-mt-sm text-body2 text-grey-8">
            Started {{ formatTimestamp(status.last_sync.started_at) }}
          </div>
          <div v-if="status.last_sync.finished_at" class="text-body2 text-grey-8">
            Finished {{ formatTimestamp(status.last_sync.finished_at) }}
          </div>
          <div v-if="status.last_sync.stats" class="q-mt-sm text-caption text-grey-8">
            {{ status.last_sync.stats.sessions }} sessions ·
            {{ status.last_sync.stats.speakers }} speakers ·
            {{ status.last_sync.stats.rooms }} rooms
          </div>
          <q-banner
            v-if="status.last_sync.status === 'failed'"
            class="bg-negative text-white q-mt-md rounded-borders"
          >
            The last Sessionize synchronization failed. Check application logs for details.
          </q-banner>
        </template>
        <div v-else class="q-mt-sm text-body2 text-grey-8">No synchronization has run yet.</div>
        <div
          v-if="status?.last_successful_sync?.finished_at"
          class="q-mt-md text-caption text-grey-8"
        >
          Last successful sync: {{ formatTimestamp(status.last_successful_sync.finished_at) }}
        </div>
      </q-card-section>
      <q-card-actions class="q-pa-md q-pt-none">
        <q-btn
          color="primary"
          :label="syncInProgress ? 'Synchronization in progress' : 'Sync now'"
          :disable="!status?.configured || connectivity.offline || syncInProgress"
          :loading="syncing"
          @click="sync"
        />
      </q-card-actions>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Notify } from 'quasar';
import { ApiError } from '@/services/api/client';
import {
  queueSessionizeSync,
  sessionizeStatus,
  type SyncRun,
  type SyncStatus,
} from '@/services/api/admin';
import { useConnectivityStore } from '@/stores/connectivity';
import { formatDate, formatTime } from '@/services/schedule';

const connectivity = useConnectivityStore();
const status = ref<SyncStatus | null>(null);
const syncing = ref(false);
let pollTimer: number | undefined;
const syncInProgress = computed(() => {
  const latestSync = status.value?.last_sync;

  return (
    latestSync !== null &&
    latestSync !== undefined &&
    ['queued', 'running'].includes(latestSync.status)
  );
});

onMounted(async () => {
  await load();
});

onBeforeUnmount(stopPolling);

watch(
  syncInProgress,
  (value) => {
    stopPolling();

    if (value) {
      pollTimer = window.setInterval(() => void load(), 5000);
    }
  },
  { immediate: true },
);

async function load(): Promise<void> {
  try {
    status.value = await sessionizeStatus();
  } catch {
    Notify.create({ type: 'negative', message: 'Sessionize status could not be loaded.' });
  }
}

function stopPolling(): void {
  if (pollTimer !== undefined) {
    window.clearInterval(pollTimer);
    pollTimer = undefined;
  }
}

function statusColor(status: SyncRun['status']): string {
  return {
    queued: 'secondary',
    running: 'primary',
    completed: 'positive',
    failed: 'negative',
  }[status];
}

function statusLabel(status: SyncRun['status']): string {
  return {
    queued: 'Queued',
    running: 'Running',
    completed: 'Completed',
    failed: 'Failed',
  }[status];
}

function formatTimestamp(timestamp: string): string {
  return `${formatDate(timestamp)} ${formatTime(timestamp)}`;
}

async function sync(): Promise<void> {
  syncing.value = true;
  try {
    await queueSessionizeSync();
    Notify.create({ type: 'positive', message: 'Sessionize synchronization queued.' });
    await load();
  } catch (error) {
    const message = error instanceof ApiError ? error.validation?.sessionize?.[0] : null;
    Notify.create({
      type: 'negative',
      message: message ?? 'Sessionize synchronization could not be queued.',
    });
  } finally {
    syncing.value = false;
  }
}
</script>
