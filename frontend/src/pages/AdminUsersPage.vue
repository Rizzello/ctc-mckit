<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div>
        <div class="text-overline text-primary">Administration</div>
        <h1 class="text-h5 q-my-none">Users</h1>
      </div>
      <q-btn color="primary" label="Add user" :disable="connectivity.offline" @click="openCreate" />
    </div>

    <q-banner v-if="error" class="bg-negative text-white q-mb-md">{{ error }}</q-banner>
    <q-list v-if="users.length" bordered separator>
      <q-item
        v-for="user in users"
        :key="user.id"
        clickable
        :disable="connectivity.offline"
        @click="openEdit(user)"
      >
        <q-item-section>
          <q-item-label>{{ user.name }}</q-item-label>
          <q-item-label caption>{{ user.email }}</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-badge :color="user.enabled ? 'positive' : 'grey-7'">{{
            user.enabled ? 'Enabled' : 'Disabled'
          }}</q-badge>
          <q-badge v-if="user.is_admin" color="primary" class="q-mt-xs">Admin</q-badge>
        </q-item-section>
        <q-item-section side><q-icon name="chevron_right" /></q-item-section>
      </q-item>
    </q-list>
    <q-banner v-else class="bg-grey-2 text-grey-9 rounded-borders"
      >No users are available.</q-banner
    >

    <q-dialog v-model="dialogOpen">
      <q-card style="min-width: min(92vw, 420px)">
        <q-card-section
          ><div class="text-h6">{{ editingId ? 'Edit user' : 'Add user' }}</div></q-card-section
        >
        <q-card-section class="q-gutter-md">
          <q-input
            v-model="form.name"
            outlined
            label="Name"
            :disable="saving || connectivity.offline"
            :error="Boolean(fieldErrors.name)"
            :error-message="fieldErrors.name"
          />
          <q-input
            v-model="form.email"
            outlined
            type="email"
            label="Email"
            :disable="saving || connectivity.offline"
            :error="Boolean(fieldErrors.email)"
            :error-message="fieldErrors.email"
          />
          <q-banner v-if="formError" class="bg-negative text-white rounded-borders">{{
            formError
          }}</q-banner>
          <q-checkbox
            v-model="form.is_admin"
            label="Administrator"
            :disable="saving || connectivity.offline"
          />
          <q-checkbox
            v-model="form.enabled"
            label="Enabled"
            :disable="saving || connectivity.offline"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            label="Save"
            :loading="saving"
            :disable="connectivity.offline"
            @click="save"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { Notify } from 'quasar';
import { ApiError } from '@/services/api/client';
import { createUser, listUsers, updateUser } from '@/services/api/admin';
import { useConnectivityStore } from '@/stores/connectivity';
import type { User } from '@/types/models';

const connectivity = useConnectivityStore();
const users = ref<User[]>([]);
const error = ref<string | null>(null);
const dialogOpen = ref(false);
const editingId = ref<number | null>(null);
const saving = ref(false);
const formError = ref<string | null>(null);
const fieldErrors = reactive({ name: '', email: '' });
const form = reactive<Omit<User, 'id'>>({ name: '', email: '', is_admin: false, enabled: true });

onMounted(load);

async function load(): Promise<void> {
  try {
    users.value = (await listUsers()).data;
  } catch {
    error.value = 'Users could not be loaded.';
  }
}

function openCreate(): void {
  editingId.value = null;
  Object.assign(form, { name: '', email: '', is_admin: false, enabled: true });
  clearFormErrors();
  dialogOpen.value = true;
}

function openEdit(user: User): void {
  if (connectivity.offline) return;
  editingId.value = user.id;
  Object.assign(form, {
    name: user.name,
    email: user.email,
    is_admin: user.is_admin,
    enabled: user.enabled,
  });
  clearFormErrors();
  dialogOpen.value = true;
}

async function save(): Promise<void> {
  clearFormErrors();
  saving.value = true;
  try {
    if (editingId.value) await updateUser(editingId.value, form);
    else await createUser(form);
    dialogOpen.value = false;
    await load();
    Notify.create({
      type: 'positive',
      message: editingId.value ? 'User updated.' : 'User created.',
    });
  } catch (error) {
    if (error instanceof ApiError && error.validation) {
      fieldErrors.name = error.validation.name?.[0] ?? '';
      fieldErrors.email = error.validation.email?.[0] ?? '';
      formError.value = error.validation.enabled?.[0] ?? error.validation.user?.[0] ?? null;
    } else {
      Notify.create({ type: 'negative', message: 'The user could not be saved.' });
    }
  } finally {
    saving.value = false;
  }
}

function clearFormErrors(): void {
  fieldErrors.name = '';
  fieldErrors.email = '';
  formError.value = null;
}
</script>
