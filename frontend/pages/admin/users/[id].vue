<template>
  <div>
    <NuxtLink to="/admin/users" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Users</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="user">
      <!-- User info -->
      <div class="card" style="margin-top:1rem">
        <h1 style="margin:0">{{ user.firstName }} {{ user.lastName }}</h1>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Email</span>
            <span>{{ user.email }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Phone</span>
            <span>{{ user.phone || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Roles</span>
            <span>{{ (user.roles || []).map((r: string) => r.replace('ROLE_', '')).join(', ') }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Created</span>
            <span>{{ user.createdAt ? new Date(user.createdAt).toLocaleDateString() : '—' }}</span>
          </div>
        </div>

        <!-- Set password -->
        <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #eee">
          <h3 style="margin:0 0 0.5rem">Set Password</h3>
          <div style="display:flex;gap:0.5rem;align-items:center">
            <input
              v-model="newPassword"
              type="password"
              placeholder="New password"
              class="password-input"
            />
            <button
              class="btn btn-primary"
              style="font-size:0.85rem"
              @click="setPassword"
              :disabled="!newPassword"
            >Set</button>
          </div>
        </div>
      </div>

      <!-- Organization Memberships -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h2 style="margin:0">Organization Memberships</h2>
          <button v-if="!showAssignForm" class="btn btn-primary" style="font-size:0.85rem" @click="showAssignForm = true">+ Assign to Org</button>
        </div>

        <!-- Assign form -->
        <div v-if="showAssignForm" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee">
          <div style="display:flex;gap:0.5rem;align-items:end;flex-wrap:wrap">
            <div class="form-group" style="margin:0;flex:1;min-width:200px">
              <label style="font-size:0.85rem">Organization</label>
              <select v-model="assignOrgId" class="select-input">
                <option :value="null" disabled>Select organization...</option>
                <option v-for="o in availableOrgs" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div class="form-group" style="margin:0;min-width:120px">
              <label style="font-size:0.85rem">Role</label>
              <select v-model="assignRole" class="select-input">
                <option value="ROLE_MANAGER">Manager</option>
                <option value="ROLE_ADMIN">Admin</option>
              </select>
            </div>
            <button
              class="btn btn-primary"
              style="font-size:0.85rem"
              @click="assignToOrg"
              :disabled="!assignOrgId || assigning"
            >{{ assigning ? 'Assigning...' : 'Assign' }}</button>
            <button class="btn" style="background:#e0e0e0;font-size:0.85rem" @click="cancelAssign">Cancel</button>
          </div>
        </div>

        <!-- Memberships table -->
        <div v-if="user.memberships && user.memberships.length > 0" style="margin-top:1rem">
          <table class="detail-table">
            <thead>
              <tr>
                <th>Organization</th>
                <th>Role</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in user.memberships" :key="m.id">
                <td>
                  <NuxtLink :to="`/organizations/${m.organization.id}`" class="link">{{ m.organization.name }}</NuxtLink>
                </td>
                <td>{{ m.role.replace('ROLE_', '') }}</td>
                <td style="text-align:right">
                  <button
                    class="btn btn-danger"
                    style="font-size:0.75rem;padding:0.2rem 0.5rem"
                    @click="unassignFromOrg(m)"
                  >Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else style="color:#666;margin-top:1rem">No organization memberships.</p>
      </div>

      <!-- Residencies -->
      <div class="card">
        <h2>Residencies</h2>
        <div v-if="residents.length > 0">
          <table class="detail-table">
            <thead>
              <tr>
                <th>Organization</th>
                <th>Building</th>
                <th>Apartment</th>
                <th>Owned Area</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in residents" :key="r.id">
                <td>
                  <NuxtLink v-if="r.orgId" :to="`/organizations/${r.orgId}`" class="link">{{ r.orgName }}</NuxtLink>
                  <span v-else>—</span>
                </td>
                <td>{{ r.buildingAddress }}</td>
                <td>{{ r.aptNumber }}</td>
                <td>{{ r.ownedArea }} m&sup2;</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else style="color:#666">No residencies.</p>
      </div>
    </template>

    <div v-else class="card" style="margin-top:1rem">
      <p>User not found.</p>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="success" class="success">{{ success }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const auth = useAuthStore();
const api = useApi();

const user = ref<any>(null);
const residents = ref<any[]>([]);
const allOrgs = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const success = ref('');

// Password
const newPassword = ref('');

// Assign form
const showAssignForm = ref(false);
const assignOrgId = ref<number | null>(null);
const assignRole = ref('ROLE_MANAGER');
const assigning = ref(false);

const availableOrgs = computed(() => {
  const memberOrgIds = new Set(
    (user.value?.memberships || []).map((m: any) => m.organization.id)
  );
  return allOrgs.value.filter((o) => !memberOrgIds.has(o.id));
});

onMounted(async () => {
  if (!auth.isPlatformAdmin) {
    navigateTo('/');
    return;
  }
  await loadData();
});

async function loadData() {
  loading.value = true;
  const userId = route.params.id;

  try {
    const [userData, resData, orgData] = await Promise.all([
      api.get<any>(`/api/users/${userId}`),
      api.get<any>(`/api/residents?user=/api/users/${userId}`),
      api.get<any>('/api/organizations'),
    ]);

    user.value = userData;

    const resList = resData['hydra:member'] || resData['member'] || [];
    residents.value = resList.map((r: any) => {
      const apt = r.apartment || {};
      const bld = apt.building || {};
      const org = bld.organization || {};
      return {
        id: r.id,
        orgId: org.id || null,
        orgName: org.name || '—',
        buildingAddress: bld.address || '—',
        aptNumber: apt.number || '—',
        ownedArea: r.ownedArea,
      };
    });

    const orgList = orgData['hydra:member'] || orgData['member'] || [];
    allOrgs.value = orgList.map((o: any) => ({ id: o.id, name: o.name }));
  } catch (e: any) {
    error.value = e.message || 'Failed to load user';
  } finally {
    loading.value = false;
  }
}

async function setPassword() {
  if (!newPassword.value) return;
  error.value = '';
  success.value = '';
  try {
    await api.patch(`/api/users/${route.params.id}`, { plainPassword: newPassword.value });
    newPassword.value = '';
    success.value = 'Password updated';
    setTimeout(() => { success.value = ''; }, 2000);
  } catch (e: any) {
    error.value = e.message;
  }
}

async function assignToOrg() {
  if (!assignOrgId.value) return;
  assigning.value = true;
  error.value = '';
  success.value = '';
  try {
    await api.post('/api/organization_memberships', {
      user: `/api/users/${route.params.id}`,
      organization: `/api/organizations/${assignOrgId.value}`,
      role: assignRole.value,
    });
    cancelAssign();
    success.value = 'Assigned to organization';
    setTimeout(() => { success.value = ''; }, 2000);
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    assigning.value = false;
  }
}

function cancelAssign() {
  showAssignForm.value = false;
  assignOrgId.value = null;
  assignRole.value = 'ROLE_MANAGER';
}

async function unassignFromOrg(m: any) {
  if (!confirm(`Remove ${user.value.firstName} ${user.value.lastName} from ${m.organization.name}?`)) return;
  error.value = '';
  success.value = '';
  try {
    await api.delete(`/api/organization_memberships/${m.id}`);
    success.value = 'Removed from organization';
    setTimeout(() => { success.value = ''; }, 2000);
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}
</script>

<style scoped>
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.info-label {
  font-size: 0.8rem;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.detail-table {
  width: 100%;
  border-collapse: collapse;
}
.detail-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  border-bottom: 2px solid #eee;
  font-size: 0.85rem;
  color: #666;
}
.detail-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #eee;
}
.detail-table tr:last-child td {
  border-bottom: none;
}
.link {
  color: #1a73e8;
  text-decoration: none;
}
.link:hover {
  text-decoration: underline;
}
.password-input {
  padding: 0.4rem 0.6rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9rem;
  width: 200px;
}
.select-input {
  width: 100%;
  padding: 0.4rem 0.6rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9rem;
}
.success {
  color: #2e7d32;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}
</style>
