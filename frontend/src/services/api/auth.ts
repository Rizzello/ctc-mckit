import { api } from './client';
import type { User } from '@/types/models';

export const requestChallenge = (email: string) =>
  api<{ message: string }>('/auth/challenge', { method: 'POST', body: JSON.stringify({ email }) });
export const verifyOtp = (otp: string) =>
  api<{ data: User }>('/auth/verify-otp', { method: 'POST', body: JSON.stringify({ otp }) });
export const logout = () => api<void>('/logout', { method: 'POST' });

export class StaleAuthValidationError extends Error {
  constructor() {
    super('The authentication validation belongs to an expired lifecycle.');
    this.name = 'StaleAuthValidationError';
  }
}

let authLifecycleGeneration = 0;
let currentUserRequest: {
  generation: number;
  promise: Promise<{ data: User }>;
} | null = null;

export function getAuthLifecycleGeneration(): number {
  return authLifecycleGeneration;
}

export function isAuthLifecycleCurrent(generation: number): boolean {
  return generation === authLifecycleGeneration;
}

export function invalidateAuthLifecycle(): void {
  authLifecycleGeneration += 1;
  currentUserRequest = null;
}

export function currentUser(): Promise<{ data: User }> {
  const generation = authLifecycleGeneration;

  if (currentUserRequest?.generation === generation) {
    return currentUserRequest.promise;
  }

  const promise = api<{ data: User }>('/me')
    .then((result) => {
      if (!isAuthLifecycleCurrent(generation)) {
        throw new StaleAuthValidationError();
      }

      return result;
    })
    .finally(() => {
      if (currentUserRequest?.generation === generation) {
        currentUserRequest = null;
      }
    });

  currentUserRequest = { generation, promise };

  return promise;
}
