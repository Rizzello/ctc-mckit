<template>
  <q-page class="flex flex-center q-pa-md">
    <q-card bordered flat class="full-width" style="max-width: 420px">
      <q-card-section>
        <div class="row items-center q-gutter-sm">
          <McKitLogo class="text-primary" size="2.5rem" />
          <div class="text-h5">MC Kit</div>
        </div>
        <div class="text-caption text-grey-7">Come To Code</div>
        <div class="text-h6 q-mt-md">Enter your code</div>
        <p class="text-body1">Enter the six-digit code from your sign-in email.</p>

        <q-banner v-if="error" class="bg-negative text-white q-mb-md" role="alert">
          {{ error }}
        </q-banner>

        <q-form @submit.prevent="verify">
          <q-input
            v-model="otp"
            inputmode="numeric"
            maxlength="6"
            autocomplete="one-time-code"
            label="Six-digit code"
            outlined
            :disable="loading"
          />
          <q-btn
            color="primary"
            class="full-width q-mt-md"
            label="Verify"
            type="submit"
            :loading="loading"
          />
          <q-btn flat class="full-width q-mt-sm" label="Use another email" to="/login" />
        </q-form>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { verifyOtp } from '@/services/api/auth';
import { useConferenceStore } from '@/stores/conference';
import McKitLogo from '@/components/McKitLogo.vue';

const otp = ref('');
const error = ref<string | null>(null);
const loading = ref(false);
const router = useRouter();
const conference = useConferenceStore();

async function verify(): Promise<void> {
  loading.value = true;
  error.value = null;

  try {
    await verifyOtp(otp.value);
    await conference.refresh();
    await router.replace('/agenda');
  } catch {
    error.value = 'This sign-in code is invalid or has expired.';
  } finally {
    loading.value = false;
  }
}
</script>
