<template>
  <q-page class="flex flex-center q-pa-md">
    <q-card bordered flat class="full-width" style="max-width: 420px">
      <q-card-section>
        <div class="row items-center q-gutter-sm">
          <McKitLogo class="text-primary" size="2.5rem" />
          <div class="text-h5">MC Kit</div>
        </div>
        <div class="text-caption text-grey-7">Come To Code</div>
        <p class="text-body1">Receive a sign-in link and a six-digit code.</p>

        <q-banner v-if="error" class="bg-negative text-white q-mb-md" role="alert">
          {{ error }}
        </q-banner>

        <q-form @submit.prevent="send">
          <q-input
            v-model="email"
            type="email"
            label="Email"
            outlined
            autocomplete="email"
            :disable="loading"
            :error="Boolean(error)"
          />
          <q-btn
            color="primary"
            class="full-width q-mt-md"
            label="Send login link and code"
            type="submit"
            :loading="loading"
          />
        </q-form>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { requestChallenge } from '@/services/api/auth';
import McKitLogo from '@/components/McKitLogo.vue';

const email = ref('');
const route = useRoute();
const error = ref<string | null>(
  route.query.error === 'magic-link-invalid'
    ? 'This sign-in link is invalid or has expired.'
    : null,
);
const loading = ref(false);
const router = useRouter();

async function send(): Promise<void> {
  loading.value = true;
  error.value = null;

  try {
    await requestChallenge(email.value);
    await router.push('/login/code');
  } catch {
    error.value = 'Unable to send the sign-in message. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
