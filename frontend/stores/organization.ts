import { defineStore } from 'pinia';

interface Membership {
  id: number;
  organization: {
    id: number;
    name: string;
    address?: string;
    '@id'?: string;
  };
  role: string;
  status: string;
}

interface OrgState {
  currentOrgId: number | null;
  memberships: Membership[];
}

export const useOrganizationStore = defineStore('organization', {
  state: (): OrgState => ({
    currentOrgId: null,
    memberships: [],
  }),

  getters: {
    currentOrg: (state) =>
      state.memberships.find((m) => m.organization.id === state.currentOrgId)?.organization ?? null,
    currentMembership: (state) =>
      state.memberships.find((m) => m.organization.id === state.currentOrgId) ?? null,
    approvedMemberships: (state) =>
      state.memberships.filter((m) => m.status === 'approved'),
    isOrgAdmin(): boolean {
      return this.currentMembership?.role === 'ROLE_ADMIN' || false;
    },
    isOrgManager(): boolean {
      const role = this.currentMembership?.role;
      return role === 'ROLE_ADMIN' || role === 'ROLE_MANAGER' || false;
    },
    hasOrg: (state) => !!state.currentOrgId,
  },

  actions: {
    async loadMemberships() {
      const config = useRuntimeConfig();
      const auth = useAuthStore();
      if (!auth.token) return;

      try {
        const response = await fetch(`${config.public.apiBase}/api/organization_memberships`, {
          headers: {
            Authorization: `Bearer ${auth.token}`,
            Accept: 'application/json',
          },
        });
        if (!response.ok) return;
        const data = await response.json();
        const members = data['hydra:member'] || data.member || [];
        this.memberships = members.map((m: any) => ({
          id: m.id,
          organization: typeof m.organization === 'string'
            ? { id: parseInt(m.organization.split('/').pop()), name: '', '@id': m.organization }
            : { id: m.organization.id, name: m.organization.name || '', address: m.organization.address, '@id': m.organization['@id'] },
          role: m.role,
          status: m.status,
        }));

        // Auto-select first approved membership if no current org
        if (!this.currentOrgId && this.approvedMemberships.length > 0) {
          this.setCurrentOrg(this.approvedMemberships[0].organization.id);
        }
      } catch {
        // ignore
      }
    },

    setCurrentOrg(id: number | null) {
      this.currentOrgId = id;
      if (import.meta.client) {
        if (id) {
          localStorage.setItem('current_org_id', String(id));
        } else {
          localStorage.removeItem('current_org_id');
        }
      }
    },

    restore() {
      if (import.meta.client) {
        const id = localStorage.getItem('current_org_id');
        if (id) {
          this.currentOrgId = parseInt(id);
        }
      }
    },

    clear() {
      this.currentOrgId = null;
      this.memberships = [];
      if (import.meta.client) {
        localStorage.removeItem('current_org_id');
      }
    },
  },
});
