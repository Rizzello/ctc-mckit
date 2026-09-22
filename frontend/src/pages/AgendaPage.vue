<template>
  <q-page class="q-pa-md">
    <div class="row items-start justify-between q-col-gutter-md q-mb-md">
      <div class="col">
        <div class="text-overline text-primary">Conference overview</div>
        <h1 class="text-h5 q-my-none">Agenda</h1>
      </div>
      <q-badge v-if="conference.noOfflineSnapshot" color="warning" text-color="dark">
        Offline data unavailable
      </q-badge>
    </div>

    <q-tabs
      v-if="dates.length > 1"
      v-model="selectedDate"
      align="left"
      active-color="primary"
      indicator-color="primary"
      narrow-indicator
      class="bg-white rounded-borders shadow-1 q-mb-md"
    >
      <q-tab v-for="date in dates" :key="date" :name="date" :label="formatDate(date)" />
    </q-tabs>

    <q-banner v-if="!daySessions.length" class="bg-grey-2 text-grey-9 rounded-borders">
      No schedule is available for this day.
    </q-banner>

    <q-card v-else bordered flat class="q-mb-md">
      <div class="agenda-scroll">
        <div class="agenda-grid" :style="gridStyle">
          <div class="agenda-time-heading">Time</div>
          <div v-for="room in rooms" :key="room.id" class="agenda-room-heading">
            {{ room.name }}
          </div>

          <div class="agenda-body" :style="bodyStyle">
            <div class="agenda-time-column">
              <span
                v-for="hour in hours"
                :key="hour"
                class="agenda-hour"
                :class="{ 'agenda-hour-first': hour === 0 }"
                :style="{ top: `${hour * 180}px` }"
              >
                {{ hourLabel(hour) }}
              </span>
            </div>

            <div v-for="room in rooms" :key="room.id" class="agenda-room-column">
              <span
                v-for="hour in hoursIncludingEnd"
                :key="hour"
                class="agenda-hour-rule"
                :style="{ top: `${hour * 180}px` }"
              />
              <q-card
                v-for="positioned in positionedByRoom[room.id] ?? []"
                :key="positioned.session.id"
                bordered
                flat
                tabindex="0"
                role="link"
                class="agenda-session"
                :class="sessionClass(positioned.session)"
                :style="{ top: `${positioned.top}px`, height: `${positioned.height}px` }"
                @click="router.push(`/sessions/${positioned.session.id}`)"
                @keyup.enter="router.push(`/sessions/${positioned.session.id}`)"
              >
                <q-card-section class="q-pa-sm">
                  <div class="text-weight-bold">
                    {{ formatTime(positioned.session.starts_at) }}–{{
                      formatTime(positioned.session.ends_at)
                    }}
                    <template v-if="sessionDuration(positioned.session)">
                      · {{ sessionDuration(positioned.session) }} min
                    </template>
                  </div>
                  <div class="q-mt-xs text-weight-medium">{{ positioned.session.title }}</div>
                  <div v-if="positioned.session.speakers.length" class="q-mt-xs text-caption">
                    {{
                      positioned.session.speakers
                        .map((speaker: { name: string }) => speaker.name)
                        .join(', ')
                    }}
                  </div>
                  <div v-if="positioned.session.mcs.length" class="q-mt-xs text-caption">
                    MC: {{ positioned.session.mcs.map((mc) => mc.name).join(', ') }}
                  </div>
                  <div
                    v-if="positioned.session.is_plenum_session"
                    class="q-mt-xs text-caption text-weight-bold"
                  >
                    Plenary
                  </div>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </div>
      </div>
    </q-card>

    <div v-if="daySessions.length" class="row q-gutter-md q-mt-sm text-caption text-grey-8">
      <span class="agenda-legend-item"
        ><span class="agenda-key agenda-key-assigned" />Your assigned sessions</span
      >
      <span class="agenda-legend-item"
        ><span class="agenda-key agenda-key-plenary" />Plenary sessions</span
      >
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import { useRouter } from 'vue-router';
import { useConferenceStore } from '@/stores/conference';
import type { ConferenceSession } from '@/types/models';
import {
  dateKey,
  formatDate,
  formatTime,
  positionSessions,
  sessionDuration,
} from '@/services/schedule';

const conference = useConferenceStore();
const router = useRouter();
const dates = computed(() =>
  [
    ...new Set(
      conference.sessions.map((session) => dateKey(session.starts_at)).filter(Boolean) as string[],
    ),
  ].sort(),
);
const selectedDate = ref<string | null>(null);

watchEffect(() => {
  const today = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: 'Europe/Rome',
  })
    .formatToParts(new Date())
    .filter((part) => part.type !== 'literal')
    .map((part) => part.value)
    .join('-');
  selectedDate.value ??= dates.value.find((date) => date >= today) ?? dates.value.at(-1) ?? null;
});

const daySessions = computed(() =>
  conference.sessions.filter((session) => dateKey(session.starts_at) === selectedDate.value),
);
const rooms = computed(() => conference.rooms);
const firstStart = computed(() =>
  daySessions.value
    .map((session) => session.starts_at)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(0),
);
const lastEnd = computed(() =>
  daySessions.value
    .map((session) => session.ends_at)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1),
);
const calendarStart = computed(() => {
  if (!firstStart.value) return null;
  const value = new Date(firstStart.value);
  value.setMinutes(0, 0, 0);

  return value;
});
const hourCount = computed(() => {
  if (!calendarStart.value || !lastEnd.value) return 0;

  return Math.ceil((Date.parse(lastEnd.value) - calendarStart.value.getTime()) / 3600000);
});
const hours = computed(() => Array.from({ length: hourCount.value }, (_, index) => index));
const hoursIncludingEnd = computed(() =>
  Array.from({ length: hourCount.value + 1 }, (_, index) => index),
);
const positionedByRoom = computed<Record<number, ReturnType<typeof positionSessions>>>(() => {
  const start = calendarStart.value;
  if (!start) return {};

  return Object.fromEntries(
    rooms.value.map((room) => [
      room.id,
      positionSessions(
        daySessions.value.filter(
          (session) => session.is_plenum_session || session.room_id === room.id,
        ),
        start,
      ),
    ]),
  );
});
const calendarHeight = computed(() =>
  Math.max(
    hourCount.value * 180,
    ...Object.values(positionedByRoom.value).map((items) => items.at(-1)?.bottom ?? 0),
  ),
);
const gridStyle = computed(() => ({
  gridTemplateColumns: `4.5rem repeat(${rooms.value.length}, minmax(15rem, 1fr))`,
}));
const bodyStyle = computed(() => ({
  height: `${calendarHeight.value}px`,
  gridTemplateColumns: gridStyle.value.gridTemplateColumns,
}));

function hourLabel(hour: number): string {
  if (!calendarStart.value) return '';

  return formatTime(new Date(calendarStart.value.getTime() + hour * 3600000).toISOString());
}

function sessionClass(session: ConferenceSession): string {
  if (session.is_plenum_session) return 'agenda-session-plenary';

  return session.mcs.some((mc) => mc.id === conference.currentUser?.id)
    ? 'agenda-session-assigned'
    : 'agenda-session-standard';
}
</script>
