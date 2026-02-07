<template>
  <div>
    <div class="page-header">
      <h1>Surveys</h1>
      <button v-if="selectedOrgId && canCreate" class="btn btn-primary" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Survey' }}
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
        <select v-model="activeFilter" @change="onFilterChange">
          <option value="">All</option>
          <option value="true">Active</option>
          <option value="false">Closed</option>
        </select>
      </div>
    </div>

    <div v-if="showForm && selectedOrgId" class="card">
      <h2>Create Survey</h2>
      <form @submit.prevent="createSurvey">
        <div class="form-group">
          <label>Title</label>
          <input v-model="form.title" type="text" required />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="form.description"></textarea>
        </div>
        <div class="form-group">
          <label>Property Type (voting scope)</label>
          <select v-model="form.propertyType">
            <option :value="null">All owners</option>
            <option value="apartment">Apartment owners only</option>
            <option value="parking">Parking owners only</option>
          </select>
        </div>

        <div style="margin:1rem 0">
          <h3 style="font-size:1rem;margin-bottom:0.5rem">Questions</h3>
          <div v-for="(q, i) in form.questions" :key="i" style="margin-bottom:0.7rem;padding:0.5rem;border:1px solid #eee;border-radius:4px">
            <div style="display:flex;gap:0.5rem;align-items:center">
              <input v-model="q.text" type="text" placeholder="Question text" style="flex:1" required />
              <button type="button" @click="form.questions.splice(i, 1)" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:1.2rem">&times;</button>
            </div>
            <input v-model="q.description" type="text" placeholder="Description (optional)" style="width:100%;margin-top:0.3rem;font-size:0.85rem" />
          </div>
          <button type="button" class="btn" style="background:#e0e0e0" @click="form.questions.push({ text: '', description: '' })">+ Add Question</button>
        </div>

        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          {{ submitting ? 'Creating...' : 'Create Survey' }}
        </button>
      </form>
    </div>

    <div v-if="loading" class="card">Loading...</div>

    <div v-for="survey in surveys" :key="survey.id" class="card">
      <NuxtLink :to="`/surveys/${survey.id}`" style="text-decoration:none;color:inherit">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <strong>{{ survey.title }}</strong>
          <span class="badge" :class="survey.isActive ? 'badge-new' : 'badge-closed'">
            {{ survey.isActive ? 'Active' : 'Closed' }}
          </span>
        </div>
        <p v-if="survey.description" style="margin-top:0.5rem;font-size:0.9rem;color:#666">
          {{ survey.description?.substring(0, 120) }}
        </p>
        <small style="color:#999">
          <span v-if="!selectedOrgId && survey.organization?.name" style="font-weight:500;color:#555">{{ survey.organization.name }} &middot; </span>
          <span v-if="survey.propertyType" style="font-weight:500;color:#555">{{ survey.propertyType === 'parking' ? 'Parking owners' : 'Apartment owners' }} &middot; </span>
          {{ survey.questions?.length || 0 }} questions
          &middot; Created {{ new Date(survey.createdAt).toLocaleDateString() }}
        </small>
      </NuxtLink>
    </div>

    <p v-if="!loading && !auth.isPlatformAdmin && !selectedOrgId" class="card">Select an organization to view surveys.</p>
    <p v-else-if="!loading && surveys.length === 0" class="card">No surveys found.</p>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();
const surveys = ref<any[]>([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');
const activeFilter = ref('');

const form = reactive({
  title: '',
  description: '',
  propertyType: null as string | null,
  questions: [{ text: '', description: '' }] as { text: string; description: string }[],
});

const orgs = computed(() => orgStore.allOrgs);
const selectedOrgId = ref<number | null>(null);

const canCreate = computed(() => {
  if (auth.isPlatformAdmin) return true;
  if (!selectedOrgId.value) return false;
  const membership = orgStore.memberships.find(m => m.organization.id === selectedOrgId.value);
  return membership?.role === 'ROLE_ADMIN' || membership?.role === 'ROLE_MANAGER';
});

function onFilterChange() {
  showForm.value = false;
  loadSurveys();
}

function withOrgContext<T>(fn: () => Promise<T>): Promise<T> {
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(selectedOrgId.value);
  return fn().finally(() => {
    orgStore.setCurrentOrg(savedOrg);
  });
}

async function loadSurveys() {
  loading.value = true;
  try {
    let url = '/api/surveys';
    const params: string[] = [];
    if (activeFilter.value) {
      params.push(`isActive=${activeFilter.value}`);
    }
    if (params.length) {
      url += '?' + params.join('&');
    }
    const data = await withOrgContext(() => api.get<any>(url));
    surveys.value = data['hydra:member'] || data.member || [];
  } catch {
    surveys.value = [];
  } finally {
    loading.value = false;
  }
}

async function createSurvey() {
  error.value = '';
  submitting.value = true;
  try {
    const survey = await withOrgContext(() =>
      api.post<any>('/api/surveys', {
        title: form.title,
        description: form.description,
        propertyType: form.propertyType,
        isActive: true,
      })
    );

    // Create questions for the survey
    const surveyIri = survey['@id'] || `/api/surveys/${survey.id}`;
    for (const q of form.questions.filter(q => q.text.trim())) {
      await withOrgContext(() =>
        api.post('/api/survey_questions', {
          survey: surveyIri,
          questionText: q.text,
          description: q.description || null,
        })
      );
    }

    form.title = '';
    form.description = '';
    form.propertyType = null;
    form.questions = [{ text: '', description: '' }];
    showForm.value = false;
    await loadSurveys();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  if (auth.isPlatformAdmin) {
    selectedOrgId.value = null;
  } else if (orgs.value.length > 0) {
    selectedOrgId.value = orgStore.currentOrgId && orgs.value.some(o => o.id === orgStore.currentOrgId)
      ? orgStore.currentOrgId
      : orgs.value[0].id;
  }
  loadSurveys();
});
</script>
