import { api } from './client';
import type { User } from '@/types/models';

export interface SyncStatus {
  configured: boolean;
  last_sync: SyncRun | null;
  last_successful_sync: SyncRun | null;
}

export interface SyncRun {
  id: number;
  status: 'queued' | 'running' | 'completed' | 'failed';
  started_at: string | null;
  finished_at: string | null;
  stats: { rooms: number; speakers: number; sessions: number; removed: number } | null;
}

export const listUsers = () => api<{ data: User[] }>('/users');
export const createUser = (attributes: Omit<User, 'id'>) =>
  api<{ data: User }>('/users', { method: 'POST', body: JSON.stringify(attributes) });
export const updateUser = (id: number, attributes: Omit<User, 'id'>) =>
  api<{ data: User }>(`/users/${id}`, { method: 'PATCH', body: JSON.stringify(attributes) });
export const sessionizeStatus = () => api<SyncStatus>('/sessionize');
export const queueSessionizeSync = () => api<SyncRun>('/sessionize/sync', { method: 'POST' });
