<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">{{ orgName ? `${orgName} — Members` : 'Members' }}</h1>

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
          </tr>
        </tbody>
      </table>
      <p v-else>No members match your search.</p>
    </div>
    <div class="card" v-else>Loading...</div>

    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();

const allMembers = ref<any[]>([]);
const buildings = ref<any[]>([]);
const orgName = ref('');
const loading = ref(true);
const error = ref('');
const search = ref('');
const selectedBuildingId = ref<number | null>(null);

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

async function loadData() {
  loading.value = true;
  const orgId = Number(route.params.id);
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId);

  try {
    // Load core data (org info + memberships)
    const [orgData, membData] = await Promise.all([
      api.get<any>(`/api/organizations/${orgId}`),
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
</style>
