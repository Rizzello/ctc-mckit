import { describe, expect, it, vi } from 'vitest';
import { fetchSnapshot, snapshotSchema } from './snapshot';

const { api } = vi.hoisted(() => ({ api: vi.fn() }));

vi.mock('./client', () => ({ api }));

describe('snapshot API validation', () => {
  function validPayload(overrides: Record<string, unknown> = {}) {
    return {
      version: 'version-1',
      generated_at: '2026-09-23T05:15:00Z',
      current_user: {
        id: 1,
        name: 'MC One',
        email: 'mc@example.test',
        is_admin: false,
        enabled: true,
      },
      rooms: [],
      speakers: [],
      sessions: [
        {
          id: 1,
          sessionize_id: 'session-1',
          title: 'Opening',
          description: null,
          room_id: null,
          starts_at: '2026-09-23T07:15:00+02:00',
          ends_at: null,
          status: null,
          is_confirmed: true,
          is_service_session: false,
          is_plenum_session: false,
          categories: [],
          mc_description: null,
          mc_script: null,
          room: null,
          speakers: [],
          mcs: [],
          notes: [],
        },
      ],
      ...overrides,
    };
  }

  it('rejects a payload missing fields consumed by the offline UI', async () => {
    api.mockResolvedValue({
      version: 'version-1',
      generated_at: '2027-10-14T09:00:00+02:00',
      current_user: {
        id: 1,
        name: 'MC One',
        email: 'mc@example.test',
        is_admin: false,
        enabled: true,
      },
      rooms: [],
      speakers: [],
      sessions: [{ id: 1, title: 'Incomplete' }],
    });

    await expect(fetchSnapshot()).rejects.toThrow();
  });

  it('accepts UTC and offset timestamps while preserving nullable fields', () => {
    expect(() => snapshotSchema.parse(validPayload())).not.toThrow();
  });

  it('rejects arbitrary strings in date-time fields', () => {
    expect(() => snapshotSchema.parse(validPayload({ generated_at: 'banana' }))).toThrow();
  });
});
