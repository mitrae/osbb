<template>
  <div class="app">
    <nav v-if="auth.isAuthenticated" class="navbar">
      <div class="navbar-brand">
        <NuxtLink to="/">OSBB Portal</NuxtLink>
      </div>
      <div class="navbar-menu">
        <NuxtLink to="/">Dashboard</NuxtLink>
        <NuxtLink to="/requests">Requests</NuxtLink>
        <NuxtLink to="/surveys">Surveys</NuxtLink>
      </div>
      <div class="navbar-end">
        <span class="user-name">{{ auth.user?.type === 'admin' ? auth.user?.email : `${auth.user?.firstName} ${auth.user?.lastName}` }}</span>
        <button class="btn btn-sm" @click="auth.logout()">Logout</button>
      </div>
    </nav>
    <main class="container">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore();
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #f5f5f5;
  color: #333;
}

.app {
  min-height: 100vh;
}

.navbar {
  background: #1a73e8;
  color: white;
  padding: 0 2rem;
  height: 56px;
  display: flex;
  align-items: center;
  gap: 2rem;
}

.navbar-brand a {
  color: white;
  text-decoration: none;
  font-weight: 700;
  font-size: 1.2rem;
}

.navbar-menu {
  display: flex;
  gap: 1.5rem;
}

.navbar-menu a {
  color: rgba(255, 255, 255, 0.85);
  text-decoration: none;
  font-size: 0.95rem;
}

.navbar-menu a:hover,
.navbar-menu a.router-link-active {
  color: white;
}

.navbar-end {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-name {
  font-size: 0.9rem;
  opacity: 0.9;
}

.container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 2rem;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: #1a73e8;
  color: white;
}

.btn-primary:hover {
  background: #1557b0;
}

.btn-sm {
  padding: 0.3rem 0.7rem;
  font-size: 0.85rem;
  background: rgba(255, 255, 255, 0.2);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}

.btn-sm:hover {
  background: rgba(255, 255, 255, 0.3);
}

.card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.3rem;
  font-weight: 500;
  font-size: 0.9rem;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.95rem;
}

.form-group textarea {
  min-height: 100px;
  resize: vertical;
}

.error {
  color: #d32f2f;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}

.badge {
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.badge-new { background: #e3f2fd; color: #1565c0; }
.badge-in_progress { background: #fff3e0; color: #e65100; }
.badge-resolved { background: #e8f5e9; color: #2e7d32; }
.badge-rejected { background: #fce4ec; color: #c62828; }

h1 { font-size: 1.8rem; margin-bottom: 1.5rem; }
h2 { font-size: 1.4rem; margin-bottom: 1rem; }

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
</style>
