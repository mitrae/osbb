<template>
  <div>
    <NuxtLink to="/organizations" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organizations</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="organization">
      <div class="card" style="margin-top:1rem">
        <h1>{{ organization.name }}</h1>
        <p style="color:#666">{{ organization.address }}</p>
        <small style="color:#999">Created {{ new Date(organization.createdAt).toLocaleDateString() }}</small>

        <div v-if="membership" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee">
          <span :class="`badge badge-${membership.status}`">{{ membership.status }}</span>
          <span style="margin-left:0.5rem;font-size:0.85rem;color:#666">{{ membership.role.replace('ROLE_', '') }}</span>
        </div>
      </div>

      <!-- Admin links -->
      <div v-if="isOrgAdmin" class="card">
        <h2>Management</h2>
        <div style="display:flex;gap:1rem;margin-top:0.5rem">
          <NuxtLink :to="`/organizations/${route.params.id}/members`" class="btn btn-primary">Manage Members</NuxtLink>
          <NuxtLink :to="`/organizations/${route.params.id}/apartments`" class="btn btn-primary">Manage Apartments</NuxtLink>
        </div>
      </div>

      <!-- Buildings -->
      <div class="card">
        <h2>Buildings</h2>
        <div v-if="buildings.length === 0" style="color:#666">No buildings registered yet.</div>
        <div v-for="b in buildings" :key="b.id" class="list-item">
          <strong>{{ b.address }}</strong>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();
const organization = ref<any>(null);
const buildings = ref<any[]>([]);
const loading = ref(true);

const membership = computed(() =>
  orgStore.memberships.find((m) => m.organization.id === Number(route.params.id))
);

const isOrgAdmin = computed(() => {
  const auth = useAuthStore();
  if (auth.user?.type === 'admin') return true;
  return membership.value?.role === 'ROLE_ADMIN' && membership.value?.status === 'approved';
});

onMounted(async () => {
  try {
    organization.value = await api.get(`/api/organizations/${route.params.id}`);

    // Load buildings with org context
    const savedOrg = orgStore.currentOrgId;
    orgStore.setCurrentOrg(Number(route.params.id));
    const bData = await api.get<any>('/api/buildings');
    buildings.value = bData['hydra:member'] || bData.member || [];
    // Restore previous org context
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
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
