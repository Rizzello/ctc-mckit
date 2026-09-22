import { api } from './client';

export const saveMcContent = (id: number, mcDescription: string | null, mcScript: string | null) =>
  api(`/sessions/${id}/mc-content`, {
    method: 'PATCH',
    body: JSON.stringify({ mc_description: mcDescription, mc_script: mcScript }),
  });
export const addNote = (id: number, body: string) =>
  api(`/sessions/${id}/notes`, { method: 'POST', body: JSON.stringify({ body }) });
export const removeNote = (id: number) => api<void>(`/notes/${id}`, { method: 'DELETE' });
export const updateNote = (id: number, body: string) =>
  api(`/notes/${id}`, { method: 'PATCH', body: JSON.stringify({ body }) });
export const assignMc = (sessionId: number, userId: number) =>
  api(`/sessions/${sessionId}/mcs`, { method: 'POST', body: JSON.stringify({ user_id: userId }) });
export const unassignMc = (sessionId: number, userId: number) =>
  api<void>(`/sessions/${sessionId}/mcs/${userId}`, { method: 'DELETE' });
