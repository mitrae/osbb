<template>
  <div>
    <NuxtLink to="/organizations" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organizations</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="organization">
      <!-- Org info card -->
      <div class="card" style="margin-top:1rem">
        <template v-if="!editing">
          <div style="display:flex;justify-content:space-between;align-items:start">
            <div>
              <h1 style="margin:0">{{ organization.name }}</h1>
              <p v-if="organization.city" style="color:#666;margin:0.3rem 0 0">{{ organization.city }}</p>
              <p style="color:#666;margin:0.3rem 0 0">{{ organization.address }}</p>
              <small style="color:#999">Created {{ new Date(organization.createdAt).toLocaleDateString() }}</small>
            </div>
            <div v-if="auth.isPlatformAdmin" style="display:flex;gap:0.5rem">
              <NuxtLink :to="`/organizations/${route.params.id}/import`" class="btn" style="background:#ff9800;color:#fff;font-size:0.85rem">Import Registry</NuxtLink>
              <button class="btn" style="background:#e0e0e0;font-size:0.85rem" @click="startEditing">Edit</button>
              <button class="btn btn-danger" style="font-size:0.85rem" @click="deleteOrg">Delete</button>
            </div>
          </div>
        </template>

        <template v-else>
          <h2 style="margin-top:0">Edit Organization</h2>
          <form @submit.prevent="saveOrg">
            <div class="form-group">
              <label>Name</label>
              <input v-model="editForm.name" type="text" required />
            </div>
            <div class="form-group">
              <label>City</label>
              <input v-model="editForm.city" type="text" placeholder="City (optional)" />
            </div>
            <div class="form-group">
              <label>Address</label>
              <input v-model="editForm.address" type="text" required />
            </div>
            <p v-if="editError" class="error">{{ editError }}</p>
            <div style="display:flex;gap:0.5rem">
              <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
              <button type="button" class="btn" style="background:#e0e0e0" @click="editing = false">Cancel</button>
            </div>
          </form>
        </template>

        <div v-if="membership && !editing" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee">
          <span class="badge badge-approved">Member</span>
          <span style="margin-left:0.5rem;font-size:0.85rem;color:#666">{{ membership.role.replace('ROLE_', '') }}</span>
        </div>
      </div>

      <!-- My residencies in this org -->
      <div v-if="myResidents.length > 0" class="card">
        <h2>My Residency</h2>
        <table class="residency-table">
          <thead>
            <tr>
              <th>Building</th>
              <th>Apartment</th>
              <th>Owned Area</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in myResidents" :key="r.id">
              <td>{{ r.buildingAddress }}</td>
              <td>{{ r.aptNumber }}{{ r.aptType !== 'apartment' ? ` (${r.aptType})` : '' }}</td>
              <td>{{ r.ownedArea }} m&sup2;</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Management widgets -->
      <div v-if="isOrgAdmin" style="margin-bottom:1rem">
        <h2 style="margin-bottom:0.5rem">Management</h2>
        <div class="widget-grid">
          <NuxtLink :to="`/organizations/${route.params.id}/requests`" class="widget-card">
            <div class="widget-label">Requests</div>
            <div class="widget-stat">{{ stats.requests }}</div>
          </NuxtLink>
          <NuxtLink :to="`/organizations/${route.params.id}/members`" class="widget-card">
            <div class="widget-label">Members</div>
            <div class="widget-stat">{{ stats.members }}</div>
          </NuxtLink>
          <NuxtLink :to="`/organizations/${route.params.id}/apartments`" class="widget-card">
            <div class="widget-label">Apartments</div>
            <div class="widget-stat">{{ stats.apartments }}</div>
          </NuxtLink>
          <NuxtLink :to="`/organizations/${route.params.id}/connection-requests`" class="widget-card">
            <div class="widget-label">Connection Requests</div>
            <div class="widget-stat">{{ stats.connectionRequests }}</div>
          </NuxtLink>
          <NuxtLink to="/surveys" class="widget-card">
            <div class="widget-label">Surveys</div>
            <div class="widget-stat">{{ stats.surveys }}</div>
          </NuxtLink>
        </div>
      </div>

      <!-- Buildings -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h2 style="margin:0">Buildings</h2>
          <button v-if="isOrgAdmin && !showBuildingForm" class="btn btn-primary" style="font-size:0.85rem" @click="showBuildingForm = true">+ Add Building</button>
        </div>

        <div v-if="showBuildingForm" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee">
          <form @submit.prevent="addBuilding" style="display:flex;gap:0.5rem;align-items:end;flex-wrap:wrap">
            <div class="form-group" style="margin:0;flex:1;min-width:200px">
              <label style="font-size:0.85rem">Address</label>
              <input v-model="newBuildingAddress" type="text" required placeholder="Building address" />
            </div>
            <button type="submit" class="btn btn-primary" style="font-size:0.85rem" :disabled="addingBuilding">{{ addingBuilding ? 'Adding...' : 'Add' }}</button>
            <button type="button" class="btn" style="background:#e0e0e0;font-size:0.85rem" @click="showBuildingForm = false; newBuildingAddress = ''">Cancel</button>
          </form>
        </div>

        <div v-if="buildings.length === 0 && !showBuildingForm" style="color:#666;margin-top:0.5rem">No buildings registered yet.</div>
        <div v-for="b in buildings" :key="b.id" class="list-item" style="display:flex;justify-content:space-between;align-items:center">
          <strong>{{ b.address }}</strong>
          <button
            v-if="isOrgAdmin"
            class="btn btn-danger"
            style="font-size:0.75rem;padding:0.15rem 0.5rem"
            @click="removeBuilding(b)"
          >
            Remove
          </button>
        </div>
      </div>

      <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();
const organization = ref<any>(null);
const buildings = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const myResidents = ref<any[]>([]);
const stats = reactive({ requests: 0, members: 0, apartments: 0, connectionRequests: 0, surveys: 0 });

// Edit org state
const editing = ref(false);
const saving = ref(false);
const editError = ref('');
const editForm = reactive({ name: '', city: '', address: '' });

// Add building state
const showBuildingForm = ref(false);
const newBuildingAddress = ref('');
const addingBuilding = ref(false);

const orgId = computed(() => Number(route.params.id));

const membership = computed(() =>
  orgStore.memberships.find((m) => m.organization.id === orgId.value)
);

const isOrgAdmin = computed(() => {
  if (auth.isPlatformAdmin) return true;
  return membership.value?.role === 'ROLE_ADMIN';
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
  try {
    organization.value = await api.get(`/api/organizations/${orgId.value}`);
    const fetches: Promise<any>[] = [
      withOrgContext(() => api.get<any>('/api/buildings')),
      withOrgContext(() => api.get<any>(`/api/residents?user=/api/users/${auth.user!.id}`)),
    ];
    if (isOrgAdmin.value) {
      fetches.push(
        withOrgContext(() => api.get<any>('/api/requests?itemsPerPage=1')),
        withOrgContext(() => api.get<any>('/api/organization_memberships?itemsPerPage=1')),
        withOrgContext(() => api.get<any>('/api/apartments?itemsPerPage=1')),
        withOrgContext(() => api.get<any>('/api/connection_requests?status=pending&itemsPerPage=1')),
        withOrgContext(() => api.get<any>('/api/surveys?itemsPerPage=1')),
      );
    }
    const results = await Promise.all(fetches);
    const [bData, rData] = results;
    buildings.value = bData['hydra:member'] || bData.member || [];
    const resList = rData['hydra:member'] || rData.member || (Array.isArray(rData) ? rData : []);
    myResidents.value = resList.map((r: any) => {
      const apt = r.apartment || {};
      const bld = apt.building || {};
      return {
        id: r.id,
        buildingAddress: bld.address || '—',
        aptNumber: apt.number || '—',
        aptType: apt.type || 'apartment',
        ownedArea: r.ownedArea,
      };
    });
    if (isOrgAdmin.value) {
      const count = (d: any) => d['hydra:totalItems'] ?? d['totalItems'] ?? (d['hydra:member'] || d.member || []).length;
      stats.requests = count(results[2]);
      stats.members = count(results[3]);
      stats.apartments = count(results[4]);
      stats.connectionRequests = count(results[5]);
      stats.surveys = count(results[6]);
    }
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

function startEditing() {
  editForm.name = organization.value.name;
  editForm.city = organization.value.city || '';
  editForm.address = organization.value.address;
  editError.value = '';
  editing.value = true;
}

async function saveOrg() {
  editError.value = '';
  saving.value = true;
  try {
    organization.value = await withOrgContext(() =>
      api.patch(`/api/organizations/${orgId.value}`, {
        name: editForm.name,
        city: editForm.city || null,
        address: editForm.address,
      })
    );
    editing.value = false;
  } catch (e: any) {
    editError.value = e.message;
  } finally {
    saving.value = false;
  }
}

async function addBuilding() {
  error.value = '';
  addingBuilding.value = true;
  try {
    await withOrgContext(() =>
      api.post('/api/buildings', {
        organization: `/api/organizations/${orgId.value}`,
        address: newBuildingAddress.value,
      })
    );
    newBuildingAddress.value = '';
    showBuildingForm.value = false;
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    addingBuilding.value = false;
  }
}

async function deleteOrg() {
  if (!confirm(`Delete organization "${organization.value.name}"? This will remove all its buildings, apartments, and residents.`)) return;
  error.value = '';
  try {
    await api.delete(`/api/organizations/${orgId.value}`);
    navigateTo('/organizations');
  } catch (e: any) {
    error.value = e.message;
  }
}

async function removeBuilding(b: any) {
  if (!confirm(`Remove building "${b.address}"? This will also remove all its apartments and residents.`)) return;
  error.value = '';
  try {
    await withOrgContext(() => api.delete(`/api/buildings/${b.id}`));
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(loadData);
</script>

<style scoped>
.list-item {
  padding: 0.7rem 0;
  border-bottom: 1px solid #eee;
}
.list-item:last-child {
  border-bottom: none;
}
.residency-table {
  width: 100%;
  border-collapse: collapse;
}
.residency-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  border-bottom: 2px solid #eee;
  font-size: 0.85rem;
  color: #666;
}
.residency-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #eee;
}
.residency-table tr:last-child td {
  border-bottom: none;
}
.widget-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 1rem;
}
.widget-card {
  display: block;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 1rem 1.2rem;
  text-decoration: none;
  color: inherit;
  transition: box-shadow 0.15s, border-color 0.15s;
  text-align: center;
}
.widget-card:hover {
  border-color: #1a73e8;
  box-shadow: 0 2px 8px rgba(26, 115, 232, 0.15);
}
.widget-label {
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.3rem;
}
.widget-stat {
  font-size: 2rem;
  font-weight: 700;
  color: #1a73e8;
}
</style>
