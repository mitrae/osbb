<template>
  <div class="auth-page">
    <div class="auth-card card">
      <h2>Register</h2>
      <form @submit.prevent="handleRegister">
        <div class="form-group">
          <label>First Name</label>
          <input v-model="form.firstName" type="text" required />
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input v-model="form.lastName" type="text" required />
        </div>
        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" required />
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input v-model="form.phone" type="tel" />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input v-model="form.password" type="password" required minlength="6" />
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? 'Registering...' : 'Register' }}
        </button>
      </form>
      <p class="auth-link">
        Already have an account? <NuxtLink to="/login">Login</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore();
const error = ref('');
const loading = ref(false);

const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  password: '',
});

async function handleRegister() {
  error.value = '';
  loading.value = true;
  try {
    await auth.register(form);
    await auth.login(form.email, form.password);
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
