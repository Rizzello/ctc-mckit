import Dexie, { type Table } from 'dexie';
import type { StoredSnapshot } from '@/types/models';

class McKitDatabase extends Dexie {
  snapshots!: Table<StoredSnapshot, string>;

  constructor() {
    super('mckit');
    this.version(1).stores({ snapshots: 'key, ownerId, version' });
  }
}

export const db = new McKitDatabase();

export async function replaceSnapshot(snapshot: StoredSnapshot): Promise<void> {
  await db.transaction('rw', db.snapshots, async () => {
    await db.snapshots.put(snapshot);
  });
}
