<template>
  <div>
    <h1>Dashboard</h1>

    <div class="dashboard-grid">
      <div class="card">
        <h2>My Requests</h2>
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
  </div>
</template>

<script setup lang="ts">
const api = useApi();

const requestCount = ref(0);
const surveyCount = ref(0);
const recentRequests = ref<any[]>([]);

onMounted(async () => {
  try {
    const [reqData, surveyData] = await Promise.all([
      api.get<any>('/api/requests'),
      api.get<any>('/api/surveys'),
    ]);

    const requests = reqData['hydra:member'] || reqData.member || [];
    const surveys = surveyData['hydra:member'] || surveyData.member || [];

    requestCount.value = requests.length;
    surveyCount.value = surveys.length;
    recentRequests.value = requests.slice(0, 5);
  } catch {
    // API might fail if no data yet
  }
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
