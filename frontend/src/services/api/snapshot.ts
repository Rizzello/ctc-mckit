import { api } from './client';
import type { Snapshot } from '@/types/models';
import { z } from 'zod';

const userSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string(),
  is_admin: z.boolean(),
  enabled: z.boolean(),
});

const roomSchema = z.object({
  id: z.number(),
  sessionize_id: z.string(),
  name: z.string(),
});

const speakerSchema = z.object({
  id: z.number(),
  sessionize_id: z.string(),
  name: z.string(),
  tagline: z.string().nullable(),
  bio: z.string().nullable(),
  photo_url: z.string().nullable(),
  links: z.array(z.unknown()),
});

const assignedMcSchema = z.object({ id: z.number(), name: z.string() });

const noteSchema = z.object({
  id: z.number(),
  body: z.string(),
  created_at: z.iso.datetime({ offset: true }),
  updated_at: z.iso.datetime({ offset: true }),
});

const dateTimeSchema = z.iso.datetime({ offset: true });

const sessionSchema = z.object({
  id: z.number(),
  sessionize_id: z.string(),
  title: z.string(),
  description: z.string().nullable(),
  room_id: z.number().nullable(),
  starts_at: dateTimeSchema.nullable(),
  ends_at: dateTimeSchema.nullable(),
  status: z.string().nullable(),
  is_confirmed: z.boolean(),
  is_service_session: z.boolean(),
  is_plenum_session: z.boolean(),
  categories: z.array(z.string()),
  mc_description: z.string().nullable(),
  mc_script: z.string().nullable(),
  room: roomSchema.nullable(),
  speakers: z.array(speakerSchema),
  mcs: z.array(assignedMcSchema),
  notes: z.array(noteSchema),
});

export const snapshotSchema = z.object({
  version: z.string(),
  generated_at: dateTimeSchema,
  current_user: userSchema,
  rooms: z.array(roomSchema),
  speakers: z.array(speakerSchema),
  sessions: z.array(sessionSchema),
});

export async function fetchSnapshot(): Promise<Snapshot> {
  return snapshotSchema.parse(await api<unknown>('/snapshot'));
}
