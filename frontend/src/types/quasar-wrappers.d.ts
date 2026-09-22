declare module 'quasar/wrappers' {
  import type { Pinia } from 'pinia';
  export function boot(callback: (parameters: { store: Pinia }) => void | Promise<void>): unknown;
}
