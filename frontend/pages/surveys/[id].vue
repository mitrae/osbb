<template>
  <div>
    <NuxtLink to="/surveys" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Surveys</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="survey">
      <div class="card" style="margin-top:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h1>{{ survey.title }}</h1>
          <span class="badge" :class="survey.isActive ? 'badge-new' : 'badge-rejected'">
            {{ survey.isActive ? 'Active' : 'Closed' }}
          </span>
        </div>
        <p v-if="survey.description" style="margin-top:0.5rem;color:#666">{{ survey.description }}</p>
        <small style="color:#999">
          Created by {{ survey.createdBy?.firstName }} {{ survey.createdBy?.lastName }}
          on {{ new Date(survey.createdAt).toLocaleDateString() }}
        </small>
      </div>

      <div v-for="question in questions" :key="question.id" class="card">
        <p style="font-weight:600;margin-bottom:0.7rem">{{ question.questionText }}</p>

        <div style="display:flex;gap:0.5rem">
          <button
            class="btn vote-btn"
            :class="{ active: votes[question.id] === true }"
            @click="castVote(question, true)"
            :disabled="!survey.isActive"
          >
            Yes {{ voteCounts[question.id]?.yes || 0 }}
          </button>
          <button
            class="btn vote-btn"
            :class="{ 'active-no': votes[question.id] === false }"
            @click="castVote(question, false)"
            :disabled="!survey.isActive"
          >
            No {{ voteCounts[question.id]?.no || 0 }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const survey = ref<any>(null);
const questions = ref<any[]>([]);
const votes = ref<Record<number, boolean | null>>({});
const voteCounts = ref<Record<number, { yes: number; no: number }>>({});
const loading = ref(true);

async function loadSurvey() {
  loading.value = true;
  try {
    survey.value = await api.get(`/api/surveys/${route.params.id}`);

    // Load questions
    const qData = await api.get<any>(`/api/survey_questions?survey=/api/surveys/${route.params.id}`);
    questions.value = qData['hydra:member'] || qData.member || [];

    // Load all votes for these questions
    const vData = await api.get<any>('/api/survey_votes');
    const allVotes = vData['hydra:member'] || vData.member || [];

    // Count votes per question
    for (const q of questions.value) {
      const qVotes = allVotes.filter((v: any) => {
        const qRef = typeof v.question === 'string' ? v.question : v.question?.['@id'] || `/api/survey_questions/${v.question?.id}`;
        return qRef === `/api/survey_questions/${q.id}`;
      });
      voteCounts.value[q.id] = {
        yes: qVotes.filter((v: any) => v.vote === true).length,
        no: qVotes.filter((v: any) => v.vote === false).length,
      };
    }
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function castVote(question: any, vote: boolean) {
  try {
    const questionIri = question['@id'] || `/api/survey_questions/${question.id}`;
    await api.post('/api/survey_votes', {
      question: questionIri,
      vote,
    });
    votes.value[question.id] = vote;
    await loadSurvey();
  } catch {
    // Already voted or error
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
