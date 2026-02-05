<template>
  <div>
    <div class="page-header">
      <h1>Surveys</h1>
      <button v-if="auth.isManager" class="btn btn-primary" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Survey' }}
      </button>
    </div>

    <div v-if="showForm" class="card">
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

        <div style="margin:1rem 0">
          <h3 style="font-size:1rem;margin-bottom:0.5rem">Questions</h3>
          <div v-for="(q, i) in form.questions" :key="i" style="display:flex;gap:0.5rem;margin-bottom:0.5rem">
            <input v-model="form.questions[i]" type="text" placeholder="Question text" style="flex:1" required />
            <button type="button" @click="form.questions.splice(i, 1)" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:1.2rem">&times;</button>
          </div>
          <button type="button" class="btn" style="background:#e0e0e0" @click="form.questions.push('')">+ Add Question</button>
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
          <span class="badge" :class="survey.isActive ? 'badge-new' : 'badge-rejected'">
            {{ survey.isActive ? 'Active' : 'Closed' }}
          </span>
        </div>
        <p v-if="survey.description" style="margin-top:0.5rem;font-size:0.9rem;color:#666">
          {{ survey.description?.substring(0, 120) }}
        </p>
        <small style="color:#999">
          {{ survey.questions?.length || 0 }} questions
          &middot; Created {{ new Date(survey.createdAt).toLocaleDateString() }}
        </small>
      </NuxtLink>
    </div>

    <p v-if="!loading && surveys.length === 0" class="card">No surveys yet.</p>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const auth = useAuthStore();
const surveys = ref<any[]>([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');

const form = reactive({
  title: '',
  description: '',
  questions: [''] as string[],
});

async function loadSurveys() {
  loading.value = true;
  try {
    const data = await api.get<any>('/api/surveys');
    surveys.value = data['hydra:member'] || data.member || [];
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function createSurvey() {
  error.value = '';
  submitting.value = true;
  try {
    const survey = await api.post<any>('/api/surveys', {
      title: form.title,
      description: form.description,
      isActive: true,
    });

    // Create questions for the survey
    const surveyIri = survey['@id'] || `/api/surveys/${survey.id}`;
    for (const questionText of form.questions.filter(q => q.trim())) {
      await api.post('/api/survey_questions', {
        survey: surveyIri,
        questionText,
      });
    }

    form.title = '';
    form.description = '';
    form.questions = [''];
    showForm.value = false;
    await loadSurveys();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    submitting.value = false;
  }
}

onMounted(loadSurveys);
</script>
