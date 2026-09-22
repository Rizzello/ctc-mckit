import { describe, expect, it } from 'vitest';
import { sessionState } from '@/services/schedule';
import type { ConferenceSession } from '@/types/models';

function session(startsAt: string, endsAt: string): ConferenceSession {
  return {
    id: 1,
    sessionize_id: 'session-1',
    title: 'Opening session',
    description: null,
    room: null,
    starts_at: startsAt,
    ends_at: endsAt,
    is_confirmed: true,
    is_service_session: false,
    is_plenum_session: false,
    categories: [],
    speakers: [],
    mcs: [],
    notes: [],
    mc_description: null,
    mc_script: null,
  };
}

describe('session state', () => {
  it('includes the start boundary and excludes the end boundary', () => {
    const scheduledSession = session('2027-10-14T09:00:00+02:00', '2027-10-14T10:00:00+02:00');

    expect(sessionState(scheduledSession, Date.parse(scheduledSession.starts_at ?? ''))).toBe(
      'Current',
    );
    expect(sessionState(scheduledSession, Date.parse(scheduledSession.ends_at ?? ''))).toBe('Past');
  });
});
