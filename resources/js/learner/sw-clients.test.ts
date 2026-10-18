import { describe, expect, it } from 'vitest';

import { pickLearnerClient } from './sw-clients';

const ORIGIN = 'https://app.example';
const w = (path: string, focused = false, origin = ORIGIN) => ({ url: origin + path, focused });

describe('pickLearnerClient', () => {
  it('never picks a back-office or server auth tab', () => {
    const clients = [w('/cores/dashboard', true), w('/admin/x'), w('/login'), w('/logout')];
    expect(pickLearnerClient(clients, ORIGIN)).toBeUndefined();
  });

  it('prefers the focused learner tab', () => {
    const a = w('/articles');
    const b = w('/examens', true);
    expect(pickLearnerClient([w('/cores/x', true), a, b], ORIGIN)).toBe(b);
  });

  it('falls back to the first learner tab when none is focused', () => {
    const a = w('/');
    expect(pickLearnerClient([w('/cores/x'), a, w('/profil')], ORIGIN)).toBe(a);
  });

  it('ignores other origins and keeps learner paths that merely start like a denied one', () => {
    const coresLike = w('/coresponsable');
    expect(pickLearnerClient([w('/', true, 'https://other.example'), coresLike], ORIGIN)).toBe(coresLike);
  });
});
