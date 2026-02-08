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
        <div style="display:flex;gap:1rem">
          <div class="form-group" style="flex:1">
            <label>Start Date</label>
            <input v-model="form.startDate" type="date" required />
          </div>
          <div class="form-group" style="flex:1">
            <label>End Date</label>
            <input v-model="form.endDate" type="date" required />
          </div>
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
            <div style="display:flex;gap:0.5rem;align-items:start">
              <textarea v-model="q.text" placeholder="Question text" style="flex:1;min-height:60px" required></textarea>
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
          <span class="badge" :class="surveyStatusClass(survey)">
            {{ surveyStatusLabel(survey) }}
          </span>
        </div>
        <p v-if="survey.description" style="margin-top:0.5rem;font-size:0.9rem;color:#666">
          {{ survey.description?.substring(0, 120) }}
        </p>
        <small style="color:#999">
          <span v-if="!selectedOrgId && survey.organization?.name" style="font-weight:500;color:#555">{{ survey.organization.name }} &middot; </span>
          <span v-if="survey.propertyType" style="font-weight:500;color:#555">{{ survey.propertyType === 'parking' ? 'Parking owners' : 'Apartment owners' }} &middot; </span>
          {{ survey.questions?.length || 0 }} questions
          &middot; {{ formatDate(survey.startDate) }} — {{ formatDate(survey.endDate) }}
        </small>
      </NuxtLink>

      <!-- Collapsible Results Summary -->
      <div v-if="survey.questions?.length" style="margin-top:0.5rem;border-top:1px solid #eee;padding-top:0.5rem">
        <button
          @click.prevent="toggleSummary(survey)"
          style="background:none;border:none;cursor:pointer;color:#1a73e8;font-size:0.85rem;padding:0;display:flex;align-items:center;gap:0.3rem"
        >
          <span style="font-size:0.7rem">{{ expandedSurveys[survey.id] ? '▼' : '▶' }}</span>
          Results Summary
        </button>
        <div v-if="expandedSurveys[survey.id]" style="margin-top:0.5rem">
          <div v-if="summaryLoading[survey.id]" style="font-size:0.85rem;color:#999">Loading results...</div>
          <div v-else-if="surveySummaries[survey.id]" style="font-size:0.85rem">
            <div
              v-for="q in surveySummaries[survey.id].questions"
              :key="q.id"
              style="padding:0.4rem 0.5rem;margin-bottom:0.3rem;border-radius:4px;display:flex;justify-content:space-between;align-items:center;gap:0.5rem"
              :style="{ background: q.yesPct > 50 ? '#e8f5e9' : '#fce4ec' }"
            >
              <span style="flex:1">{{ q.questionText }}</span>
              <span style="white-space:nowrap;font-weight:600" :style="{ color: q.yesPct > 50 ? '#2e7d32' : '#c62828' }">
                {{ q.yesPct.toFixed(1) }}% — {{ q.yesPct > 50 ? 'Passed' : 'Not Passed' }}
              </span>
            </div>
            <div v-if="surveySummaries[survey.id].totalArea <= 0" style="color:#999;font-size:0.8rem">
              No area data available for this organization.
            </div>
          </div>
        </div>
      </div>
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
const activeFilter = ref('true');

const expandedSurveys = ref<Record<number, boolean>>({});
const summaryLoading = ref<Record<number, boolean>>({});
const surveySummaries = ref<Record<number, { totalArea: number; questions: Array<{ id: number; questionText: string; yesPct: number }> }>>({});

async function toggleSummary(survey: any) {
  const id = survey.id;
  expandedSurveys.value[id] = !expandedSurveys.value[id];
  if (expandedSurveys.value[id] && !surveySummaries.value[id]) {
    await loadSurveySummary(survey);
  }
}

async function loadSurveySummary(survey: any) {
  const id = survey.id;
  summaryLoading.value[id] = true;
  try {
    const orgId = survey.organization?.id;
    let totalArea = 0;
    if (orgId) {
      const ptParam = survey.propertyType ? `?propertyType=${survey.propertyType}` : '';
      const areaData = await withOrgContext(() => api.get<any>(`/api/organizations/${orgId}/total-area${ptParam}`));
      totalArea = parseFloat(areaData.totalArea || '0');
    }

    const questions: Array<{ id: number; questionText: string; yesPct: number }> = [];
    for (const q of (survey.questions || [])) {
      const vData = await withOrgContext(() => api.get<any>(`/api/survey_votes?question=/api/survey_questions/${q.id}`));
      const votes = vData['hydra:member'] || vData.member || [];
      const yesWeight = votes
        .filter((v: any) => v.vote === true)
        .reduce((sum: number, v: any) => sum + parseFloat(v.weight || '0'), 0);
      const yesPct = totalArea > 0 ? (yesWeight / totalArea) * 100 : 0;
      questions.push({ id: q.id, questionText: q.questionText, yesPct });
    }

    surveySummaries.value[id] = { totalArea, questions };
  } catch {
    // silent fail
  } finally {
    summaryLoading.value[id] = false;
  }
}

const form = reactive({
  title: '',
  description: '',
  startDate: '',
  endDate: '',
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

function formatDate(d: string | null) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString();
}

function surveyStatusLabel(survey: any) {
  const now = new Date();
  const start = survey.startDate ? new Date(survey.startDate) : null;
  const end = survey.endDate ? new Date(survey.endDate) : null;
  if (!survey.isActive) return 'Closed';
  if (start && now < start) return 'Upcoming';
  if (end && now > end) return 'Ended';
  return 'Active';
}

function surveyStatusClass(survey: any) {
  const label = surveyStatusLabel(survey);
  if (label === 'Active') return 'badge-new';
  if (label === 'Upcoming') return 'badge-pending';
  return 'badge-closed';
}

function onFilterChange() {
  showForm.value = false;
  expandedSurveys.value = {};
  surveySummaries.value = {};
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
        startDate: form.startDate + 'T00:00:00+00:00',
        endDate: form.endDate + 'T23:59:59+00:00',
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
    form.startDate = '';
    form.endDate = '';
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
