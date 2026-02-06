export const useApi = () => {
  const config = useRuntimeConfig();
  const auth = useAuthStore();
  const org = useOrganizationStore();

  const apiFetch = async <T>(path: string, options: RequestInit = {}): Promise<T> => {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/ld+json',
      ...(options.headers as Record<string, string> || {}),
    };

    if (auth.token) {
      headers.Authorization = `Bearer ${auth.token}`;
    }

    if (org.currentOrgId) {
      headers['X-Organization-Id'] = String(org.currentOrgId);
    }

    const response = await fetch(`${config.public.apiBase}${path}`, {
      ...options,
      headers,
    });

    if (response.status === 401) {
      auth.logout();
      navigateTo('/login');
      throw new Error('Unauthorized');
    }

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.detail || error.message || error['hydra:description'] || `HTTP ${response.status}`);
    }

    if (response.status === 204) {
      return {} as T;
    }

    return response.json();
  };

  return {
    get: <T>(path: string) => apiFetch<T>(path),
    post: <T>(path: string, body: unknown) =>
      apiFetch<T>(path, { method: 'POST', body: JSON.stringify(body) }),
    patch: <T>(path: string, body: unknown) =>
      apiFetch<T>(path, {
        method: 'PATCH',
        body: JSON.stringify(body),
        headers: { 'Content-Type': 'application/merge-patch+json' },
      }),
    delete: <T>(path: string) => apiFetch<T>(path, { method: 'DELETE' }),
  };
};
