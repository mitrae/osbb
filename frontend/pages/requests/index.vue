<template>
  <div>
    <div class="page-header">
      <h1>Requests</h1>
      <button class="btn btn-primary" @click="showForm = !showForm" v-if="selectedOrgId && canCreate">
        {{ showForm ? 'Cancel' : '+ New Request' }}
      </button>
    </div>

    <!-- Filters -->
    <div class="card" style="padding:0.7rem 1rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:end">
      <div class="form-group" style="margin:0;min-width:180px">
        <label style="font-size:0.85rem">Organization</label>
        <select v-model="selectedOrgId" @change="onFilterChange">
          <option v-if="auth.isPlatformAdmin" :value="null">All Organizations</option>
          <option v-for="o in orgs" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:140px">
        <label style="font-size:0.85rem">Status</label>
        <select v-model="statusFilter" @change="onFilterChange">
          <option value="">All</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:200px">
        <label style="font-size:0.85rem">Search</label>
        <input v-model="searchQuery" type="text" placeholder="Title, name, email, phone, apt #..." @input="onSearchInput" />
      </div>
    </div>

    <div v-if="authorFilter" class="card" style="padding:0.7rem 1rem;display:flex;align-items:center;justify-content:space-between;background:#e3f2fd">
      <span>Showing requests by <strong>{{ authorName }}</strong></span>
      <button class="btn" style="background:#fff;font-size:0.85rem" @click="clearAuthorFilter">Clear filter</button>
    </div>

    <div v-if="showForm && selectedOrgId" class="card">
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
        <small style="color:#999">
          <span v-if="!selectedOrgId && req.organization?.name" style="font-weight:500;color:#555">{{ req.organization.name }} &middot; </span>
          <template v-if="req.author">
            <NuxtLink
              v-if="canLinkAuthor && req.author.id && selectedOrgId"
              :to="`/organizations/${selectedOrgId}/members/${req.author.id}`"
              style="color:#1a73e8;text-decoration:none"
              @click.stop
            >{{ req.author.firstName }} {{ req.author.lastName }}</NuxtLink>
            <span v-else>{{ req.author.firstName }} {{ req.author.lastName }}</span>
            &middot;
          </template>
          {{ new Date(req.createdAt).toLocaleDateString() }}
        </small>
      </NuxtLink>
    </div>

    <p v-if="!loading && !auth.isPlatformAdmin && !selectedOrgId" class="card">Select an organization to view requests.</p>
    <p v-else-if="!loading && requests.length === 0" class="card">No requests found.</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const router = useRouter();
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();
const requests = ref<any[]>([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');
const statusFilter = ref('');
const searchQuery = ref('');
const authorFilter = ref('');
const authorName = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const form = reactive({ title: '', description: '', visibility: 'private' });

const orgs = computed(() => orgStore.allOrgs);
const selectedOrgId = ref<number | null>(null);

const canLinkAuthor = computed(() => {
  return auth.isPlatformAdmin || orgStore.isOrgManager;
});

const canCreate = computed(() => {
  if (auth.isPlatformAdmin) return true;
  if (!selectedOrgId.value) return false;
  const membership = orgStore.memberships.find(m => m.organization.id === selectedOrgId.value);
  if (membership) return true;
  // Residents can also create requests
  return orgStore.residentOrgs.some(r => r.orgId === selectedOrgId.value);
});

function clearAuthorFilter() {
  authorFilter.value = '';
  authorName.value = '';
  router.replace({ query: {} });
  loadRequests();
}

function onFilterChange() {
  showForm.value = false;
  loadRequests();
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => loadRequests(), 300);
}

function withOrgContext<T>(fn: () => Promise<T>, orgId?: number | null): Promise<T> {
  const savedOrg = orgStore.currentOrgId;
  const target = orgId !== undefined ? orgId : selectedOrgId.value;
  orgStore.setCurrentOrg(target);
  return fn().finally(() => {
    orgStore.setCurrentOrg(savedOrg);
  });
}

async function loadRequests() {
  loading.value = true;
  try {
    let url = '/api/requests';
    const params: string[] = [];
    if (authorFilter.value) {
      params.push(`author=/api/users/${authorFilter.value}`);
    }
    if (statusFilter.value) {
      params.push(`status=${statusFilter.value}`);
    }
    if (searchQuery.value.trim()) {
      params.push(`search=${encodeURIComponent(searchQuery.value.trim())}`);
    }
    if (params.length) {
      url += '?' + params.join('&');
    }
    const data = await withOrgContext(() => api.get<any>(url));
    requests.value = data['hydra:member'] || data.member || [];
  } catch {
    requests.value = [];
  } finally {
    loading.value = false;
  }
}

async function createRequest() {
  error.value = '';
  submitting.value = true;
  try {
    await withOrgContext(() => api.post('/api/requests', form));
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

onMounted(async () => {
  if (auth.isPlatformAdmin) {
    selectedOrgId.value = null;
  } else if (orgs.value.length > 0) {
    selectedOrgId.value = orgStore.currentOrgId && orgs.value.some(o => o.id === orgStore.currentOrgId)
      ? orgStore.currentOrgId
      : orgs.value[0].id;
  }

  // Handle ?author=<userId> query param
  const qAuthor = route.query.author;
  if (qAuthor) {
    authorFilter.value = String(qAuthor);
    try {
      const userData = await api.get<any>(`/api/users/${qAuthor}`);
      authorName.value = `${userData.firstName} ${userData.lastName}`.trim();
    } catch {
      authorName.value = `User #${qAuthor}`;
    }
  }

  loadRequests();
});
</script>
