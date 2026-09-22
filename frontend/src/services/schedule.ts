import type { ConferenceSession } from '@/types/models';

const timezone = 'Europe/Rome';

export interface PositionedSession {
  session: ConferenceSession;
  top: number;
  height: number;
  bottom: number;
}

export function formatDate(isoDate: string): string {
  return new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    timeZone: timezone,
  }).format(new Date(isoDate));
}

export function formatTime(isoDate: string | null | undefined): string {
  if (!isoDate) {
    return 'Time to be confirmed';
  }

  return new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: timezone,
  }).format(new Date(isoDate));
}

export function dateKey(isoDate: string | null | undefined): string | null {
  if (!isoDate) {
    return null;
  }

  const parts = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: timezone,
  }).formatToParts(new Date(isoDate));
  const part = (type: string) => parts.find((value) => value.type === type)?.value;

  return `${part('year')}-${part('month')}-${part('day')}`;
}

export function sessionDuration(session: ConferenceSession): number | null {
  if (!session.starts_at || !session.ends_at) {
    return null;
  }

  return Math.round((Date.parse(session.ends_at) - Date.parse(session.starts_at)) / 60000);
}

export function positionSessions(
  sessions: ConferenceSession[],
  calendarStart: Date,
): PositionedSession[] {
  const pixelsPerMinute = 3;
  let bottom = 0;

  return sessions
    .filter((session) => session.starts_at && session.ends_at)
    .sort((left, right) => Date.parse(left.starts_at ?? '') - Date.parse(right.starts_at ?? ''))
    .map((session) => {
      const scheduledTop =
        Math.round((Date.parse(session.starts_at ?? '') - calendarStart.getTime()) / 60000) *
        pixelsPerMinute;
      const duration = sessionDuration(session) ?? 0;
      const height = Math.max(duration * pixelsPerMinute, session.is_service_session ? 64 : 96);
      const top = Math.max(scheduledTop, bottom);
      bottom = top + height;

      return { session, top, height, bottom };
    });
}

export function sessionState(
  session: ConferenceSession,
  now = Date.now(),
): 'Current' | 'Upcoming' | 'Past' {
  const startsAt = session.starts_at ? Date.parse(session.starts_at) : Number.NaN;
  const endsAt = session.ends_at ? Date.parse(session.ends_at) : Number.NaN;

  if (!Number.isNaN(startsAt) && !Number.isNaN(endsAt) && startsAt <= now && now < endsAt) {
    return 'Current';
  }

  return !Number.isNaN(startsAt) && startsAt > now ? 'Upcoming' : 'Past';
}
