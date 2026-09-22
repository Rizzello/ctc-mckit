<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-col-gutter-md q-mb-md">
      <div class="col">
        <div class="text-overline text-primary">Read-only presenter mode</div>
        <h1 class="text-h5 q-my-none">Live</h1>
      </div>
      <q-badge
        rounded
        :color="statusColor"
        text-color="white"
        class="live-status-badge col-auto text-weight-bold"
      >
        {{ state }}
      </q-badge>
    </div>

    <q-banner v-if="!sessions.length" class="bg-grey-2 text-grey-9 rounded-borders">
      No sessions are assigned to you. Live mode is ready once an administrator assigns a session.
    </q-banner>

    <template v-else-if="session">
      <div class="row q-col-gutter-sm q-mb-md">
        <div class="col">
          <q-btn
            outline
            color="primary"
            class="full-width"
            label="Previous"
            :disable="index === 0"
            @click="index -= 1"
          />
        </div>
        <div class="col">
          <q-btn
            color="primary"
            class="full-width"
            label="Next"
            :disable="index === sessions.length - 1"
            @click="index += 1"
          />
        </div>
      </div>

      <q-card bordered flat class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle2 text-primary">{{ timeRange }}</div>
          <div class="text-body1 text-grey-8 q-mt-xs">
            {{ session.room?.name ?? 'Room to be confirmed' }}
          </div>
          <h2 class="text-h5 q-mt-md q-mb-sm">{{ session.title }}</h2>
          <div class="text-body1 text-grey-8">
            MC: {{ session.mcs.map((mc) => mc.name).join(', ') || 'No MC assigned' }}
          </div>
        </q-card-section>

        <q-expansion-item
          v-if="session.description"
          label="About this session"
          header-class="text-primary text-weight-medium"
        >
          <q-card-section class="whitespace-pre-line text-body1">{{
            session.description
          }}</q-card-section>
        </q-expansion-item>
      </q-card>

      <div v-if="session.speakers.length" class="q-gutter-y-sm q-mb-md">
        <q-card
          v-for="speaker in session.speakers"
          :key="speaker.id"
          bordered
          flat
          class="bg-grey-1"
        >
          <q-card-section class="row no-wrap q-gutter-md">
            <q-avatar v-if="speaker.photo_url" size="56px">
              <img :src="speaker.photo_url" :alt="speaker.name" />
            </q-avatar>
            <div class="col">
              <div class="text-subtitle1 text-weight-bold">{{ speaker.name }}</div>
              <div v-if="speaker.tagline" class="text-body1 text-grey-8">
                {{ speaker.tagline }}
              </div>
            </div>
          </q-card-section>
          <q-expansion-item
            v-if="speaker.bio"
            label="Speaker bio"
            header-class="text-primary text-weight-medium"
          >
            <q-card-section class="q-pa-md q-pt-sm whitespace-pre-line text-body1">{{
              speaker.bio
            }}</q-card-section>
          </q-expansion-item>
        </q-card>
      </div>

      <q-card
        v-if="session.mc_description || session.mc_script"
        bordered
        flat
        class="q-mb-md bg-blue-1"
      >
        <q-card-section>
          <div class="row items-center justify-between q-col-gutter-md">
            <h3 class="text-subtitle1 text-weight-bold q-my-none">Host preparation</h3>
            <q-btn
              flat
              round
              dense
              icon="zoom_in"
              aria-label="Expand host preparation"
              class="q-ml-auto"
              @click="expanded = true"
            />
          </div>
          <div v-if="session.mc_description" class="q-mt-md">
            <div class="text-weight-medium">Host briefing</div>
            <div class="whitespace-pre-line text-body1">{{ session.mc_description }}</div>
          </div>
          <div v-if="session.mc_script" class="q-mt-md">
            <div class="text-weight-medium">Suggested wording</div>
            <div class="whitespace-pre-line text-body1">{{ session.mc_script }}</div>
          </div>
        </q-card-section>
      </q-card>

      <q-card v-if="session.notes.length" bordered flat class="q-mb-md">
        <q-card-section>
          <h3 class="text-subtitle1 text-weight-bold">Notes</h3>
          <q-list bordered separator>
            <q-item v-for="note in session.notes" :key="note.id">
              <q-item-section>
                <q-item-label class="whitespace-pre-line text-body1">{{ note.body }}</q-item-label>
                <q-item-label caption>{{ formatTime(note.created_at) }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>

      <q-dialog
        v-model="expanded"
        maximized
        transition-show="slide-up"
        transition-hide="slide-down"
      >
        <q-card class="column bg-grey-1">
          <q-toolbar class="bg-white text-dark q-px-md q-py-sm relative-position">
            <q-toolbar-title class="text-center q-px-xl">
              <div class="text-h6 text-weight-bold">{{ session.title }}</div>
              <div class="text-body1 text-grey-8">
                {{ session.speakers.map((speaker) => speaker.name).join(', ') }}
              </div>
            </q-toolbar-title>
            <q-btn
              v-close-popup
              flat
              round
              dense
              icon="close"
              aria-label="Close host preparation"
              class="absolute-right q-mr-sm"
            />
          </q-toolbar>
          <q-card-section class="col q-pa-lg live-preparation-scroll">
            <div class="live-preparation-content">
              <div v-if="session.mc_description" class="q-mb-xl">
                <h2 class="text-h5">Host briefing</h2>
                <div class="whitespace-pre-line">{{ session.mc_description }}</div>
              </div>
              <div v-if="session.mc_script">
                <h2 class="text-h5">Suggested wording</h2>
                <div class="whitespace-pre-line">{{ session.mc_script }}</div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </q-dialog>
    </template>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useConferenceStore } from '@/stores/conference';
import { formatTime, sessionState } from '@/services/schedule';

const conference = useConferenceStore();
const now = ref(Date.now());
const sessions = computed(() =>
  [...conference.assignedSessions]
    .filter((session) => session.starts_at && session.ends_at)
    .sort((left, right) => Date.parse(left.starts_at ?? '') - Date.parse(right.starts_at ?? '')),
);
const firstUpcomingIndex = sessions.value.findIndex(
  (item) => Date.parse(item.starts_at ?? '') > now.value,
);
const currentIndex = sessions.value.findIndex(
  (item) => sessionState(item, now.value) === 'Current',
);
const index = ref(currentIndex !== -1 ? currentIndex : Math.max(0, firstUpcomingIndex));
const session = computed(() => sessions.value[index.value]);
const state = computed(() => (session.value ? sessionState(session.value, now.value) : 'Upcoming'));
const statusColor = computed(() => {
  if (state.value === 'Current') {
    return 'primary';
  }

  if (state.value === 'Upcoming') {
    return 'secondary';
  }

  return 'grey-8';
});
const timeRange = computed(() =>
  session.value
    ? `${formatTime(session.value.starts_at)}–${formatTime(session.value.ends_at)}`
    : '',
);
const expanded = ref(false);
const timer = window.setInterval(() => {
  now.value = Date.now();
}, 30000);

onBeforeUnmount(() => window.clearInterval(timer));
</script>
