import { api } from './client';
import type { User } from '@/types/models';

export const requestChallenge = (email: string) =>
  api<{ message: string }>('/auth/challenge', { method: 'POST', body: JSON.stringify({ email }) });
export const verifyOtp = (otp: string) =>
  api<{ data: User }>('/auth/verify-otp', { method: 'POST', body: JSON.stringify({ otp }) });
export const logout = () => api<void>('/logout', { method: 'POST' });
export const currentUser = () => api<{ data: User }>('/me');
