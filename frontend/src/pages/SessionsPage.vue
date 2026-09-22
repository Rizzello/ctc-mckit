<template>
  <q-page class="q-pa-md">
    <div class="q-mb-md">
      <div class="text-overline text-primary">Preparation</div>
      <h1 class="text-h5 q-my-none">Sessions</h1>
    </div>

    <q-card bordered flat class="q-mb-md">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div class="row items-center q-gutter-sm">
          <q-icon name="tune" color="primary" size="22px" />
          <div>
            <div class="text-subtitle1 text-weight-bold">Filters</div>
            <div class="text-caption text-grey-8">Search and narrow the session catalog</div>
          </div>
        </div>
        <q-btn
          v-if="hasFilters"
          flat
          no-caps
          color="primary"
          label="Clear all"
          @click="clearFilters"
        />
      </q-card-section>
      <q-card-section>
        <q-form class="row q-col-gutter-md q-row-gutter-md">
          <div class="col-12">
            <q-input
              v-model="search"
              outlined
              clearable
              hide-bottom-space
              label="Search title or speaker"
            >
              <template #prepend><q-icon name="search" /></template>
            </q-input>
          </div>
          <div class="col-12 col-sm-6">
            <q-select
              v-model="roomId"
              outlined
              clearable
              emit-value
              map-options
              hide-bottom-space
              label="Room"
              :options="roomOptions"
            />
          </div>
          <div class="col-12 col-sm-6">
            <q-select
              v-model="selectedDate"
              outlined
              clearable
              emit-value
              map-options
              hide-bottom-space
              label="Date"
              :options="dateOptions"
            />
          </div>
          <div class="col-12">
            <q-toggle v-model="mySessions" color="primary" label="My sessions only" />
          </div>
        </q-form>
      </q-card-section>
    </q-card>

    <div v-if="matching.length" class="q-gutter-y-sm">
      <q-card
        v-for="session in matching"
        :key="session.id"
        bordered
        flat
        class="cursor-pointer"
        :class="isAssigned(session) ? 'bg-blue-1 border-primary' : ''"
        tabindex="0"
        role="link"
        @click="router.push(`/sessions/${session.id}`)"
        @keyup.enter="router.push(`/sessions/${session.id}`)"
      >
        <q-card-section>
          <div class="text-caption text-grey-8">
            {{ formatSessionTime(session) }} · {{ session.room?.name ?? 'Room to be confirmed' }}
          </div>
          <div class="text-subtitle1 text-weight-bold q-mt-xs">{{ session.title }}</div>
          <div class="text-body2 q-mt-sm">
            {{ session.speakers.map((speaker) => speaker.name).join(', ') || 'No speakers listed' }}
          </div>
          <div class="text-caption text-grey-8 q-mt-xs">
            MC: {{ session.mcs.map((mc) => mc.name).join(', ') || 'No MC assigned' }}
          </div>
          <q-badge v-if="isAssigned(session)" color="primary" class="q-mt-sm">Your session</q-badge>
        </q-card-section>
      </q-card>
    </div>

    <q-banner v-else class="bg-grey-2 text-grey-9 rounded-borders">
      No sessions match these filters. Try a different title, speaker, room, date, or assignment.
    </q-banner>
  </q-page>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useConferenceStore } from '@/stores/conference';
import { dateKey, formatDate, formatTime } from '@/services/schedule';
import type { ConferenceSession } from '@/types/models';

const conference = useConferenceStore();
const router = useRouter();
const search = ref('');
const roomId = ref<number | null>(null);
const selectedDate = ref<string | null>(null);
const mySessions = ref(false);
const roomOptions = computed(() =>
  conference.rooms.map((room) => ({ label: room.name, value: room.id })),
);
const dateOptions = computed(() =>
  [
    ...new Set(
      conference.sessions.map((session) => dateKey(session.starts_at)).filter(Boolean) as string[],
    ),
  ]
    .sort()
    .map((date) => ({ label: formatDate(`${date}T12:00:00+02:00`), value: date })),
);
const hasFilters = computed(() =>
  Boolean(search.value || roomId.value || selectedDate.value || mySessions.value),
);
const matching = computed(() => {
  const term = search.value.trim().toLocaleLowerCase();

  return [...conference.sessions]
    .filter((session) => {
      const matchesSearch =
        !term ||
        session.title.toLocaleLowerCase().includes(term) ||
        session.speakers.some((speaker) => speaker.name.toLocaleLowerCase().includes(term));
      const matchesRoom = roomId.value === null || session.room_id === roomId.value;
      const matchesDate =
        selectedDate.value === null || dateKey(session.starts_at) === selectedDate.value;

      return (
        matchesSearch && matchesRoom && matchesDate && (!mySessions.value || isAssigned(session))
      );
    })
    .sort((left, right) => Date.parse(left.starts_at ?? '') - Date.parse(right.starts_at ?? ''));
});

function isAssigned(session: ConferenceSession): boolean {
  return session.mcs.some((mc) => mc.id === conference.currentUser?.id);
}

function formatSessionTime(session: ConferenceSession): string {
  return session.starts_at
    ? `${formatDate(session.starts_at)} ${formatTime(session.starts_at)}`
    : 'Time to be confirmed';
}

function clearFilters(): void {
  search.value = '';
  roomId.value = null;
  selectedDate.value = null;
  mySessions.value = false;
}
</script>
