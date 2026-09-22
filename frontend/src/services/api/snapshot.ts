import { api } from './client';
import type { Snapshot } from '@/types/models';
import { z } from 'zod';

const snapshotSchema = z.object({
  version: z.string(),
  generated_at: z.string(),
  current_user: z.object({
    id: z.number(),
    name: z.string(),
    email: z.string(),
    is_admin: z.boolean(),
    enabled: z.boolean(),
  }),
  rooms: z.array(z.object({ id: z.number(), sessionize_id: z.string(), name: z.string() })),
  speakers: z.array(
    z.object({ id: z.number(), sessionize_id: z.string(), name: z.string() }).passthrough(),
  ),
  sessions: z.array(
    z
      .object({
        id: z.number(),
        sessionize_id: z.string(),
        title: z.string(),
        speakers: z.array(z.unknown()),
        mcs: z.array(z.unknown()),
        notes: z.array(z.unknown()),
      })
      .passthrough(),
  ),
});

export async function fetchSnapshot(): Promise<Snapshot> {
  return snapshotSchema.parse(await api<unknown>('/snapshot')) as Snapshot;
}
