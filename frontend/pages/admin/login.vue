<template>
  <div class="auth-page">
    <div class="auth-card card">
      <h2>Admin Login</h2>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label>Email</label>
          <input v-model="email" type="email" required placeholder="admin@example.com" />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input v-model="password" type="password" required placeholder="Password" />
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
      <p class="auth-link">
        <NuxtLink to="/login">Back to user login</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function handleLogin() {
  error.value = '';
  loading.value = true;
  try {
    await auth.adminLogin(email.value, password.value);
    navigateTo('/');
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.auth-page {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 80vh;
}
.auth-card {
  width: 100%;
  max-width: 400px;
}
.auth-card h2 {
  text-align: center;
  margin-bottom: 1.5rem;
}
.auth-card .btn {
  width: 100%;
  padding: 0.7rem;
  margin-top: 0.5rem;
}
.auth-link {
  text-align: center;
  margin-top: 1rem;
  font-size: 0.9rem;
}
.auth-link a {
  color: #1a73e8;
}
</style>
