import type { components } from './generated/api';

export type User = components['schemas']['User'];
export type Room = components['schemas']['Room'];
export type Speaker = components['schemas']['Speaker'];
export type SessionNote = components['schemas']['Note'];
export type ConferenceSession = components['schemas']['Session'];
export type Snapshot = components['schemas']['Snapshot'];

export interface StoredSnapshot {
  key: 'active';
  ownerId: number;
  version: string;
  generatedAt: string;
  payload: Snapshot;
}
