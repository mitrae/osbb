<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">{{ orgName ? `${orgName} — Members` : 'Members' }}</h1>

    <!-- Add Member -->
    <div v-if="isOrgAdmin && !showAddForm" style="margin-bottom:1rem">
      <button class="btn btn-primary" @click="showAddForm = true">+ Add Member</button>
    </div>

    <div v-if="showAddForm" class="card" style="margin-bottom:1rem">
      <h3 style="margin-top:0">Add Member</h3>
      <div style="display:flex;gap:0.5rem;align-items:end;flex-wrap:wrap">
        <div class="form-group" style="margin:0;flex:1;min-width:200px">
          <label style="font-size:0.85rem">Search users</label>
          <input
            v-model="userSearch"
            type="text"
            placeholder="Type name or email..."
            @input="searchUsers"
            class="search-input"
            style="margin-bottom:0"
          />
        </div>
        <div class="form-group" style="margin:0;min-width:120px">
          <label style="font-size:0.85rem">Role</label>
          <select v-model="newMemberRole" class="role-select">
            <option value="ROLE_MANAGER">Manager</option>
            <option value="ROLE_ADMIN">Admin</option>
          </select>
        </div>
      </div>

      <div v-if="userSearchResults.length > 0" class="search-results">
        <div
          v-for="u in userSearchResults"
          :key="u.id"
          class="search-result-item"
          :class="{ selected: selectedUser?.id === u.id }"
          @click="selectedUser = u"
        >
          <strong>{{ u.firstName }} {{ u.lastName }}</strong>
          <span class="email">{{ u.email }}</span>
        </div>
      </div>
      <div v-else-if="userSearch.length >= 2 && !searchingUsers" style="font-size:0.85rem;color:#666;margin-top:0.5rem">
        No users found.
      </div>
      <div v-if="searchingUsers" style="font-size:0.85rem;color:#666;margin-top:0.5rem">
        Searching...
      </div>

      <div v-if="selectedUser" style="margin-top:0.75rem;padding:0.5rem 0.75rem;background:#f0f7ff;border-radius:6px;display:flex;justify-content:space-between;align-items:center">
        <span>
          Selected: <strong>{{ selectedUser.firstName }} {{ selectedUser.lastName }}</strong> ({{ selectedUser.email }})
        </span>
        <button class="btn btn-primary" style="font-size:0.85rem" @click="addMember" :disabled="addingMember">
          {{ addingMember ? 'Adding...' : 'Add' }}
        </button>
      </div>

      <div style="margin-top:0.75rem">
        <button class="btn" style="background:#e0e0e0;font-size:0.85rem" @click="cancelAdd">Cancel</button>
      </div>
      <p v-if="addError" class="error" style="margin-top:0.5rem">{{ addError }}</p>
    </div>

    <div class="card" v-if="!loading">
      <div class="filters">
        <input
          v-model="search"
          type="text"
          placeholder="Search by name, phone, apartment..."
          class="search-input"
        />
        <select v-if="buildings.length > 2" v-model="selectedBuildingId" class="building-filter">
          <option :value="null">All buildings</option>
          <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.address }}</option>
        </select>
      </div>

      <table class="members-table" v-if="filtered.length > 0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Apartment</th>
            <th>Role</th>
            <th v-if="auth.isPlatformAdmin"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in filtered" :key="m.id">
            <td>
              <NuxtLink v-if="m.userId" :to="`/organizations/${route.params.id}/members/${m.userId}`" class="member-link">
                <strong>{{ m.userName }}</strong>
              </NuxtLink>
              <strong v-else>{{ m.userName }}</strong>
              <div class="email">{{ m.userEmail }}</div>
            </td>
            <td>{{ m.phone || '—' }}</td>
            <td>
              <span v-if="m.apartmentNumber">
                Apt {{ m.apartmentNumber }}
                <span v-if="m.buildingAddress" class="building-label">{{ m.buildingAddress }}</span>
              </span>
              <span v-else>—</span>
            </td>
            <td>
              <template v-if="m.role === 'ROLE_RESIDENT'">
                <span class="role-badge resident">Resident</span>
              </template>
              <select
                v-else
                :value="m.role"
                @change="updateRole(m.id, ($event.target as HTMLSelectElement).value)"
                class="role-select"
              >
                <option value="ROLE_MANAGER">Manager</option>
                <option value="ROLE_ADMIN">Admin</option>
              </select>
            </td>
            <td v-if="auth.isPlatformAdmin">
              <button
                class="btn btn-danger"
                style="font-size:0.75rem;padding:0.2rem 0.5rem"
                @click="removeMember(m)"
              >Remove</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No members match your search.</p>
    </div>
    <div class="card" v-else>Loading...</div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="success" class="success">{{ success }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();

const allMembers = ref<any[]>([]);
const buildings = ref<any[]>([]);
const orgName = ref('');
const loading = ref(true);
const error = ref('');
const success = ref('');
const search = ref('');
const selectedBuildingId = ref<number | null>(null);

// Add member state
const showAddForm = ref(false);
const userSearch = ref('');
const userSearchResults = ref<any[]>([]);
const searchingUsers = ref(false);
const selectedUser = ref<any>(null);
const newMemberRole = ref('ROLE_MANAGER');
const addingMember = ref(false);
const addError = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const orgId = computed(() => Number(route.params.id));

const membership = computed(() =>
  orgStore.memberships.find((m) => m.organization.id === orgId.value)
);

const isOrgAdmin = computed(() => {
  if (auth.isPlatformAdmin) return true;
  return membership.value?.role === 'ROLE_ADMIN';
});

const existingUserIds = computed(() =>
  new Set(allMembers.value.map((m) => m.userId).filter(Boolean))
);

const filtered = computed(() => {
  let list = allMembers.value;

  if (selectedBuildingId.value) {
    list = list.filter((m) => m.buildingId === selectedBuildingId.value);
  }

  const q = search.value.toLowerCase().trim();
  if (q) {
    list = list.filter((m) =>
      m.userName.toLowerCase().includes(q) ||
      (m.phone && m.phone.toLowerCase().includes(q)) ||
      (m.userEmail && m.userEmail.toLowerCase().includes(q)) ||
      (m.apartmentNumber && m.apartmentNumber.toLowerCase().includes(q))
    );
  }

  return list;
});

function withOrgContext<T>(fn: () => Promise<T>): Promise<T> {
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId.value);
  return fn().finally(() => {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
  });
}

async function loadData() {
  loading.value = true;
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId.value);

  try {
    // Load core data (org info + memberships)
    const [orgData, membData] = await Promise.all([
      api.get<any>(`/api/organizations/${orgId.value}`),
      api.get<any>('/api/organization_memberships'),
    ]);

    orgName.value = orgData.name;

    // Load enrichment data (residents + buildings) — optional, don't block members list
    let resData: any = { member: [] };
    let bldData: any = { member: [] };
    try {
      [resData, bldData] = await Promise.all([
        api.get<any>('/api/residents?pagination=false'),
        api.get<any>('/api/buildings?pagination=false'),
      ]);
    } catch {
      // enrichment data unavailable — members still display without apartment info
    }

    buildings.value = (bldData['hydra:member'] || bldData['member'] || []).map((b: any) => ({
      id: b.id,
      address: b.address,
    }));

    // Build a map: userId → { apartmentNumber, buildingId, buildingAddress }
    const residents = resData['hydra:member'] || resData['member'] || [];
    const userResidentMap: Record<number, { apartmentNumber: string; buildingId: number | null; buildingAddress: string }> = {};
    for (const r of residents) {
      const userId = typeof r.user === 'object' ? r.user?.id : null;
      if (!userId) continue;
      const aptNumber = typeof r.apartment === 'object' ? r.apartment?.number : null;
      const bldg = typeof r.apartment === 'object' && typeof r.apartment?.building === 'object'
        ? r.apartment.building
        : null;
      if (!userResidentMap[userId]) {
        userResidentMap[userId] = {
          apartmentNumber: aptNumber || '',
          buildingId: bldg?.id || null,
          buildingAddress: bldg?.address || '',
        };
      }
    }

    const members = membData['hydra:member'] || membData['member'] || [];
    allMembers.value = members.map((m: any) => {
      const user = typeof m.user === 'object' ? m.user : null;
      const userId = user?.id;
      const resInfo = userId ? userResidentMap[userId] : null;
      return {
        id: m.id,
        userId: userId || null,
        role: m.role,
        userName: user ? `${user.firstName || ''} ${user.lastName || ''}`.trim() || user.email : String(m.user),
        userEmail: user?.email || '',
        phone: user?.phone || '',
        apartmentNumber: resInfo?.apartmentNumber || '',
        buildingId: resInfo?.buildingId || null,
        buildingAddress: resInfo?.buildingAddress || '',
      };
    });
  } catch {
    error.value = 'Failed to load members';
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    loading.value = false;
  }
}

async function updateRole(id: number, role: string) {
  error.value = '';
  try {
    await api.patch(`/api/organization_memberships/${id}`, { role });
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

function searchUsers() {
  selectedUser.value = null;
  if (searchTimeout) clearTimeout(searchTimeout);

  if (userSearch.value.length < 2) {
    userSearchResults.value = [];
    return;
  }

  searchTimeout = setTimeout(async () => {
    searchingUsers.value = true;
    try {
      const q = encodeURIComponent(userSearch.value);
      // SearchFilter uses AND between fields, so search each independently and merge
      const [byEmail, byFirst, byLast] = await Promise.all([
        api.get<any>(`/api/users?email=${q}`),
        api.get<any>(`/api/users?firstName=${q}`),
        api.get<any>(`/api/users?lastName=${q}`),
      ]);
      const extract = (d: any) => d['hydra:member'] || d['member'] || [];
      const seen = new Set<number>();
      const merged: any[] = [];
      for (const u of [...extract(byEmail), ...extract(byFirst), ...extract(byLast)]) {
        if (!seen.has(u.id)) {
          seen.add(u.id);
          merged.push(u);
        }
      }
      // Filter out users already in the org
      userSearchResults.value = merged.filter((u: any) => !existingUserIds.value.has(u.id));
    } catch {
      userSearchResults.value = [];
    } finally {
      searchingUsers.value = false;
    }
  }, 300);
}

async function addMember() {
  if (!selectedUser.value) return;
  addingMember.value = true;
  addError.value = '';
  try {
    await withOrgContext(() =>
      api.post('/api/organization_memberships', {
        user: `/api/users/${selectedUser.value.id}`,
        organization: `/api/organizations/${orgId.value}`,
        role: newMemberRole.value,
      })
    );
    cancelAdd();
    success.value = 'Member added';
    setTimeout(() => { success.value = ''; }, 2000);
    await loadData();
  } catch (e: any) {
    addError.value = e.message;
  } finally {
    addingMember.value = false;
  }
}

function cancelAdd() {
  showAddForm.value = false;
  userSearch.value = '';
  userSearchResults.value = [];
  selectedUser.value = null;
  newMemberRole.value = 'ROLE_MANAGER';
  addError.value = '';
}

async function removeMember(m: any) {
  if (!confirm(`Remove ${m.userName} from this organization?`)) return;
  error.value = '';
  try {
    await api.delete(`/api/organization_memberships/${m.id}`);
    success.value = 'Member removed';
    setTimeout(() => { success.value = ''; }, 2000);
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(loadData);
</script>

<style scoped>
.filters {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.search-input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9rem;
}
.building-filter {
  padding: 0.5rem 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9rem;
  min-width: 180px;
}
.members-table {
  width: 100%;
  border-collapse: collapse;
}
.members-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  border-bottom: 2px solid #eee;
  font-size: 0.85rem;
  color: #666;
}
.members-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #eee;
  vertical-align: middle;
}
.members-table tr:last-child td {
  border-bottom: none;
}
.member-link {
  color: #1a73e8;
  text-decoration: none;
}
.member-link:hover {
  text-decoration: underline;
}
.email {
  font-size: 0.8rem;
  color: #888;
}
.building-label {
  display: block;
  font-size: 0.8rem;
  color: #888;
}
.role-select {
  padding: 0.3rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.85rem;
}
.role-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 500;
}
.role-badge.resident {
  background: #e8f5e9;
  color: #2e7d32;
}
.search-results {
  margin-top: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  max-height: 200px;
  overflow-y: auto;
}
.search-result-item {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #eee;
}
.search-result-item:last-child {
  border-bottom: none;
}
.search-result-item:hover {
  background: #f5f5f5;
}
.search-result-item.selected {
  background: #e3f2fd;
}
.success {
  color: #2e7d32;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}
</style>
