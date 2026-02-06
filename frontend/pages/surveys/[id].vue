<template>
  <div>
    <NuxtLink to="/surveys" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Surveys</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <template v-else-if="survey">
      <div class="card" style="margin-top:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h1>{{ survey.title }}</h1>
          <span class="badge" :class="survey.isActive ? 'badge-new' : 'badge-closed'">
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
        <p style="font-weight:600;margin-bottom:0.3rem">{{ question.questionText }}</p>
        <p v-if="question.description" style="font-size:0.85rem;color:#666;margin-bottom:0.7rem">{{ question.description }}</p>

        <div style="display:flex;gap:0.5rem;margin-bottom:0.7rem">
          <button
            class="btn vote-btn"
            :class="{ active: votes[question.id] === true }"
            @click="castVote(question, true)"
            :disabled="!survey.isActive"
          >
            Yes {{ voteCounts[question.id]?.yesCount || 0 }}
          </button>
          <button
            class="btn vote-btn"
            :class="{ 'active-no': votes[question.id] === false }"
            @click="castVote(question, false)"
            :disabled="!survey.isActive"
          >
            No {{ voteCounts[question.id]?.noCount || 0 }}
          </button>
        </div>

        <!-- Weighted results -->
        <div v-if="voteCounts[question.id]" style="font-size:0.85rem;color:#666;background:#f9f9f9;padding:0.5rem;border-radius:4px">
          <div style="display:flex;justify-content:space-between">
            <span>Yes: <strong>{{ voteCounts[question.id].yesWeight.toFixed(2) }} m2</strong></span>
            <span>No: <strong>{{ voteCounts[question.id].noWeight.toFixed(2) }} m2</strong></span>
            <span>Total: <strong>{{ (voteCounts[question.id].yesWeight + voteCounts[question.id].noWeight).toFixed(2) }} m2</strong></span>
          </div>
          <div v-if="voteCounts[question.id].yesWeight + voteCounts[question.id].noWeight > 0" style="margin-top:0.3rem;background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden">
            <div
              style="background:#4caf50;height:100%"
              :style="{ width: `${(voteCounts[question.id].yesWeight / (voteCounts[question.id].yesWeight + voteCounts[question.id].noWeight) * 100)}%` }"
            ></div>
          </div>
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
const voteCounts = ref<Record<number, { yesCount: number; noCount: number; yesWeight: number; noWeight: number }>>({});
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

    // Count votes and weights per question
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
