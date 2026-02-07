<template>
  <div>
    <h1>My Profile</h1>

    <div v-if="loading" class="card">Loading...</div>

    <template v-else>
      <div class="card">
        <h2>Personal Information</h2>
        <form @submit.prevent="saveProfile">
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
            <input v-model="form.phone" type="tel" placeholder="+380..." />
          </div>
          <p v-if="profileError" class="error">{{ profileError }}</p>
          <p v-if="profileSuccess" style="color:#2e7d32;font-size:0.9rem">Saved successfully.</p>
          <button type="submit" class="btn btn-primary" :disabled="profileSaving">
            {{ profileSaving ? 'Saving...' : 'Save Changes' }}
          </button>
        </form>
      </div>

      <div class="card" v-if="residents.length > 0">
        <h2>My Residencies</h2>
        <table class="residencies-table">
          <thead>
            <tr>
              <th>Organization</th>
              <th>Building</th>
              <th>Apartment</th>
              <th>Owned Area</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in residents" :key="r.id">
              <td>
                <NuxtLink :to="`/organizations/${r.orgId}`" style="color:#1a73e8;text-decoration:none">{{ r.orgName }}</NuxtLink>
              </td>
              <td>{{ r.buildingAddress }}</td>
              <td>{{ r.aptNumber }}{{ r.aptType !== 'apartment' ? ` (${r.aptType})` : '' }}</td>
              <td>{{ r.ownedArea }} m&sup2;</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>Change Password</h2>
        <form @submit.prevent="changePassword">
          <div class="form-group">
            <label>New Password</label>
            <input v-model="pw.newPassword" type="password" required minlength="6" />
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input v-model="pw.confirmPassword" type="password" required minlength="6" />
          </div>
          <p v-if="pwError" class="error">{{ pwError }}</p>
          <p v-if="pwSuccess" style="color:#2e7d32;font-size:0.9rem">Password changed successfully.</p>
          <button type="submit" class="btn btn-primary" :disabled="pwSaving">
            {{ pwSaving ? 'Saving...' : 'Change Password' }}
          </button>
        </form>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const auth = useAuthStore();

const loading = ref(true);
const form = reactive({ firstName: '', lastName: '', email: '', phone: '' });
const profileSaving = ref(false);
const profileError = ref('');
const profileSuccess = ref(false);

const residents = ref<any[]>([]);

const pw = reactive({ newPassword: '', confirmPassword: '' });
const pwSaving = ref(false);
const pwError = ref('');
const pwSuccess = ref(false);

async function saveProfile() {
  profileError.value = '';
  profileSuccess.value = false;
  profileSaving.value = true;
  try {
    const data = await api.patch<any>(`/api/users/${auth.user!.id}`, {
      firstName: form.firstName,
      lastName: form.lastName,
      email: form.email,
      phone: form.phone || null,
    });
    // Update local auth state
    if (auth.user) {
      auth.user.firstName = data.firstName;
      auth.user.lastName = data.lastName;
      auth.user.email = data.email;
      if (import.meta.client) {
        localStorage.setItem('auth_user', JSON.stringify(auth.user));
      }
    }
    profileSuccess.value = true;
  } catch (e: any) {
    profileError.value = e.message;
  } finally {
    profileSaving.value = false;
  }
}

async function changePassword() {
  pwError.value = '';
  pwSuccess.value = false;
  if (pw.newPassword !== pw.confirmPassword) {
    pwError.value = 'Passwords do not match.';
    return;
  }
  pwSaving.value = true;
  try {
    await api.patch(`/api/users/${auth.user!.id}`, {
      plainPassword: pw.newPassword,
    });
    pw.newPassword = '';
    pw.confirmPassword = '';
    pwSuccess.value = true;
  } catch (e: any) {
    pwError.value = e.message;
  } finally {
    pwSaving.value = false;
  }
}

async function loadResidents() {
  try {
    const data = await api.get<any>(`/api/residents?user=/api/users/${auth.user!.id}`);
    const items = data['hydra:member'] || data['member'] || (Array.isArray(data) ? data : []);
    residents.value = items.map((r: any) => {
      const apt = r.apartment || {};
      const bld = apt.building || {};
      const org = bld.organization || {};
      return {
        id: r.id,
        orgId: org.id,
        orgName: org.name || '—',
        buildingAddress: bld.address || '—',
        aptNumber: apt.number || '—',
        aptType: apt.type || 'apartment',
        ownedArea: r.ownedArea,
      };
    });
  } catch {
    // ignore — residents section just won't show
  }
}

onMounted(async () => {
  try {
    const data = await api.get<any>(`/api/users/${auth.user!.id}`);
    form.firstName = data.firstName || '';
    form.lastName = data.lastName || '';
    form.email = data.email || '';
    form.phone = data.phone || '';
  } catch {
    // Fall back to local auth data
    form.firstName = auth.user?.firstName || '';
    form.lastName = auth.user?.lastName || '';
    form.email = auth.user?.email || '';
  } finally {
    loading.value = false;
  }
  loadResidents();
});
</script>

<style scoped>
.residencies-table {
  width: 100%;
  border-collapse: collapse;
}
.residencies-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  border-bottom: 2px solid #eee;
  font-size: 0.85rem;
  color: #666;
}
.residencies-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #eee;
}
.residencies-table tr:last-child td {
  border-bottom: none;
}
</style>
