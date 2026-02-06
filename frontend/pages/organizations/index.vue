<template>
  <div>
    <h1>Organizations</h1>

    <!-- My memberships -->
    <div v-if="org.memberships.length > 0" class="card">
      <h2>My Memberships</h2>
      <div v-for="m in org.memberships" :key="m.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <NuxtLink :to="`/organizations/${m.organization.id}`" style="text-decoration:none;color:inherit">
              <strong>{{ m.organization.name || `Org #${m.organization.id}` }}</strong>
            </NuxtLink>
            <span style="margin-left:0.5rem;font-size:0.85rem;color:#666">{{ m.role.replace('ROLE_', '') }}</span>
          </div>
          <span :class="`badge badge-${m.status}`">{{ m.status }}</span>
        </div>
      </div>
    </div>

    <!-- Browse all organizations -->
    <div class="card" style="margin-top:1.5rem">
      <h2>All Organizations</h2>
      <div v-if="loading">Loading...</div>
      <div v-for="o in organizations" :key="o.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <NuxtLink :to="`/organizations/${o.id}`" style="text-decoration:none;color:inherit">
              <strong>{{ o.name }}</strong>
            </NuxtLink>
            <span style="display:block;font-size:0.85rem;color:#666">{{ o.address }}</span>
          </div>
          <button
            v-if="!isMember(o.id)"
            class="btn btn-primary"
            :disabled="joining === o.id"
            @click="joinOrg(o)"
          >
            {{ joining === o.id ? 'Joining...' : 'Join' }}
          </button>
          <span v-else-if="getMembership(o.id)?.status === 'pending'" class="badge badge-pending">Pending</span>
          <span v-else class="badge badge-approved">Member</span>
        </div>
      </div>
      <p v-if="!loading && organizations.length === 0">No organizations available.</p>
    </div>

    <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const org = useOrganizationStore();
const organizations = ref<any[]>([]);
const loading = ref(true);
const joining = ref<number | null>(null);
const error = ref('');

function isMember(orgId: number) {
  return org.memberships.some((m) => m.organization.id === orgId);
}

function getMembership(orgId: number) {
  return org.memberships.find((m) => m.organization.id === orgId);
}

async function joinOrg(o: any) {
  joining.value = o.id;
  error.value = '';
  try {
    await api.post('/api/organization_memberships', {
      organization: `/api/organizations/${o.id}`,
    });
    await org.loadMemberships();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    joining.value = null;
  }
}

onMounted(async () => {
  try {
    const data = await api.get<any>('/api/organizations');
    organizations.value = data['hydra:member'] || data.member || [];
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
});
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
