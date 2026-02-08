<template>
  <div>
    <NuxtLink to="/surveys" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Surveys</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="survey">
      <!-- View Mode -->
      <template v-if="!editing">
        <div class="card" style="margin-top:1rem">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <h1>{{ survey.title }}</h1>
            <div style="display:flex;gap:0.5rem;align-items:center">
              <span class="badge" :class="statusClass">{{ statusLabel }}</span>
              <button v-if="canEdit" class="btn" style="font-size:0.85rem" @click="startEditing">Edit</button>
            </div>
          </div>
          <p v-if="survey.description" style="margin-top:0.5rem;color:#666">{{ survey.description }}</p>
          <div v-if="survey.propertyType" style="margin-top:0.5rem">
            <span style="display:inline-block;padding:0.15rem 0.5rem;border-radius:3px;font-size:0.8rem;font-weight:500" :style="survey.propertyType === 'parking' ? 'background:#fff3e0;color:#e65100' : 'background:#e3f2fd;color:#1565c0'">
              {{ survey.propertyType === 'parking' ? 'Parking owners only' : 'Apartment owners only' }}
            </span>
          </div>
          <small style="color:#999">
            Created by {{ survey.createdBy?.firstName }} {{ survey.createdBy?.lastName }}
            on {{ new Date(survey.createdAt).toLocaleDateString() }}
          </small>
          <div style="margin-top:0.5rem;font-size:0.9rem;color:#555">
            Voting period: <strong>{{ formatDate(survey.startDate) }}</strong> — <strong>{{ formatDate(survey.endDate) }}</strong>
          </div>
        </div>

        <div v-for="question in questions" :key="question.id" class="card">
          <p style="font-weight:600;margin-bottom:0.3rem">{{ question.questionText }}</p>
          <p v-if="question.description" style="font-size:0.85rem;color:#666;margin-bottom:0.7rem">{{ question.description }}</p>

          <div style="display:flex;gap:0.5rem;margin-bottom:0.7rem">
            <button
              class="btn vote-btn"
              :class="{ active: userVotes[question.id] === true }"
              @click="castVote(question, true)"
              :disabled="!canVote || voting[question.id]"
            >
              Yes {{ voteCounts[question.id]?.yesCount || 0 }}
            </button>
            <button
              class="btn vote-btn"
              :class="{ 'active-no': userVotes[question.id] === false }"
              @click="castVote(question, false)"
              :disabled="!canVote || voting[question.id]"
            >
              No {{ voteCounts[question.id]?.noCount || 0 }}
            </button>
          </div>

          <!-- Weighted results -->
          <div v-if="voteCounts[question.id]" style="font-size:0.85rem;color:#666;background:#f9f9f9;padding:0.5rem;border-radius:4px">
            <div style="display:flex;justify-content:space-between">
              <span>Yes: <strong>{{ voteCounts[question.id].yesWeight.toFixed(2) }} m2</strong></span>
              <span>No: <strong>{{ voteCounts[question.id].noWeight.toFixed(2) }} m2</strong></span>
              <span>Total: <strong>{{ (voteCounts[question.id].yesWeight + voteCounts[question.id].noWeight).toFixed(2) }} / {{ totalArea.toFixed(2) }} m2</strong></span>
            </div>
            <div v-if="totalArea > 0" style="margin-top:0.4rem;display:flex;gap:1rem;font-weight:600">
              <span style="color:#4caf50">Yes: {{ pct(voteCounts[question.id].yesWeight) }}%</span>
              <span style="color:#f44336">No: {{ pct(voteCounts[question.id].noWeight) }}%</span>
              <span style="color:#999">No vote: {{ pct(totalArea - voteCounts[question.id].yesWeight - voteCounts[question.id].noWeight) }}%</span>
            </div>
            <div v-if="totalArea > 0" style="margin-top:0.3rem;background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;display:flex">
              <div
                style="background:#4caf50;height:100%"
                :style="{ width: pct(voteCounts[question.id].yesWeight) + '%' }"
              ></div>
              <div
                style="background:#f44336;height:100%"
                :style="{ width: pct(voteCounts[question.id].noWeight) + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </template>

      <!-- Edit Mode -->
      <template v-if="editing">
        <div class="card" style="margin-top:1rem">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h2>Edit Survey</h2>
            <button class="btn" style="font-size:0.85rem" @click="editing = false">Cancel</button>
          </div>
          <form @submit.prevent="saveSurvey">
            <div class="form-group">
              <label>Title</label>
              <input v-model="editForm.title" type="text" required />
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea v-model="editForm.description"></textarea>
            </div>
            <div style="display:flex;gap:1rem">
              <div class="form-group" style="flex:1">
                <label>Start Date</label>
                <input v-model="editForm.startDate" type="date" required />
              </div>
              <div class="form-group" style="flex:1">
                <label>End Date</label>
                <input v-model="editForm.endDate" type="date" required />
              </div>
            </div>
            <div class="form-group">
              <label>Property Type (voting scope)</label>
              <select v-model="editForm.propertyType">
                <option :value="null">All owners</option>
                <option value="apartment">Apartment owners only</option>
                <option value="parking">Parking owners only</option>
              </select>
            </div>
            <div class="form-group">
              <label>
                <input type="checkbox" v-model="editForm.isActive" style="margin-right:0.3rem" />
                Active
              </label>
            </div>
            <p v-if="editError" class="error">{{ editError }}</p>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save Survey' }}
            </button>
          </form>
        </div>

        <!-- Edit Questions -->
        <div class="card">
          <h3 style="margin-bottom:0.7rem">Questions</h3>

          <div v-for="(q, i) in editQuestions" :key="q.id || `new-${i}`" style="margin-bottom:0.7rem;padding:0.7rem;border:1px solid #eee;border-radius:4px">
            <div style="display:flex;gap:0.5rem;align-items:start">
              <textarea v-model="q.questionText" placeholder="Question text" style="flex:1;min-height:60px" required></textarea>
              <button type="button" @click="removeQuestion(i)" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:1.2rem" title="Remove question">&times;</button>
            </div>
            <input v-model="q.description" type="text" placeholder="Description (optional)" style="width:100%;margin-top:0.3rem;font-size:0.85rem" />
            <div style="margin-top:0.3rem">
              <button v-if="q.id && q.dirty" type="button" class="btn" style="font-size:0.8rem;padding:0.2rem 0.6rem" @click="saveQuestion(q)" :disabled="q.saving">
                {{ q.saving ? 'Saving...' : 'Save' }}
              </button>
              <span v-if="q.id && !q.dirty" style="font-size:0.8rem;color:#999">Saved</span>
              <span v-if="!q.id" style="font-size:0.8rem;color:#e65100">New — will be created on save</span>
            </div>
          </div>

          <button type="button" class="btn" style="background:#e0e0e0" @click="addQuestion">+ Add Question</button>

          <div v-if="editQuestions.some(q => !q.id)" style="margin-top:0.7rem">
            <button class="btn btn-primary" style="font-size:0.85rem" @click="saveNewQuestions" :disabled="savingQuestions">
              {{ savingQuestions ? 'Creating...' : 'Create New Questions' }}
            </button>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();
const survey = ref<any>(null);
const questions = ref<any[]>([]);
const userVotes = ref<Record<number, boolean | null>>({});
const voteCounts = ref<Record<number, { yesCount: number; noCount: number; yesWeight: number; noWeight: number }>>({});
const totalArea = ref(0);
const loading = ref(true);

// Edit state
const editing = ref(false);
const saving = ref(false);
const savingQuestions = ref(false);
const editError = ref('');
const editForm = reactive({
  title: '',
  description: '',
  startDate: '',
  endDate: '',
  propertyType: null as string | null,
  isActive: true,
});
const editQuestions = ref<Array<{
  id: number | null;
  questionText: string;
  description: string;
  dirty: boolean;
  saving: boolean;
  _origText: string;
  _origDesc: string;
}>>([]);

function pct(weight: number): string {
  if (totalArea.value <= 0) return '0.00';
  return (weight / totalArea.value * 100).toFixed(2);
}

function formatDate(d: string | null) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString();
}

function toDateInput(d: string | null): string {
  if (!d) return '';
  return new Date(d).toISOString().split('T')[0];
}

const canEdit = computed(() => {
  if (auth.isPlatformAdmin) return true;
  const orgId = survey.value?.organization?.id;
  if (!orgId) return false;
  const membership = orgStore.memberships.find(m => m.organization.id === orgId);
  return membership?.role === 'ROLE_ADMIN' || membership?.role === 'ROLE_MANAGER';
});

const canVote = computed(() => {
  if (!survey.value?.isActive) return false;
  const now = new Date();
  const start = survey.value.startDate ? new Date(survey.value.startDate) : null;
  const end = survey.value.endDate ? new Date(survey.value.endDate) : null;
  if (start && now < start) return false;
  if (end && now > end) return false;
  return true;
});

const statusLabel = computed(() => {
  if (!survey.value) return '';
  const now = new Date();
  const start = survey.value.startDate ? new Date(survey.value.startDate) : null;
  const end = survey.value.endDate ? new Date(survey.value.endDate) : null;
  if (!survey.value.isActive) return 'Closed';
  if (start && now < start) return 'Upcoming';
  if (end && now > end) return 'Ended';
  return 'Active';
});

const statusClass = computed(() => {
  if (statusLabel.value === 'Active') return 'badge-new';
  if (statusLabel.value === 'Upcoming') return 'badge-pending';
  return 'badge-closed';
});

function startEditing() {
  editForm.title = survey.value.title;
  editForm.description = survey.value.description || '';
  editForm.startDate = toDateInput(survey.value.startDate);
  editForm.endDate = toDateInput(survey.value.endDate);
  editForm.propertyType = survey.value.propertyType || null;
  editForm.isActive = survey.value.isActive;
  editQuestions.value = questions.value.map(q => ({
    id: q.id,
    questionText: q.questionText,
    description: q.description || '',
    dirty: false,
    saving: false,
    _origText: q.questionText,
    _origDesc: q.description || '',
  }));
  editError.value = '';
  editing.value = true;
}

// Watch for question edits to mark dirty
watch(editQuestions, (qs) => {
  for (const q of qs) {
    if (q.id) {
      q.dirty = q.questionText !== q._origText || q.description !== q._origDesc;
    }
  }
}, { deep: true });

function addQuestion() {
  editQuestions.value.push({
    id: null,
    questionText: '',
    description: '',
    dirty: false,
    saving: false,
    _origText: '',
    _origDesc: '',
  });
}

async function removeQuestion(index: number) {
  const q = editQuestions.value[index];
  if (q.id) {
    if (!confirm('Delete this question? All its votes will be lost.')) return;
    try {
      await withOrgContext(() => api.delete(`/api/survey_questions/${q.id}`));
    } catch (e: any) {
      alert(e.message || 'Failed to delete question');
      return;
    }
  }
  editQuestions.value.splice(index, 1);
}

async function saveQuestion(q: typeof editQuestions.value[0]) {
  if (!q.id) return;
  q.saving = true;
  try {
    await withOrgContext(() => api.patch(`/api/survey_questions/${q.id}`, {
      questionText: q.questionText,
      description: q.description || null,
    }));
    q._origText = q.questionText;
    q._origDesc = q.description;
    q.dirty = false;
  } catch (e: any) {
    alert(e.message || 'Failed to save question');
  } finally {
    q.saving = false;
  }
}

async function saveNewQuestions() {
  savingQuestions.value = true;
  const surveyIri = survey.value['@id'] || `/api/surveys/${survey.value.id}`;
  try {
    for (const q of editQuestions.value.filter(q => !q.id && q.questionText.trim())) {
      const created = await withOrgContext(() => api.post<any>('/api/survey_questions', {
        survey: surveyIri,
        questionText: q.questionText,
        description: q.description || null,
      }));
      q.id = created.id;
      q._origText = q.questionText;
      q._origDesc = q.description;
      q.dirty = false;
    }
  } catch (e: any) {
    alert(e.message || 'Failed to create questions');
  } finally {
    savingQuestions.value = false;
  }
}

async function saveSurvey() {
  editError.value = '';
  saving.value = true;
  try {
    await withOrgContext(() => api.patch(`/api/surveys/${survey.value.id}`, {
      title: editForm.title,
      description: editForm.description || null,
      startDate: editForm.startDate + 'T00:00:00+00:00',
      endDate: editForm.endDate + 'T23:59:59+00:00',
      propertyType: editForm.propertyType,
      isActive: editForm.isActive,
    }));

    // Save dirty existing questions
    for (const q of editQuestions.value.filter(q => q.id && q.dirty)) {
      await saveQuestion(q);
    }

    // Create new questions
    if (editQuestions.value.some(q => !q.id && q.questionText.trim())) {
      await saveNewQuestions();
    }

    editing.value = false;
    await loadSurvey();
  } catch (e: any) {
    editError.value = e.message || 'Failed to save';
  } finally {
    saving.value = false;
  }
}

function withOrgContext<T>(fn: () => Promise<T>): Promise<T> {
  const orgId = survey.value?.organization?.id;
  if (!orgId) return fn();
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId);
  return fn().finally(() => {
    orgStore.setCurrentOrg(savedOrg);
  });
}

async function loadSurvey() {
  loading.value = true;
  try {
    survey.value = await api.get(`/api/surveys/${route.params.id}`);

    const qData = await api.get<any>(`/api/survey_questions?survey=/api/surveys/${route.params.id}`);
    questions.value = qData['hydra:member'] || qData.member || [];

    // Fetch total org area (respecting propertyType)
    const orgId = survey.value.organization?.id;
    if (orgId) {
      const ptParam = survey.value.propertyType ? `?propertyType=${survey.value.propertyType}` : '';
      const areaData = await api.get<any>(`/api/organizations/${orgId}/total-area${ptParam}`);
      totalArea.value = parseFloat(areaData.totalArea || '0');
    }

    await loadVotes();
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function loadVotes() {
  const questionIds = questions.value.map((q: any) => q.id);
  let allVotes: any[] = [];
  for (const qId of questionIds) {
    const vData = await api.get<any>(`/api/survey_votes?question=/api/survey_questions/${qId}`);
    const qVotes = vData['hydra:member'] || vData.member || [];
    allVotes.push(...qVotes);
  }

  const userId = auth.user?.id;
  for (const q of questions.value) {
    const qVotes = allVotes.filter((v: any) => {
      const qRef = typeof v.question === 'string' ? v.question : v.question?.['@id'] || `/api/survey_questions/${v.question?.id}`;
      return qRef === `/api/survey_questions/${q.id}`;
    });

    const yesVotes = qVotes.filter((v: any) => v.vote === true);
    const noVotes = qVotes.filter((v: any) => v.vote === false);

    voteCounts.value[q.id] = {
      yesCount: yesVotes.length,
      noCount: noVotes.length,
      yesWeight: yesVotes.reduce((sum: number, v: any) => sum + parseFloat(v.weight || '0'), 0),
      noWeight: noVotes.reduce((sum: number, v: any) => sum + parseFloat(v.weight || '0'), 0),
    };

    const myVote = qVotes.find((v: any) => {
      const uRef = typeof v.user === 'string' ? v.user : v.user?.['@id'] || `/api/users/${v.user?.id}`;
      return uRef === `/api/users/${userId}`;
    });
    userVotes.value[q.id] = myVote ? myVote.vote : null;
  }
}

const voting = ref<Record<number, boolean>>({});

async function castVote(question: any, vote: boolean) {
  voting.value[question.id] = true;
  try {
    const questionIri = question['@id'] || `/api/survey_questions/${question.id}`;
    await api.post('/api/survey_votes', {
      question: questionIri,
      vote,
    });
    userVotes.value[question.id] = vote;
    await loadVotes();
  } catch (e: any) {
    if (e.message) alert(e.message);
  } finally {
    voting.value[question.id] = false;
  }
}

onMounted(loadSurvey);
</script>

<style scoped>
.vote-btn {
  background: #e0e0e0;
  padding: 0.4rem 1.2rem;
}
.vote-btn.active {
  background: #4caf50;
  color: white;
}
.vote-btn.active-no {
  background: #f44336;
  color: white;
}
</style>
