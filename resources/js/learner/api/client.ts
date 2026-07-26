/**
 * Client HTTP typé de l'API apprenant v1.
 *
 * Session + cookie same-origin ; le token CSRF est lu du cookie XSRF-TOKEN
 * posé par Laravel (middleware web) et renvoyé en header X-XSRF-TOKEN.
 */

import type {
  ActionsResponse,
  BootstrapPayload,
  ChangesPayload,
  ExamResultResponse,
  ExamStartResponse,
  OutboxAction,
  UserProfile,
} from '../domain/types';

const BASE = '/api/learner/v1';

export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly payload: unknown = null,
  ) {
    super(message);
  }
}

/** Erreur réseau (offline, DNS…) — à distinguer d'un refus serveur. */
export class NetworkError extends Error {}

function csrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${BASE}${path}`, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrfToken(),
      },
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (e) {
    throw new NetworkError(e instanceof Error ? e.message : 'Réseau indisponible');
  }

  const data = (await response.json().catch(() => null)) as Record<string, unknown> | null;

  if (!response.ok) {
    const message =
      (data && typeof data.message === 'string' && data.message) ||
      `Erreur ${response.status}`;
    throw new ApiError(response.status, message, data);
  }

  return data as T;
}

export const api = {
  login(login: string, password: string) {
    return request<{ success: boolean; user: UserProfile }>('POST', '/session', {
      login,
      password,
    });
  },

  logout() {
    return request<{ success: boolean }>('DELETE', '/session');
  },

  me() {
    return request<{ success: boolean; user: UserProfile }>('GET', '/me');
  },

  bootstrap() {
    return request<BootstrapPayload & { success: boolean }>('GET', '/bootstrap');
  },

  changes(since: string) {
    return request<ChangesPayload & { success: boolean }>(
      'GET',
      `/changes?since=${encodeURIComponent(since)}`,
    );
  },

  actions(actions: OutboxAction[]) {
    return request<ActionsResponse>('POST', '/actions', {
      actions: actions.map(({ id, type, data }) => ({ id, type, data })),
    });
  },

  startExam(examId: number) {
    return request<ExamStartResponse>('POST', `/exams/${examId}/attempts`);
  },

  saveExamAnswers(examId: number, attemptId: number, answers: Record<string, unknown>) {
    return request<{ success: boolean; time_up?: boolean }>(
      'PATCH',
      `/exams/${examId}/attempts/${attemptId}`,
      { answers },
    );
  },

  completeExam(examId: number, attemptId: number, answers: Record<string, unknown>) {
    return request<ExamResultResponse>(
      'POST',
      `/exams/${examId}/attempts/${attemptId}/complete`,
      { answers },
    );
  },

  reportExamViolation(examId: number, attemptId: number, type: 'screenshot' | 'navigation') {
    return request<{ success: boolean; cancelled: boolean; violations_count?: number; message?: string }>(
      'POST',
      `/exams/${examId}/attempts/${attemptId}/violations`,
      { type },
    );
  },
};
