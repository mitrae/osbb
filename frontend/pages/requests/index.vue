<template>
  <div>
    <div class="page-header">
      <h1>Requests</h1>
      <button class="btn btn-primary" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Request' }}
      </button>
    </div>

    <div v-if="showForm" class="card">
      <h2>Create Request</h2>
      <form @submit.prevent="createRequest">
        <div class="form-group">
          <label>Title</label>
          <input v-model="form.title" type="text" required />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="form.description" required></textarea>
        </div>
        <div class="form-group">
          <label>Visibility</label>
          <select v-model="form.visibility">
            <option value="private">Private (only you and admins)</option>
            <option value="public">Public (visible to all members)</option>
          </select>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          {{ submitting ? 'Creating...' : 'Submit Request' }}
        </button>
      </form>
    </div>

    <div v-if="loading" class="card">Loading...</div>

    <div v-for="req in requests" :key="req.id" class="card">
      <NuxtLink :to="`/requests/${req.id}`" style="text-decoration:none;color:inherit">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div style="display:flex;align-items:center;gap:0.5rem">
            <strong>{{ req.title }}</strong>
            <span v-if="req.visibility === 'public'" style="font-size:0.75rem;color:#666;border:1px solid #ddd;padding:0.1rem 0.4rem;border-radius:8px">public</span>
          </div>
          <span :class="`badge badge-${req.status}`">{{ req.status }}</span>
        </div>
        <p style="margin-top:0.5rem;font-size:0.9rem;color:#666">
          {{ req.description?.substring(0, 120) }}{{ req.description?.length > 120 ? '...' : '' }}
        </p>
        <small style="color:#999">{{ new Date(req.createdAt).toLocaleDateString() }}</small>
      </NuxtLink>
    </div>

    <p v-if="!loading && requests.length === 0" class="card">No requests yet.</p>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const requests = ref<any[]>([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');

const form = reactive({ title: '', description: '', visibility: 'private' });

async function loadRequests() {
  loading.value = true;
  try {
    const data = await api.get<any>('/api/requests');
    requests.value = data['hydra:member'] || data.member || [];
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function createRequest() {
  error.value = '';
  submitting.value = true;
  try {
    await api.post('/api/requests', form);
    form.title = '';
    form.description = '';
    form.visibility = 'private';
    showForm.value = false;
    await loadRequests();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    submitting.value = false;
  }
}

onMounted(loadRequests);
</script>
