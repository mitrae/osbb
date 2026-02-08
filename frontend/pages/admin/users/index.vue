<template>
  <div>
    <h1>User Management</h1>

    <div class="card" v-if="!loading">
      <div class="filters">
        <input
          v-model="search"
          type="text"
          placeholder="Search by name, email, or phone..."
          class="search-input"
        />
        <select v-model="roleFilter" class="role-filter">
          <option value="">All roles</option>
          <option value="ROLE_PLATFORM_ADMIN">Platform Admin</option>
          <option value="ROLE_ADMIN">Org Admin</option>
          <option value="ROLE_MANAGER">Org Manager</option>
          <option value="no_org">No organization</option>
        </select>
      </div>

      <table class="users-table" v-if="filtered.length > 0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Organizations</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in filtered" :key="u.id">
            <td>
              <NuxtLink :to="`/admin/users/${u.id}`" class="user-link">
                <strong>{{ u.firstName }} {{ u.lastName }}</strong>
              </NuxtLink>
            </td>
            <td>{{ u.email }}</td>
            <td>{{ u.phone || '—' }}</td>
            <td>
              <div v-if="u.memberships && u.memberships.length > 0" class="org-list">
                <span v-for="m in u.memberships" :key="m.id" class="org-item">
                  <NuxtLink :to="`/organizations/${m.organization.id}`" class="org-link">{{ m.organization.name }}</NuxtLink>
                  <span class="role-badge" :class="roleBadgeClass(m.role)">{{ formatRole(m.role) }}</span>
                </span>
              </div>
              <span v-else class="no-org">—</span>
            </td>
            <td class="date-cell">{{ formatDate(u.createdAt) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No users match your search.</p>
    </div>
    <div class="card" v-else>Loading...</div>

    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore();
const api = useApi();

const users = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const search = ref('');
const roleFilter = ref('');

// Guard: redirect non-platform-admins
onMounted(() => {
  if (!auth.isPlatformAdmin) {
    navigateTo('/');
    return;
  }
  loadUsers();
});

const filtered = computed(() => {
  let list = users.value;

  // Role filter
  if (roleFilter.value === 'no_org') {
    list = list.filter((u) => !u.memberships || u.memberships.length === 0);
  } else if (roleFilter.value === 'ROLE_PLATFORM_ADMIN') {
    list = list.filter((u) => (u.roles || []).includes('ROLE_PLATFORM_ADMIN'));
  } else if (roleFilter.value) {
    list = list.filter((u) =>
      (u.memberships || []).some((m: any) => m.role === roleFilter.value)
    );
  }

  // Text search
  const q = search.value.toLowerCase().trim();
  if (q) {
    list = list.filter((u) =>
      `${u.firstName} ${u.lastName}`.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q) ||
      (u.phone && u.phone.toLowerCase().includes(q))
    );
  }

  return list;
});

function formatDate(dateStr: string): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString();
}

function formatRole(role: string): string {
  return role.replace('ROLE_', '').toLowerCase();
}

function roleBadgeClass(role: string): string {
  if (role === 'ROLE_ADMIN') return 'admin';
  if (role === 'ROLE_MANAGER') return 'manager';
  return 'default';
}

async function loadUsers() {
  loading.value = true;
  error.value = '';
  try {
    const data = await api.get<any>('/api/users');
    const list = data['hydra:member'] || data['member'] || [];
    users.value = list;
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.filters {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.search-input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9rem;
}
.role-filter {
  padding: 0.5rem 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9rem;
  min-width: 160px;
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
.user-link {
  color: #1a73e8;
  text-decoration: none;
}
.user-link:hover {
  text-decoration: underline;
}
.org-list {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.org-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.org-link {
  color: #1a73e8;
  text-decoration: none;
  font-size: 0.85rem;
}
.org-link:hover {
  text-decoration: underline;
}
.role-badge {
  display: inline-block;
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: capitalize;
}
.role-badge.admin {
  background: #e3f2fd;
  color: #1565c0;
}
.role-badge.manager {
  background: #fff3e0;
  color: #e65100;
}
.role-badge.default {
  background: #f5f5f5;
  color: #666;
}
.no-org {
  color: #999;
}
.date-cell {
  font-size: 0.85rem;
  color: #666;
}
</style>
