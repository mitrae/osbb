<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}/members`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Members</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="member">
      <div class="card" style="margin-top:1rem">
        <h1>{{ member.firstName }} {{ member.lastName }}</h1>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Email</span>
            <span>{{ member.email }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Phone</span>
            <span>{{ member.phone || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Member since</span>
            <span>{{ new Date(member.createdAt).toLocaleDateString() }}</span>
          </div>
        </div>
      </div>

      <div class="card" v-if="residents.length > 0">
        <h2>Residencies</h2>
        <table class="residencies-table">
          <thead>
            <tr>
              <th>Building</th>
              <th>Apartment</th>
              <th>Owned Area</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in residents" :key="r.id">
              <td>{{ r.buildingAddress }}</td>
              <td>{{ r.aptNumber }}</td>
              <td>{{ r.ownedArea }} m&sup2;</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h2>Recent Requests</h2>
          <NuxtLink
            :to="`/requests?author=${route.params.userId}`"
            style="color:#1a73e8;text-decoration:none;font-size:0.9rem"
          >View all requests &rarr;</NuxtLink>
        </div>

        <div v-if="requests.length === 0" style="color:#666;font-size:0.9rem">No requests found.</div>

        <div v-for="req in requests" :key="req.id" style="padding:0.7rem 0;border-bottom:1px solid #eee">
          <NuxtLink :to="`/requests/${req.id}`" style="text-decoration:none;color:inherit">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <strong>{{ req.title }}</strong>
              <span :class="`badge badge-${req.status}`">{{ req.status }}</span>
            </div>
            <small style="color:#999">{{ new Date(req.createdAt).toLocaleDateString() }}</small>
          </NuxtLink>
        </div>
      </div>
    </template>

    <div v-else class="card" style="margin-top:1rem">
      <p>Member not found.</p>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();

const member = ref<any>(null);
const residents = ref<any[]>([]);
const requests = ref<any[]>([]);
const loading = ref(true);
const error = ref('');

async function loadData() {
  loading.value = true;
  const orgId = Number(route.params.id);
  const userId = route.params.userId;
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId);

  try {
    // Load user info
    const userData = await api.get<any>(`/api/users/${userId}`);
    member.value = userData;

    // Load residencies and recent requests in parallel
    const [resData, reqData] = await Promise.all([
      api.get<any>(`/api/residents?user=/api/users/${userId}`),
      api.get<any>(`/api/requests?author=/api/users/${userId}&order[createdAt]=desc&itemsPerPage=10`),
    ]);

    const resItems = resData['hydra:member'] || resData['member'] || [];
    residents.value = resItems.map((r: any) => {
      const apt = r.apartment || {};
      const bld = apt.building || {};
      return {
        id: r.id,
        buildingAddress: bld.address || '—',
        aptNumber: apt.number || '—',
        ownedArea: r.ownedArea,
      };
    });

    const reqItems = reqData['hydra:member'] || reqData['member'] || [];
    requests.value = reqItems;
  } catch (e: any) {
    error.value = 'Failed to load member details';
  } finally {
    orgStore.setCurrentOrg(savedOrg);
    loading.value = false;
  }
}

onMounted(loadData);
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
.residencies-table {
  width: 100%;
  border-collapse: collapse;
}
.residencies-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  border-bottom: 2px solid #eee;
  font-size: 0.85rem;
  color: #666;
}
.residencies-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #eee;
}
.residencies-table tr:last-child td {
  border-bottom: none;
}
</style>
