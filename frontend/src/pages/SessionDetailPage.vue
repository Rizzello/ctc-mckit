<template>
  <q-page class="q-pa-md">
    <q-btn flat icon="arrow_back" to="/sessions" label="Sessions" class="q-mb-md" />
    <template v-if="session">
      <div class="text-caption text-primary">{{ timeAndRoom }}</div>
      <h1 class="text-h5 q-mt-sm">{{ session.title }}</h1>
      <div class="row q-gutter-sm q-mb-md">
        <q-badge v-for="category in session.categories" :key="category" color="grey-7">{{
          category
        }}</q-badge>
        <q-badge v-if="session.is_plenum_session" color="deep-purple">Plenary</q-badge>
        <q-badge v-if="session.is_service_session" color="grey-7">Service session</q-badge>
      </div>

      <q-card bordered flat class="q-mb-md">
        <q-card-section
          ><div class="text-subtitle1 text-weight-bold">Session information</div>
          <div class="whitespace-pre-line q-mt-sm">
            {{ session.description || 'No session description is available.' }}
          </div></q-card-section
        >
      </q-card>

      <q-card v-for="speaker in session.speakers" :key="speaker.id" bordered flat class="q-mb-sm">
        <q-card-section class="row no-wrap q-gutter-md"
          ><q-avatar v-if="speaker.photo_url" size="64px"
            ><img :src="speaker.photo_url" :alt="speaker.name"
          /></q-avatar>
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">{{ speaker.name }}</div>
            <div v-if="speaker.tagline" class="text-body2 text-grey-8">{{ speaker.tagline }}</div>
          </div></q-card-section
        >
        <q-card-section v-if="speaker.bio" class="q-pt-none whitespace-pre-line text-body2">{{
          speaker.bio
        }}</q-card-section>
      </q-card>

      <q-card bordered flat class="q-mb-md">
        <q-card-section
          ><div class="text-subtitle1 text-weight-bold">MC preparation</div>
          <q-input
            v-model="description"
            type="textarea"
            outlined
            label="Host briefing"
            class="q-mt-md"
            :readonly="connectivity.offline || savingPreparation" /><q-input
            v-model="script"
            type="textarea"
            outlined
            label="Suggested wording"
            class="q-mt-md"
            :readonly="connectivity.offline || savingPreparation" /><q-btn
            color="primary"
            label="Save preparation"
            class="q-mt-md"
            :loading="savingPreparation"
            :disable="connectivity.offline || savingPreparation"
            @click="save"
        /></q-card-section>
      </q-card>

      <q-card bordered flat class="q-mb-md"
        ><q-card-section
          ><div class="row items-center justify-between">
            <div class="text-subtitle1 text-weight-bold">Assigned MCs</div>
            <q-btn
              v-if="isAdmin"
              flat
              color="primary"
              label="Assign MC"
              :disable="connectivity.offline"
              @click="openAssignment"
            />
          </div>
          <q-list separator class="q-mt-sm"
            ><q-item v-for="mc in session.mcs" :key="mc.id"
              ><q-item-section>{{ mc.name }}</q-item-section
              ><q-item-section v-if="isAdmin" side
                ><q-btn
                  flat
                  color="negative"
                  label="Remove"
                  :loading="removingAssignmentId === mc.id"
                  :disable="connectivity.offline || removingAssignmentId !== null"
                  @click="removeAssignment(mc.id)" /></q-item-section></q-item
            ><q-item v-if="!session.mcs.length"
              ><q-item-section class="text-grey-7">No MC assigned</q-item-section></q-item
            ></q-list
          ></q-card-section
        ></q-card
      >

      <q-card bordered flat
        ><q-card-section
          ><div class="row items-center justify-between">
            <div class="text-subtitle1 text-weight-bold">Notes</div>
            <q-btn
              v-if="isAdmin"
              flat
              color="primary"
              label="Add note"
              :disable="connectivity.offline"
              @click="openCreateNote"
            />
          </div>
          <q-list separator class="q-mt-sm"
            ><q-item v-for="note in session.notes" :key="note.id"
              ><q-item-section
                ><q-item-label class="whitespace-pre-line">{{ note.body }}</q-item-label
                ><q-item-label caption>{{
                  formatTime(note.created_at)
                }}</q-item-label></q-item-section
              ><q-item-section v-if="isAdmin" side class="row no-wrap"
                ><q-btn
                  flat
                  color="primary"
                  icon="edit"
                  aria-label="Edit note"
                  :disable="connectivity.offline"
                  @click="openEditNote(note.id, note.body)" /><q-btn
                  flat
                  color="negative"
                  icon="delete"
                  aria-label="Delete note"
                  :disable="connectivity.offline"
                  @click="confirmNoteDeletion(note.id)" /></q-item-section></q-item
            ><q-item v-if="!session.notes.length"
              ><q-item-section class="text-grey-7">No notes yet</q-item-section></q-item
            ></q-list
          ></q-card-section
        ></q-card
      >

      <q-dialog v-model="noteOpen"
        ><q-card style="min-width: min(92vw, 420px)"
          ><q-card-section><div class="text-h6">Add note</div></q-card-section
          ><q-form @submit.prevent="createNote"
            ><q-card-section
              ><q-input
                v-model="noteBody"
                type="textarea"
                outlined
                label="Technical note"
                :disable="noteSaving"
                :error="Boolean(noteError)"
                :error-message="noteError" /></q-card-section
            ><q-card-actions align="right"
              ><q-btn flat label="Cancel" :disable="noteSaving" v-close-popup /><q-btn
                color="primary"
                label="Save note"
                type="submit"
                :loading="noteSaving"
                :disable="!noteBody.trim() || noteSaving" /></q-card-actions></q-form></q-card
      ></q-dialog>
      <q-dialog v-model="editNoteOpen"
        ><q-card style="min-width: min(92vw, 420px)"
          ><q-card-section><div class="text-h6">Edit note</div></q-card-section
          ><q-form @submit.prevent="saveEditedNote"
            ><q-card-section
              ><q-input
                v-model="editedNoteBody"
                type="textarea"
                outlined
                label="Technical note"
                :disable="noteSaving"
                :error="Boolean(noteError)"
                :error-message="noteError" /></q-card-section
            ><q-card-actions align="right"
              ><q-btn flat label="Cancel" :disable="noteSaving" v-close-popup /><q-btn
                color="primary"
                label="Save note"
                type="submit"
                :loading="noteSaving"
                :disable="!editedNoteBody.trim() || noteSaving" /></q-card-actions></q-form></q-card
      ></q-dialog>
      <q-dialog v-model="deleteNoteOpen" persistent
        ><q-card style="min-width: min(92vw, 360px)"
          ><q-card-section><div class="text-h6">Delete note?</div></q-card-section
          ><q-card-section>This action cannot be undone.</q-card-section
          ><q-card-actions align="right"
            ><q-btn flat label="Cancel" :disable="noteDeleting" v-close-popup /><q-btn
              color="negative"
              label="Delete"
              :loading="noteDeleting"
              @click="deleteNote" /></q-card-actions></q-card
      ></q-dialog>
      <q-dialog v-model="assignmentOpen"
        ><q-card style="min-width: min(92vw, 420px)"
          ><q-card-section><div class="text-h6">Assign MC</div></q-card-section
          ><q-form @submit.prevent="createAssignment"
            ><q-card-section
              ><q-select
                v-model="assignedUserId"
                outlined
                emit-value
                map-options
                label="Enabled user"
                :options="assignableUsers"
                :disable="assignmentSaving" /></q-card-section
            ><q-card-actions align="right"
              ><q-btn flat label="Cancel" :disable="assignmentSaving" v-close-popup /><q-btn
                color="primary"
                label="Assign"
                type="submit"
                :loading="assignmentSaving"
                :disable="
                  assignedUserId === null || assignmentSaving
                " /></q-card-actions></q-form></q-card
      ></q-dialog>
    </template>
    <q-banner v-else class="bg-grey-2 text-grey-9"
      >This session is not available in the local snapshot.</q-banner
    >
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Notify } from 'quasar';
import { useConferenceStore } from '@/stores/conference';
import { useConnectivityStore } from '@/stores/connectivity';
import { listUsers } from '@/services/api/admin';
import {
  addNote,
  assignMc,
  removeNote,
  saveMcContent,
  unassignMc,
  updateNote,
} from '@/services/api/mutations';
import { ApiError } from '@/services/api/client';
import { formatTime } from '@/services/schedule';
import type { User } from '@/types/models';

const route = useRoute();
const conference = useConferenceStore();
const connectivity = useConnectivityStore();
const session = computed(() => conference.sessionById(Number(route.params.id)));
const description = ref('');
const script = ref('');
const users = ref<User[]>([]);
const noteOpen = ref(false);
const editNoteOpen = ref(false);
const deleteNoteOpen = ref(false);
const assignmentOpen = ref(false);
const noteBody = ref('');
const editedNoteBody = ref('');
const editedNoteId = ref<number | null>(null);
const noteIdToDelete = ref<number | null>(null);
const noteError = ref('');
const assignedUserId = ref<number | null>(null);
const savingPreparation = ref(false);
const noteSaving = ref(false);
const noteDeleting = ref(false);
const assignmentSaving = ref(false);
const removingAssignmentId = ref<number | null>(null);
const isAdmin = computed(() => conference.currentUser?.is_admin === true);
const timeAndRoom = computed(() =>
  session.value
    ? `${formatTime(session.value.starts_at)}–${formatTime(session.value.ends_at)} · ${session.value.room?.name ?? 'Room to be confirmed'}`
    : '',
);
const assignableUsers = computed(() =>
  users.value
    .filter((user) => user.enabled && !session.value?.mcs.some((mc) => mc.id === user.id))
    .map((user) => ({ label: user.name, value: user.id })),
);
watch(
  session,
  (value) => {
    description.value = value?.mc_description ?? '';
    script.value = value?.mc_script ?? '';
  },
  { immediate: true },
);
onMounted(async () => {
  if (isAdmin.value) {
    try {
      users.value = (await listUsers()).data;
    } catch {
      Notify.create({ type: 'negative', message: 'Users could not be loaded.' });
    }
  }
});
async function refresh(message: string): Promise<void> {
  await conference.refresh();
  Notify.create({ type: 'positive', message });
}
async function save(): Promise<void> {
  if (!session.value || savingPreparation.value) return;
  savingPreparation.value = true;
  try {
    await saveMcContent(session.value.id, description.value || null, script.value || null);
    await refresh('Session preparation saved.');
  } catch {
    Notify.create({ type: 'negative', message: 'Session preparation could not be saved.' });
  } finally {
    savingPreparation.value = false;
  }
}
function openCreateNote(): void {
  noteBody.value = '';
  noteError.value = '';
  noteOpen.value = true;
}
async function createNote(): Promise<void> {
  if (!session.value || noteSaving.value) return;
  if (!noteBody.value.trim()) {
    noteError.value = 'Enter a technical note.';
    return;
  }

  noteSaving.value = true;
  noteError.value = '';
  try {
    await addNote(session.value.id, noteBody.value.trim());
    noteBody.value = '';
    noteOpen.value = false;
    await refresh('Note added.');
  } catch (error) {
    if (error instanceof ApiError && error.validation?.body?.[0]) {
      noteError.value = error.validation.body[0];
      return;
    }

    Notify.create({ type: 'negative', message: 'Note could not be added.' });
  } finally {
    noteSaving.value = false;
  }
}
function openEditNote(id: number, body: string): void {
  editedNoteId.value = id;
  editedNoteBody.value = body;
  noteError.value = '';
  editNoteOpen.value = true;
}
async function saveEditedNote(): Promise<void> {
  if (editedNoteId.value === null || noteSaving.value) return;
  if (!editedNoteBody.value.trim()) {
    noteError.value = 'Enter a technical note.';
    return;
  }

  noteSaving.value = true;
  noteError.value = '';
  try {
    await updateNote(editedNoteId.value, editedNoteBody.value.trim());
    editNoteOpen.value = false;
    editedNoteId.value = null;
    editedNoteBody.value = '';
    await refresh('Note updated.');
  } catch (error) {
    if (error instanceof ApiError && error.validation?.body?.[0]) {
      noteError.value = error.validation.body[0];
      return;
    }

    Notify.create({ type: 'negative', message: 'Note could not be updated.' });
  } finally {
    noteSaving.value = false;
  }
}
function confirmNoteDeletion(id: number): void {
  noteIdToDelete.value = id;
  deleteNoteOpen.value = true;
}
async function deleteNote(): Promise<void> {
  if (noteIdToDelete.value === null || noteDeleting.value) return;

  noteDeleting.value = true;
  try {
    await removeNote(noteIdToDelete.value);
    deleteNoteOpen.value = false;
    noteIdToDelete.value = null;
    await refresh('Note deleted.');
  } catch {
    Notify.create({ type: 'negative', message: 'Note could not be deleted.' });
  } finally {
    noteDeleting.value = false;
  }
}
function openAssignment(): void {
  assignedUserId.value = null;
  assignmentOpen.value = true;
}
async function createAssignment(): Promise<void> {
  if (!session.value || assignedUserId.value === null || assignmentSaving.value) return;
  assignmentSaving.value = true;
  try {
    await assignMc(session.value.id, assignedUserId.value);
    assignedUserId.value = null;
    assignmentOpen.value = false;
    await refresh('MC assigned.');
  } catch {
    Notify.create({ type: 'negative', message: 'MC could not be assigned.' });
  } finally {
    assignmentSaving.value = false;
  }
}
async function removeAssignment(id: number): Promise<void> {
  if (!session.value || removingAssignmentId.value !== null) return;
  removingAssignmentId.value = id;
  try {
    await unassignMc(session.value.id, id);
    await refresh('MC assignment removed.');
  } catch {
    Notify.create({ type: 'negative', message: 'MC assignment could not be removed.' });
  } finally {
    removingAssignmentId.value = null;
  }
}
</script>
