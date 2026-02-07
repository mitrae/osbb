<template>
  <div>
    <h1>User Management</h1>

    <div class="card" v-if="!loading">
      <input
        v-model="search"
        type="text"
        placeholder="Search by name, email, or phone..."
        class="search-input"
      />

      <table class="users-table" v-if="filtered.length > 0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Set Password</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in filtered" :key="u.id">
            <td><strong>{{ u.firstName }} {{ u.lastName }}</strong></td>
            <td>{{ u.email }}</td>
            <td>{{ u.phone || '—' }}</td>
            <td>
              <select
                :value="u.primaryRole"
                @change="updateRole(u.id, ($event.target as HTMLSelectElement).value)"
                class="role-select"
              >
                <option value="ROLE_USER">User</option>
                <option value="ROLE_RESIDENT">Resident</option>
                <option value="ROLE_MANAGER">Manager</option>
                <option value="ROLE_ADMIN">Admin</option>
                <option value="ROLE_PLATFORM_ADMIN">Platform Admin</option>
              </select>
            </td>
            <td>
              <div class="password-form">
                <input
                  v-model="passwordInputs[u.id]"
                  type="password"
                  placeholder="New password"
                  class="password-input"
                />
                <button
                  class="btn btn-sm-action"
                  @click="setPassword(u.id)"
                  :disabled="!passwordInputs[u.id]"
                >Set</button>
              </div>
            </td>
            <td class="date-cell">{{ formatDate(u.createdAt) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No users match your search.</p>
    </div>
    <div class="card" v-else>Loading...</div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="success" class="success">{{ success }}</p>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore();
const api = useApi();

const users = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const success = ref('');
const search = ref('');
const passwordInputs = ref<Record<number, string>>({});

// Guard: redirect non-platform-admins
onMounted(() => {
  if (!auth.isPlatformAdmin) {
    navigateTo('/');
    return;
  }
  loadUsers();
});

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim();
  if (!q) return users.value;
  return users.value.filter((u) =>
    `${u.firstName} ${u.lastName}`.toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q) ||
    (u.phone && u.phone.toLowerCase().includes(q))
  );
});

function getPrimaryRole(roles: string[]): string {
  const priority = ['ROLE_PLATFORM_ADMIN', 'ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_RESIDENT', 'ROLE_USER'];
  for (const r of priority) {
    if (roles.includes(r)) return r;
  }
  return 'ROLE_USER';
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString();
}

async function loadUsers() {
  loading.value = true;
  error.value = '';
  try {
    const data = await api.get<any>('/api/users');
    const list = data['hydra:member'] || data['member'] || [];
    users.value = list.map((u: any) => ({
      ...u,
      primaryRole: getPrimaryRole(u.roles || []),
    }));
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function updateRole(userId: number, role: string) {
  error.value = '';
  success.value = '';
  try {
    await api.patch(`/api/users/${userId}`, { roles: [role] });
    success.value = 'Role updated';
    await loadUsers();
    setTimeout(() => { success.value = ''; }, 2000);
  } catch (e: any) {
    error.value = e.message;
  }
}

async function setPassword(userId: number) {
  error.value = '';
  success.value = '';
  const pw = passwordInputs.value[userId];
  if (!pw) return;
  try {
    await api.patch(`/api/users/${userId}`, { plainPassword: pw });
    passwordInputs.value[userId] = '';
    success.value = 'Password updated';
    setTimeout(() => { success.value = ''; }, 2000);
  } catch (e: any) {
    error.value = e.message;
  }
}
</script>

<style scoped>
.search-input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}
.users-table {
  width: 100%;
  border-collapse: collapse;
}
.users-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  border-bottom: 2px solid #eee;
  font-size: 0.85rem;
  color: #666;
}
.users-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #eee;
  vertical-align: middle;
}
.users-table tr:last-child td {
  border-bottom: none;
}
.role-select {
  padding: 0.3rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.85rem;
}
.password-form {
  display: flex;
  gap: 0.4rem;
  align-items: center;
}
.password-input {
  width: 120px;
  padding: 0.3rem 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.85rem;
}
.btn-sm-action {
  padding: 0.3rem 0.6rem;
  font-size: 0.8rem;
  background: #1a73e8;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.btn-sm-action:hover {
  background: #1557b0;
}
.btn-sm-action:disabled {
  background: #ccc;
  cursor: not-allowed;
}
.date-cell {
  font-size: 0.85rem;
  color: #666;
}
.success {
  color: #2e7d32;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}
</style>
