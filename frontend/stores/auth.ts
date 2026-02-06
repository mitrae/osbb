import { defineStore } from 'pinia';

interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  roles: string[];
  isPlatformAdmin: boolean;
}

interface AuthState {
  token: string | null;
  user: User | null;
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: null,
    user: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isManager: (state) =>
      state.user?.roles?.some((r) => ['ROLE_MANAGER', 'ROLE_ADMIN'].includes(r)) ?? false,
    isAdmin: (state) => state.user?.roles?.includes('ROLE_ADMIN') ?? false,
    isPlatformAdmin: (state) => state.user?.isPlatformAdmin ?? false,
  },

  actions: {
    async login(email: string, password: string) {
      const config = useRuntimeConfig();
      const response = await fetch(`${config.public.apiBase}/api/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });

      if (!response.ok) {
        throw new Error('Invalid credentials');
      }

      const data = await response.json();
      this.setTokenAndUser(data.token);

      // Load org memberships after login
      const org = useOrganizationStore();
      await org.loadMemberships();
    },

    setTokenAndUser(token: string) {
      this.token = token;

      // Decode JWT payload to get user info
      const payload = JSON.parse(atob(token.split('.')[1]));

      // Strip legacy type prefix from username if present
      let email = payload.username;
      if (email.includes(':')) {
        email = email.substring(email.indexOf(':') + 1);
      }

      this.user = {
        id: payload.id,
        email,
        firstName: payload.firstName || '',
        lastName: payload.lastName || '',
        roles: payload.roles || [],
        isPlatformAdmin: payload.isPlatformAdmin || false,
      };

      if (import.meta.client) {
        localStorage.setItem('auth_token', token);
        localStorage.setItem('auth_user', JSON.stringify(this.user));
      }
    },

    async register(userData: {
      email: string;
      password: string;
      firstName: string;
      lastName: string;
      phone?: string;
    }) {
      const config = useRuntimeConfig();
      const response = await fetch(`${config.public.apiBase}/api/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(userData),
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || error.errors ? JSON.stringify(error.errors) : 'Registration failed');
      }

      return response.json();
    },

    logout() {
      this.token = null;
      this.user = null;
      if (import.meta.client) {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
      }
      const org = useOrganizationStore();
      org.clear();
      navigateTo('/login');
    },

    restore() {
      if (import.meta.client) {
        const token = localStorage.getItem('auth_token');
        const user = localStorage.getItem('auth_user');
        if (token && user) {
          this.token = token;
          this.user = JSON.parse(user);
        }
      }
    },
  },
});
