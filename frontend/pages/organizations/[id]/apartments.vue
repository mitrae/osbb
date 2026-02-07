<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">Properties &amp; Residents</h1>

    <!-- Create unit form (platform admin only) -->
    <div v-if="showForm && isPlatformAdmin" class="card">
      <h2>Add Unit</h2>
      <form @submit.prevent="createApartment">
        <div class="form-group">
          <label>Type</label>
          <select v-model="form.type">
            <option value="apartment">Apartment</option>
            <option value="parking">Parking</option>
          </select>
        </div>
        <div class="form-group">
          <label>Building</label>
          <select v-model="form.buildingId" required>
            <option value="" disabled>Select building</option>
            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.address }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>{{ form.type === 'parking' ? 'Spot #' : 'Apartment #' }}</label>
          <input v-model="form.number" type="text" required />
        </div>
        <div class="form-group">
          <label>Total Area (m2)</label>
          <input v-model="form.totalArea" type="number" step="0.01" min="0.01" required />
        </div>
        <p v-if="formError" class="error">{{ formError }}</p>
        <div style="display:flex;gap:0.5rem">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Creating...' : 'Add Unit' }}
          </button>
          <button type="button" class="btn" style="background:#e0e0e0" @click="showForm = false">Cancel</button>
        </div>
      </form>
    </div>

    <div class="page-header">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <h2 style="margin:0">Property List</h2>
        <div style="display:flex;gap:0.3rem">
          <button class="btn" :class="typeFilter === '' ? 'btn-primary' : ''" style="padding:0.25rem 0.6rem;font-size:0.85rem" :style="typeFilter !== '' ? 'background:#e0e0e0' : ''" @click="typeFilter = ''; loadData()">All</button>
          <button class="btn" :class="typeFilter === 'apartment' ? 'btn-primary' : ''" style="padding:0.25rem 0.6rem;font-size:0.85rem" :style="typeFilter !== 'apartment' ? 'background:#e0e0e0' : ''" @click="typeFilter = 'apartment'; loadData()">Apartments</button>
          <button class="btn" :class="typeFilter === 'parking' ? 'btn-primary' : ''" style="padding:0.25rem 0.6rem;font-size:0.85rem" :style="typeFilter !== 'parking' ? 'background:#e0e0e0' : ''" @click="typeFilter = 'parking'; loadData()">Parkings</button>
        </div>
      </div>
      <button class="btn btn-primary" @click="showForm = !showForm" v-if="!showForm && isPlatformAdmin">+ Add Unit</button>
    </div>

    <div style="margin-bottom:1rem">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search by apartment #, building address, or resident name..."
        style="width:100%;padding:0.5rem 0.75rem;border:1px solid #ddd;border-radius:6px;font-size:0.9rem"
      />
    </div>

    <div v-if="loading" class="card">Loading...</div>

    <div v-if="!loading && filteredApartments.length > 0" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;font-size:0.85rem;color:#666">
      <span>Showing {{ paginationStart + 1 }}–{{ paginationEnd }} of {{ filteredApartments.length }} properties</span>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <button class="btn" style="padding:0.2rem 0.5rem;font-size:0.8rem;background:#e0e0e0" :disabled="currentPage === 1" @click="currentPage--">&laquo; Prev</button>
        <span>Page {{ currentPage }} / {{ totalPages }}</span>
        <button class="btn" style="padding:0.2rem 0.5rem;font-size:0.8rem;background:#e0e0e0" :disabled="currentPage >= totalPages" @click="currentPage++">Next &raquo;</button>
      </div>
    </div>

    <div v-for="apt in paginatedApartments" :key="apt.id" class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <strong>{{ apt.type === 'parking' ? 'Spot' : 'Apt' }} #{{ apt.number }}</strong>
          <span class="badge" :style="apt.type === 'parking' ? 'background:#ff9800;color:#fff' : 'background:#1a73e8;color:#fff'" style="margin-left:0.5rem;font-size:0.75rem;padding:0.1rem 0.4rem;border-radius:3px">{{ apt.type === 'parking' ? 'Parking' : 'Apartment' }}</span>
          <span style="margin-left:0.5rem;color:#666;font-size:0.9rem">{{ apt.totalArea }} m2</span>
          <span style="display:block;font-size:0.85rem;color:#999">{{ apt.buildingAddress }}</span>
        </div>
      </div>

      <!-- Residents for this apartment -->
      <div v-if="apt.residents.length > 0" style="margin-top:0.7rem;padding-top:0.7rem;border-top:1px solid #eee">
        <div v-for="r in apt.residents" :key="r.id" style="padding:0.3rem 0">
          <!-- Display mode -->
          <template v-if="editingResidentId !== r.id">
            <div style="font-size:0.9rem;display:flex;justify-content:space-between;align-items:center">
              <div>
                <span>{{ r.firstName }} {{ r.lastName }}</span>
                <span style="color:#666;margin-left:0.5rem">{{ r.ownedArea }} m2</span>
                <span v-if="r.userEmail" style="color:#1a73e8;margin-left:0.5rem;font-size:0.85rem">{{ r.userEmail }}</span>
              </div>
              <div style="display:flex;gap:0.3rem;align-items:center">
                <button
                  v-if="isPlatformAdmin"
                  class="btn"
                  style="background:#e0e0e0;font-size:0.75rem;padding:0.15rem 0.4rem"
                  @click="startEditResident(r)"
                >
                  Edit
                </button>
                <template v-if="isPlatformAdmin && !r.userEmail">
                  <input
                    v-model="linkInputs[r.id]"
                    type="email"
                    placeholder="user@email"
                    class="link-input"
                  />
                  <button
                    class="btn"
                    style="background:#2e7d32;color:#fff;font-size:0.75rem;padding:0.15rem 0.4rem"
                    :disabled="!linkInputs[r.id]"
                    @click="linkUser(r.id)"
                  >
                    Link
                  </button>
                </template>
                <button
                  v-if="r.userEmail"
                  class="btn btn-danger"
                  style="font-size:0.75rem;padding:0.15rem 0.4rem"
                  @click="disconnectResident(r.id)"
                >
                  Disconnect
                </button>
              </div>
            </div>
          </template>

          <!-- Edit mode -->
          <template v-else>
            <div style="font-size:0.9rem;padding:0.3rem 0">
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end">
                <div class="form-group" style="margin:0;flex:1;min-width:100px">
                  <label style="font-size:0.8rem">First Name</label>
                  <input v-model="editResident.firstName" type="text" style="padding:0.3rem" />
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:100px">
                  <label style="font-size:0.8rem">Last Name</label>
                  <input v-model="editResident.lastName" type="text" style="padding:0.3rem" />
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:80px">
                  <label style="font-size:0.8rem">Owned Area (m2)</label>
                  <input v-model="editResident.ownedArea" type="number" step="0.01" style="padding:0.3rem" />
                </div>
              </div>
              <div style="display:flex;gap:0.5rem;margin-top:0.5rem">
                <button class="btn btn-primary" style="padding:0.3rem 0.7rem;font-size:0.85rem" @click="saveResident(r.id)">Save</button>
                <button class="btn" style="background:#e0e0e0;padding:0.3rem 0.7rem;font-size:0.85rem" @click="editingResidentId = null">Cancel</button>
                <button class="btn btn-danger" style="padding:0.3rem 0.7rem;font-size:0.85rem;margin-left:auto" @click="deleteResident(r.id)">Delete</button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Add resident form (platform admin only) -->
      <div v-if="isPlatformAdmin" style="margin-top:0.5rem">
        <button
          v-if="!apt.showResidentForm"
          class="btn btn-sm"
          style="background:#e0e0e0;color:#333;border:none;font-size:0.8rem"
          @click="apt.showResidentForm = true"
        >
          + Add Resident
        </button>
        <div v-else style="margin-top:0.5rem">
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end">
            <div class="form-group" style="margin:0;flex:1;min-width:100px">
              <label style="font-size:0.8rem">First Name</label>
              <input v-model="apt.newResident.firstName" type="text" placeholder="First name" style="padding:0.3rem" />
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:100px">
              <label style="font-size:0.8rem">Last Name</label>
              <input v-model="apt.newResident.lastName" type="text" placeholder="Last name" style="padding:0.3rem" />
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:80px">
              <label style="font-size:0.8rem">Owned Area (m2)</label>
              <input v-model="apt.newResident.ownedArea" type="number" step="0.01" placeholder="Area" style="padding:0.3rem" />
            </div>
          </div>
          <div style="display:flex;gap:0.5rem;margin-top:0.5rem">
            <button class="btn btn-primary" style="padding:0.3rem 0.7rem;font-size:0.85rem" @click="addResident(apt)">Add</button>
            <button class="btn" style="background:#e0e0e0;padding:0.3rem 0.7rem;font-size:0.85rem" @click="apt.showResidentForm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!loading && filteredApartments.length > 0 && totalPages > 1" style="display:flex;justify-content:flex-end;align-items:center;gap:0.5rem;margin-top:0.5rem;font-size:0.85rem;color:#666">
      <button class="btn" style="padding:0.2rem 0.5rem;font-size:0.8rem;background:#e0e0e0" :disabled="currentPage === 1" @click="currentPage--">&laquo; Prev</button>
      <span>Page {{ currentPage }} / {{ totalPages }}</span>
      <button class="btn" style="padding:0.2rem 0.5rem;font-size:0.8rem;background:#e0e0e0" :disabled="currentPage >= totalPages" @click="currentPage++">Next &raquo;</button>
    </div>

    <p v-if="!loading && apartments.length === 0" class="card">No properties registered yet.</p>
    <p v-if="!loading && apartments.length > 0 && filteredApartments.length === 0" class="card">No properties match your search.</p>
    <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();

const isPlatformAdmin = computed(() => auth.isPlatformAdmin);
const apartments = ref<any[]>([]);
const buildings = ref<any[]>([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');
const formError = ref('');

const typeFilter = ref('');
const searchQuery = ref('');
const currentPage = ref(1);
const pageSize = 20;
const form = reactive({ buildingId: '', number: '', totalArea: '', type: 'apartment' });

const filteredApartments = computed(() => {
  const q = searchQuery.value.toLowerCase().trim();
  if (!q) return apartments.value;
  return apartments.value.filter((apt: any) => {
    if (apt.number?.toLowerCase().includes(q)) return true;
    if (apt.buildingAddress?.toLowerCase().includes(q)) return true;
    if (apt.residents?.some((r: any) =>
      `${r.firstName} ${r.lastName}`.toLowerCase().includes(q)
    )) return true;
    return false;
  });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredApartments.value.length / pageSize)));
const paginationStart = computed(() => (currentPage.value - 1) * pageSize);
const paginationEnd = computed(() => Math.min(paginationStart.value + pageSize, filteredApartments.value.length));
const paginatedApartments = computed(() => filteredApartments.value.slice(paginationStart.value, paginationEnd.value));

watch(searchQuery, () => { currentPage.value = 1; });
watch(typeFilter, () => { currentPage.value = 1; });

// Edit resident state
const editingResidentId = ref<number | null>(null);
const editResident = reactive({ firstName: '', lastName: '', ownedArea: '' });

// Link user state (platform admin)
const linkInputs = ref<Record<number, string>>({});
const allUsers = ref<any[]>([]);

const orgId = computed(() => Number(route.params.id));

function withOrgContext<T>(fn: () => Promise<T>): Promise<T> {
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId.value);
  return fn().finally(() => {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
  });
}

async function loadData() {
  loading.value = true;
  try {
    const aptParams = new URLSearchParams({ pagination: 'false' });
    if (typeFilter.value) aptParams.set('type', typeFilter.value);
    const aptUrl = `/api/apartments?${aptParams}`;
    const fetches: Promise<any>[] = [
      withOrgContext(() => api.get<any>('/api/buildings?pagination=false')),
      withOrgContext(() => api.get<any>(aptUrl)),
      withOrgContext(() => api.get<any>('/api/residents?pagination=false')),
    ];
    if (isPlatformAdmin.value) {
      fetches.push(api.get<any>('/api/users'));
    }
    const results = await Promise.all(fetches);
    const [bData, aData, rData] = results;
    if (isPlatformAdmin.value && results[3]) {
      const uList = results[3]['hydra:member'] || results[3].member || (Array.isArray(results[3]) ? results[3] : []);
      allUsers.value = uList;
    }

    buildings.value = bData['hydra:member'] || bData.member || [];
    const rawApts = aData['hydra:member'] || aData.member || [];
    const rawResidents = rData['hydra:member'] || rData.member || [];

    apartments.value = rawApts.map((a: any) => {
      const buildingRef = typeof a.building === 'string' ? a.building : a.building?.['@id'];
      const bldg = a.building?.address ? a.building : buildings.value.find((b: any) => b['@id'] === buildingRef || `/api/buildings/${b.id}` === buildingRef);
      const residents = rawResidents
        .filter((r: any) => {
          const aptRef = typeof r.apartment === 'string' ? r.apartment : r.apartment?.['@id'] || `/api/apartments/${r.apartment?.id}`;
          return aptRef === `/api/apartments/${a.id}` || aptRef === a['@id'];
        })
        .map((r: any) => ({
          id: r.id,
          firstName: r.firstName,
          lastName: r.lastName,
          ownedArea: r.ownedArea,
          userEmail: typeof r.user === 'string' ? r.user : r.user?.email || null,
        }));

      return {
        ...a,
        buildingAddress: bldg?.address || 'Unknown building',
        residents,
        showResidentForm: false,
        newResident: { firstName: '', lastName: '', ownedArea: '' },
      };
    });
  } catch {
    // handle error
  } finally {
    loading.value = false;
  }
}

async function createApartment() {
  formError.value = '';
  submitting.value = true;
  try {
    await withOrgContext(() =>
      api.post('/api/apartments', {
        building: `/api/buildings/${form.buildingId}`,
        number: form.number,
        totalArea: form.totalArea,
        type: form.type,
      })
    );
    form.buildingId = '';
    form.number = '';
    form.totalArea = '';
    form.type = 'apartment';
    showForm.value = false;
    await loadData();
  } catch (e: any) {
    formError.value = e.message;
  } finally {
    submitting.value = false;
  }
}

async function addResident(apt: any) {
  error.value = '';
  try {
    await withOrgContext(() =>
      api.post('/api/residents', {
        apartment: `/api/apartments/${apt.id}`,
        firstName: apt.newResident.firstName,
        lastName: apt.newResident.lastName,
        ownedArea: apt.newResident.ownedArea,
      })
    );
    apt.showResidentForm = false;
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

function startEditResident(r: any) {
  editingResidentId.value = r.id;
  editResident.firstName = r.firstName;
  editResident.lastName = r.lastName;
  editResident.ownedArea = r.ownedArea;
}

async function saveResident(residentId: number) {
  error.value = '';
  try {
    await withOrgContext(() =>
      api.patch(`/api/residents/${residentId}`, {
        firstName: editResident.firstName,
        lastName: editResident.lastName,
        ownedArea: editResident.ownedArea,
      })
    );
    editingResidentId.value = null;
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function deleteResident(residentId: number) {
  if (!confirm('Delete this resident? This cannot be undone.')) return;
  error.value = '';
  try {
    await withOrgContext(() => api.delete(`/api/residents/${residentId}`));
    editingResidentId.value = null;
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function linkUser(residentId: number) {
  error.value = '';
  const email = linkInputs.value[residentId]?.trim();
  if (!email) return;
  const user = allUsers.value.find((u: any) => u.email === email);
  if (!user) {
    error.value = `User not found: ${email}`;
    return;
  }
  try {
    await withOrgContext(() =>
      api.patch(`/api/residents/${residentId}`, { user: `/api/users/${user.id}` })
    );
    linkInputs.value[residentId] = '';
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function disconnectResident(residentId: number) {
  error.value = '';
  try {
    await withOrgContext(() => api.patch(`/api/residents/${residentId}`, { user: null }));
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(loadData);
</script>

<style scoped>
.link-input {
  width: 140px;
  padding: 0.15rem 0.4rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.8rem;
}
</style>
