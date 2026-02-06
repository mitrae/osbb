<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">Members</h1>

    <!-- Pending requests -->
    <div v-if="pending.length > 0" class="card">
      <h2>Pending Join Requests</h2>
      <div v-for="m in pending" :key="m.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <strong>{{ m.userName }}</strong>
            <span style="margin-left:0.5rem;font-size:0.85rem;color:#666">{{ m.userEmail }}</span>
          </div>
          <div style="display:flex;gap:0.5rem">
            <button class="btn btn-primary" @click="updateMembership(m.id, 'approved')">Approve</button>
            <button class="btn btn-danger" @click="updateMembership(m.id, 'rejected')">Reject</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Approved members -->
    <div class="card">
      <h2>Members</h2>
      <div v-if="loading">Loading...</div>
      <div v-for="m in approved" :key="m.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <strong>{{ m.userName }}</strong>
            <span style="margin-left:0.5rem;font-size:0.85rem;color:#666">{{ m.userEmail }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:0.5rem">
            <select
              :value="m.role"
              @change="updateRole(m.id, ($event.target as HTMLSelectElement).value)"
              style="padding:0.3rem;border:1px solid #ddd;border-radius:4px;font-size:0.85rem"
            >
              <option value="ROLE_RESIDENT">Resident</option>
              <option value="ROLE_MANAGER">Manager</option>
              <option value="ROLE_ADMIN">Admin</option>
            </select>
          </div>
        </div>
      </div>
      <p v-if="!loading && approved.length === 0">No approved members yet.</p>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();
const memberships = ref<any[]>([]);
const loading = ref(true);
const error = ref('');

const pending = computed(() => memberships.value.filter((m) => m.status === 'pending'));
const approved = computed(() => memberships.value.filter((m) => m.status === 'approved'));

async function loadMembers() {
  loading.value = true;
  try {
    // Set org context for this request
    const savedOrg = orgStore.currentOrgId;
    orgStore.setCurrentOrg(Number(route.params.id));
    const data = await api.get<any>('/api/organization_memberships');
    const members = data['hydra:member'] || data.member || [];
    memberships.value = members.map((m: any) => ({
      id: m.id,
      status: m.status,
      role: m.role,
      userName: typeof m.user === 'string' ? m.user : `${m.user?.firstName || ''} ${m.user?.lastName || ''}`.trim() || m.user?.email,
      userEmail: typeof m.user === 'string' ? '' : m.user?.email || '',
    }));
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function updateMembership(id: number, status: string) {
  error.value = '';
  try {
    await api.patch(`/api/organization_memberships/${id}`, { status });
    await loadMembers();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function updateRole(id: number, role: string) {
  error.value = '';
  try {
    await api.patch(`/api/organization_memberships/${id}`, { role });
    await loadMembers();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(loadMembers);
</script>

<style scoped>
.list-item {
  padding: 0.7rem 0;
  border-bottom: 1px solid #eee;
}
.list-item:last-child {
  border-bottom: none;
}
</style>
