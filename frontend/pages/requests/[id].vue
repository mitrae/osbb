<template>
  <div>
    <NuxtLink to="/requests" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Requests</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <div v-else-if="request" class="card" style="margin-top:1rem">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h1>{{ request.title }}</h1>
        <span :class="`badge badge-${request.status}`">{{ request.status }}</span>
      </div>

      <p style="margin:1rem 0;line-height:1.6">{{ request.description }}</p>

      <div style="font-size:0.9rem;color:#666;margin-top:1rem">
        <p>Author: {{ request.author?.firstName }} {{ request.author?.lastName }}</p>
        <p v-if="request.assignee">Assignee: {{ request.assignee?.firstName }} {{ request.assignee?.lastName }}</p>
        <p>Created: {{ new Date(request.createdAt).toLocaleString() }}</p>
        <p>Updated: {{ new Date(request.updatedAt).toLocaleString() }}</p>
      </div>

      <div v-if="auth.isManager" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #eee">
        <h2>Update Status</h2>
        <div style="display:flex;gap:0.5rem;margin-top:0.5rem">
          <button
            v-for="status in statuses"
            :key="status"
            class="btn"
            :class="request.status === status ? 'btn-primary' : ''"
            :style="request.status !== status ? 'background:#e0e0e0' : ''"
            @click="updateStatus(status)"
          >
            {{ status }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const request = ref<any>(null);
const loading = ref(true);
const statuses = ['new', 'in_progress', 'resolved', 'rejected'];

async function loadRequest() {
  loading.value = true;
  try {
    request.value = await api.get(`/api/requests/${route.params.id}`);
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function updateStatus(status: string) {
  try {
    request.value = await api.patch(`/api/requests/${route.params.id}`, { status });
  } catch {
    // handle error
  }
}

onMounted(loadRequest);
</script>
