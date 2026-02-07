<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">{{ orgName ? `${orgName} — Requests` : 'Requests' }}</h1>

    <div class="card" v-if="!loading">
      <div class="status-tabs">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          :class="['tab', { active: selectedStatus === tab.value }]"
          @click="selectedStatus = tab.value"
        >
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
        </button>
      </div>

      <div v-if="filtered.length > 0">
        <div v-for="req in filtered" :key="req.id" class="request-item">
          <NuxtLink :to="`/requests/${req.id}`" style="text-decoration:none;color:inherit">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:0.5rem">
                <strong>{{ req.title }}</strong>
                <span v-if="req.visibility === 'public'" class="visibility-tag">public</span>
              </div>
              <span :class="`badge badge-${req.status}`">{{ formatStatus(req.status) }}</span>
            </div>
            <p class="description">
              {{ req.description?.substring(0, 150) }}{{ req.description?.length > 150 ? '...' : '' }}
            </p>
            <div class="meta">
              <span>{{ req.authorName }}</span>
              <span>&middot;</span>
              <span>{{ new Date(req.createdAt).toLocaleDateString() }}</span>
              <template v-if="req.assigneeName">
                <span>&middot;</span>
                <span>Assigned: {{ req.assigneeName }}</span>
              </template>
            </div>
          </NuxtLink>
        </div>
      </div>
      <p v-else style="color:#666;margin:1rem 0 0">No requests match this filter.</p>
    </div>
    <div class="card" v-else>Loading...</div>

    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();

const allRequests = ref<any[]>([]);
const orgName = ref('');
const loading = ref(true);
const error = ref('');
const selectedStatus = ref('active');

const statusTabs = computed(() => {
  const counts: Record<string, number> = {};
  for (const r of allRequests.value) {
    counts[r.status] = (counts[r.status] || 0) + 1;
  }
  const active = (counts['open'] || 0) + (counts['in_progress'] || 0);
  return [
    { label: 'Active', value: 'active', count: active },
    { label: 'Open', value: 'open', count: counts['open'] || 0 },
    { label: 'In Progress', value: 'in_progress', count: counts['in_progress'] || 0 },
    { label: 'Resolved', value: 'resolved', count: counts['resolved'] || 0 },
    { label: 'Closed', value: 'closed', count: counts['closed'] || 0 },
    { label: 'All', value: 'all', count: allRequests.value.length },
  ];
});

const filtered = computed(() => {
  if (selectedStatus.value === 'all') return allRequests.value;
  if (selectedStatus.value === 'active') {
    return allRequests.value.filter((r) => r.status === 'open' || r.status === 'in_progress');
  }
  return allRequests.value.filter((r) => r.status === selectedStatus.value);
});

function formatStatus(status: string): string {
  return status.replace('_', ' ');
}

function userName(user: any): string {
  if (!user || typeof user !== 'object') return '';
  return `${user.firstName || ''} ${user.lastName || ''}`.trim() || user.email || '';
}

async function loadData() {
  loading.value = true;
  const orgId = Number(route.params.id);
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId);

  try {
    const [orgData, reqData] = await Promise.all([
      api.get<any>(`/api/organizations/${orgId}`),
      api.get<any>('/api/requests'),
    ]);

    orgName.value = orgData.name;

    const items = reqData['hydra:member'] || reqData['member'] || [];
    allRequests.value = items.map((r: any) => ({
      id: r.id,
      title: r.title,
      description: r.description,
      status: r.status,
      visibility: r.visibility,
      createdAt: r.createdAt,
      authorName: userName(r.author),
      assigneeName: userName(r.assignee),
    }));
  } catch {
    error.value = 'Failed to load requests';
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    loading.value = false;
  }
}

onMounted(loadData);
</script>

<style scoped>
.status-tabs {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
  border-bottom: 2px solid #eee;
  padding-bottom: 0.5rem;
}
.tab {
  padding: 0.4rem 0.75rem;
  border: none;
  background: none;
  font-size: 0.85rem;
  color: #666;
  cursor: pointer;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.tab:hover {
  background: #f0f0f0;
}
.tab.active {
  background: #e8f0fe;
  color: #1a73e8;
  font-weight: 500;
}
.tab-count {
  font-size: 0.75rem;
  background: #eee;
  padding: 0.1rem 0.4rem;
  border-radius: 8px;
  min-width: 1.2rem;
  text-align: center;
}
.tab.active .tab-count {
  background: #c6dafb;
}
.request-item {
  padding: 0.75rem 0;
  border-bottom: 1px solid #eee;
}
.request-item:last-child {
  border-bottom: none;
}
.description {
  margin: 0.4rem 0 0;
  font-size: 0.9rem;
  color: #666;
}
.meta {
  margin-top: 0.3rem;
  font-size: 0.8rem;
  color: #999;
  display: flex;
  gap: 0.4rem;
}
.visibility-tag {
  font-size: 0.75rem;
  color: #666;
  border: 1px solid #ddd;
  padding: 0.1rem 0.4rem;
  border-radius: 8px;
}
</style>
