import { db } from './schema';
import type { ActionType, OutboxAction } from '../domain/types';

/**
 * Outbox pattern : chaque mutation locale est journalisée avec un UUID
 * d'idempotence puis rejouée vers POST /actions dès que possible.
 */

export async function enqueue(type: ActionType, data: Record<string, unknown>): Promise<OutboxAction> {
  const action: OutboxAction = {
    id: crypto.randomUUID(),
    type,
    data,
    created_at: new Date().toISOString(),
    error: null,
  };

  await db.outbox.add(action);
  return action;
}

export async function pending(): Promise<OutboxAction[]> {
  return db.outbox.orderBy('seq').toArray();
}

export async function pendingCount(): Promise<number> {
  return db.outbox.count();
}

export async function remove(clientActionIds: string[]): Promise<void> {
  await db.outbox.where('id').anyOf(clientActionIds).delete();
}

export async function markError(clientActionId: string, message: string): Promise<void> {
  await db.outbox.where('id').equals(clientActionId).modify({ error: message });
}
