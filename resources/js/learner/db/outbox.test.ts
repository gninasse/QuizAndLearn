import { beforeEach, describe, expect, it } from 'vitest';
import { db } from './schema';
import { enqueue, markError, pending, pendingCount, remove } from './outbox';

describe('outbox', () => {
  beforeEach(async () => {
    await db.outbox.clear();
  });

  it("chaque action reçoit un UUID d'idempotence unique", async () => {
    const a = await enqueue('article_favorite', { article_id: 1, is_favorite: true });
    const b = await enqueue('article_favorite', { article_id: 1, is_favorite: false });

    expect(a.id).not.toBe(b.id);
    expect(a.id).toMatch(/^[0-9a-f-]{36}$/);
  });

  it("conserve l'ordre d'insertion (seq)", async () => {
    await enqueue('article_progress', { article_id: 1 });
    await enqueue('card_review', { card_id: 2, quality: 5 });

    const items = await pending();
    expect(items.map((i) => i.type)).toEqual(['article_progress', 'card_review']);
  });

  it('remove supprime par client_action_id', async () => {
    const a = await enqueue('article_progress', { article_id: 1 });
    await enqueue('card_review', { card_id: 2, quality: 4 });

    await remove([a.id]);

    expect(await pendingCount()).toBe(1);
    expect((await pending())[0]?.type).toBe('card_review');
  });

  it("markError annote l'action sans la retirer", async () => {
    const a = await enqueue('quiz_attempt', { quiz_id: 9 });
    await markError(a.id, 'Quiz non autorisé.');

    const [item] = await pending();
    expect(item?.error).toBe('Quiz non autorisé.');
    expect(await pendingCount()).toBe(1);
  });
});
