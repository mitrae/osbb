import { defineStore } from 'pinia';

interface Membership {
  id: number;
  organization: {
    id: number;
    name: string;
    address?: string;
    city?: string;
    '@id'?: string;
  };
  role: string;
}

interface ResidentOrg {
  orgId: number;
  orgName: string;
  orgAddress?: string;
  orgCity?: string;
}

interface OrgState {
  currentOrgId: number | null;
  memberships: Membership[];
  residentOrgs: ResidentOrg[];
}

export const useOrganizationStore = defineStore('organization', {
  state: (): OrgState => ({
    currentOrgId: null,
    memberships: [],
    residentOrgs: [],
  }),

  getters: {
    currentOrg: (state) => {
      const fromMembership = state.memberships.find((m) => m.organization.id === state.currentOrgId)?.organization;
      if (fromMembership) return fromMembership;
      const fromResident = state.residentOrgs.find((r) => r.orgId === state.currentOrgId);
      if (fromResident) return { id: fromResident.orgId, name: fromResident.orgName, address: fromResident.orgAddress };
      return null;
    },
    currentMembership: (state) =>
      state.memberships.find((m) => m.organization.id === state.currentOrgId) ?? null,
    allOrgs(): Array<{ id: number; name: string; address?: string; role?: string; source: 'membership' | 'resident' }> {
      const orgs: Array<{ id: number; name: string; address?: string; role?: string; source: 'membership' | 'resident' }> = [];
      const seenIds = new Set<number>();

      for (const m of this.memberships) {
        seenIds.add(m.organization.id);
        orgs.push({
          id: m.organization.id,
          name: m.organization.name,
          address: m.organization.address,
          role: m.role,
          source: 'membership',
        });
      }

      for (const r of this.residentOrgs) {
        if (!seenIds.has(r.orgId)) {
          orgs.push({
            id: r.orgId,
            name: r.orgName,
            address: r.orgAddress,
            source: 'resident',
          });
        }
      }

      return orgs;
    },
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
        // Platform admins: load all orgs directly
        if (auth.isPlatformAdmin) {
          await this.loadAllOrgsForAdmin();
        } else {
          // Load memberships
          const response = await fetch(`${config.public.apiBase}/api/organization_memberships`, {
            headers: {
              Authorization: `Bearer ${auth.token}`,
              Accept: 'application/json',
            },
          });
          if (response.ok) {
            const data = await response.json();
            const members = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
            this.memberships = members.map((m: any) => ({
              id: m.id,
              organization: typeof m.organization === 'string'
                ? { id: parseInt(m.organization.split('/').pop()), name: '', '@id': m.organization }
                : { id: m.organization.id, name: m.organization.name || '', address: m.organization.address, city: m.organization.city, '@id': m.organization['@id'] },
              role: m.role,
            }));
          }

          // Load resident-linked organizations
          await this.loadResidentOrgs();
        }

        // Auto-select first org if no current org
        if (!this.currentOrgId && this.allOrgs.length > 0) {
          this.setCurrentOrg(this.allOrgs[0].id);
        }
      } catch {
        // ignore
      }
    },

    async loadAllOrgsForAdmin() {
      const config = useRuntimeConfig();
      const auth = useAuthStore();
      if (!auth.token) return;

      try {
        const response = await fetch(`${config.public.apiBase}/api/organizations`, {
          headers: {
            Authorization: `Bearer ${auth.token}`,
            Accept: 'application/json',
          },
        });
        if (!response.ok) return;
        const data = await response.json();
        const orgs = Array.isArray(data) ? data : (Array.isArray(data) ? data : (data['hydra:member'] || data.member || []));
        // Store as memberships with ROLE_ADMIN so allOrgs picks them up
        this.memberships = orgs.map((o: any) => ({
          id: 0,
          organization: { id: o.id, name: o.name, address: o.address, city: o.city, '@id': o['@id'] },
          role: 'ROLE_ADMIN',
        }));
      } catch {
        // ignore
      }
    },

    async loadResidentOrgs() {
      const config = useRuntimeConfig();
      const auth = useAuthStore();
      if (!auth.token) return;

      try {
        const orgResponse = await fetch(`${config.public.apiBase}/api/organizations`, {
          headers: {
            Authorization: `Bearer ${auth.token}`,
            Accept: 'application/json',
          },
        });
        if (!orgResponse.ok) return;

        const orgData = await orgResponse.json();
        const allOrgs = Array.isArray(orgData) ? orgData : (orgData['hydra:member'] || orgData.member || []);

        const memberOrgIds = new Set(this.memberships.map((m) => m.organization.id));
        const residentOrgs: ResidentOrg[] = [];

        for (const org of allOrgs) {
          if (memberOrgIds.has(org.id)) continue;

          const resResponse = await fetch(`${config.public.apiBase}/api/residents?user=/api/users/${auth.user?.id}`, {
            headers: {
              Authorization: `Bearer ${auth.token}`,
              Accept: 'application/json',
              'X-Organization-Id': String(org.id),
            },
          });
          if (!resResponse.ok) continue;
          const resData = await resResponse.json();
          const residents = Array.isArray(resData) ? resData : (resData['hydra:member'] || resData.member || []);
          if (residents.length > 0) {
            residentOrgs.push({
              orgId: org.id,
              orgName: org.name,
              orgAddress: org.address,
              orgCity: org.city,
            });
          }
        }

        this.residentOrgs = residentOrgs;
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
      this.residentOrgs = [];
      if (import.meta.client) {
        localStorage.removeItem('current_org_id');
      }
    },
  },
});
