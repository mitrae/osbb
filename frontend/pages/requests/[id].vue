<template>
  <div>
    <NuxtLink to="/requests" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Requests</NuxtLink>

    <div v-if="loading" class="card" style="margin-top:1rem">Loading...</div>

    <div v-else-if="request" class="card" style="margin-top:1rem">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h1>{{ request.title }}</h1>
        <div style="display:flex;align-items:center;gap:0.5rem">
          <span v-if="request.visibility === 'public'" style="font-size:0.75rem;color:#666;border:1px solid #ddd;padding:0.1rem 0.4rem;border-radius:8px">public</span>
          <span :class="`badge badge-${request.status}`">{{ request.status }}</span>
        </div>
      </div>

      <p style="margin:1rem 0;line-height:1.6">{{ request.description }}</p>

      <div style="font-size:0.9rem;color:#666;margin-top:1rem">
        <p>Author: {{ request.author?.firstName }} {{ request.author?.lastName }}</p>
        <p v-if="request.assignee">Assignee: {{ request.assignee?.firstName }} {{ request.assignee?.lastName }}</p>
        <p>Created: {{ new Date(request.createdAt).toLocaleString() }}</p>
        <p>Updated: {{ new Date(request.updatedAt).toLocaleString() }}</p>
      </div>

      <div v-if="org.isOrgAdmin || auth.isPlatformAdmin" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #eee">
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

    <!-- Comments -->
    <div v-if="request" class="card">
      <h2>Comments ({{ request.comments?.length || 0 }})</h2>

      <div v-if="request.comments?.length === 0" style="color:#666;font-size:0.9rem">No comments yet.</div>

      <div v-for="comment in request.comments" :key="comment.id" style="padding:0.7rem 0;border-bottom:1px solid #eee">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <strong style="font-size:0.9rem">{{ comment.author?.firstName }} {{ comment.author?.lastName }}</strong>
          <small style="color:#999">{{ new Date(comment.createdAt).toLocaleString() }}</small>
        </div>
        <p style="margin-top:0.3rem;font-size:0.9rem;line-height:1.5">{{ comment.body }}</p>
      </div>

      <form @submit.prevent="postComment" style="margin-top:1rem">
        <div class="form-group">
          <label>Add a comment</label>
          <textarea v-model="commentBody" placeholder="Write your comment..." style="min-height:60px" required></textarea>
        </div>
        <p v-if="commentError" class="error">{{ commentError }}</p>
        <button type="submit" class="btn btn-primary" :disabled="commentSubmitting">
          {{ commentSubmitting ? 'Posting...' : 'Post Comment' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const org = useOrganizationStore();
const request = ref<any>(null);
const loading = ref(true);
const statuses = ['open', 'in_progress', 'resolved', 'closed'];
const commentBody = ref('');
const commentError = ref('');
const commentSubmitting = ref(false);

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

async function postComment() {
  commentError.value = '';
  commentSubmitting.value = true;
  try {
    await api.post('/api/request_comments', {
      request: `/api/requests/${route.params.id}`,
      body: commentBody.value,
    });
    commentBody.value = '';
    await loadRequest();
  } catch (e: any) {
    commentError.value = e.message;
  } finally {
    commentSubmitting.value = false;
  }
}

onMounted(loadRequest);
</script>
