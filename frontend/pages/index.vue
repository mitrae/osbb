<template>
  <div>
    <h1>Dashboard</h1>

    <div v-if="!auth.isPlatformAdmin && orgs.length === 0" class="card" style="text-align:center;padding:2rem">
      <h2>Welcome!</h2>
      <p style="margin:1rem 0;color:#666">You haven't joined any organization yet.</p>
      <NuxtLink to="/organizations" class="btn btn-primary">Apply for Residency</NuxtLink>
    </div>

    <template v-else>
      <!-- Org selector -->
      <div v-if="orgs.length > 0" class="card" style="padding:0.7rem 1rem">
        <div class="form-group" style="margin:0">
          <label style="font-size:0.85rem">Organization</label>
          <select v-model="selectedOrgId" @change="onOrgChange">
            <option v-for="o in orgs" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="card">Loading...</div>

      <template v-else>
        <div class="dashboard-grid">
          <div class="card">
            <h2>Requests</h2>
            <p class="stat">{{ requestCount }}</p>
            <NuxtLink to="/requests" class="btn btn-primary">View Requests</NuxtLink>
          </div>

          <div class="card">
            <h2>Active Surveys</h2>
            <p class="stat">{{ surveyCount }}</p>
            <NuxtLink to="/surveys" class="btn btn-primary">View Surveys</NuxtLink>
          </div>
        </div>

        <div v-if="recentRequests.length" class="card" style="margin-top: 1.5rem">
          <h2>Recent Requests</h2>
          <div v-for="req in recentRequests" :key="req.id" class="list-item">
            <NuxtLink :to="`/requests/${req.id}`">
              <strong>{{ req.title }}</strong>
              <span :class="`badge badge-${req.status}`">{{ req.status }}</span>
            </NuxtLink>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const auth = useAuthStore();
const org = useOrganizationStore();

const orgs = computed(() => org.allOrgs);
const selectedOrgId = ref<number | null>(null);
const loading = ref(true);
const requestCount = ref(0);
const surveyCount = ref(0);
const recentRequests = ref<any[]>([]);

function onOrgChange() {
  org.setCurrentOrg(selectedOrgId.value);
  loadDashboard();
}

async function loadDashboard() {
  if (!selectedOrgId.value) {
    loading.value = false;
    return;
  }
  loading.value = true;

  const savedOrg = org.currentOrgId;
  org.setCurrentOrg(selectedOrgId.value);

  try {
    const [reqData, surveyData] = await Promise.all([
      api.get<any>('/api/requests'),
      api.get<any>('/api/surveys'),
    ]);

    const requests = reqData['hydra:member'] || reqData.member || [];
    const surveys = surveyData['hydra:member'] || surveyData.member || [];

    requestCount.value = requests.length;
    surveyCount.value = surveys.filter((s: any) => s.isActive).length;
    recentRequests.value = requests.slice(0, 5);
  } catch {
    requestCount.value = 0;
    surveyCount.value = 0;
    recentRequests.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (orgs.value.length > 0) {
    selectedOrgId.value = org.currentOrgId && orgs.value.some(o => o.id === org.currentOrgId)
      ? org.currentOrgId
      : orgs.value[0].id;
    org.setCurrentOrg(selectedOrgId.value);
  }
  loadDashboard();
});
</script>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}
.stat {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1a73e8;
  margin: 0.5rem 0 1rem;
}
.list-item {
  padding: 0.7rem 0;
  border-bottom: 1px solid #eee;
}
.list-item:last-child {
  border-bottom: none;
}
.list-item a {
  text-decoration: none;
  color: inherit;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
