<template>
  <span
    class="mc-kit-logo"
    :style="{ width: size, height: size }"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
    v-html="logo"
  />
</template>

<script setup lang="ts">
import logo from '@/assets/mc-kit-logo.svg?raw';

withDefaults(
  defineProps<{
    size?: string;
    label?: string;
  }>(),
  {
    size: '2rem',
  },
);
</script>

<style scoped>
.mc-kit-logo {
  display: inline-block;
  flex: none;
}

.mc-kit-logo :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
}
</style>
