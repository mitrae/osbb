<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">Apartments &amp; Residents</h1>

    <!-- Create apartment form -->
    <div v-if="showForm" class="card">
      <h2>Add Apartment</h2>
      <form @submit.prevent="createApartment">
        <div class="form-group">
          <label>Building</label>
          <select v-model="form.buildingId" required>
            <option value="" disabled>Select building</option>
            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.address }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Apartment Number</label>
          <input v-model="form.number" type="text" required />
        </div>
        <div class="form-group">
          <label>Total Area (m2)</label>
          <input v-model="form.totalArea" type="number" step="0.01" min="0.01" required />
        </div>
        <p v-if="formError" class="error">{{ formError }}</p>
        <div style="display:flex;gap:0.5rem">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Creating...' : 'Add Apartment' }}
          </button>
          <button type="button" class="btn" style="background:#e0e0e0" @click="showForm = false">Cancel</button>
        </div>
      </form>
    </div>

    <div class="page-header">
      <h2 style="margin:0">Apartment List</h2>
      <button class="btn btn-primary" @click="showForm = !showForm" v-if="!showForm">+ Add Apartment</button>
    </div>

    <div v-if="loading" class="card">Loading...</div>

    <div v-for="apt in apartments" :key="apt.id" class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <strong>Apt #{{ apt.number }}</strong>
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
              <div style="display:flex;gap:0.3rem">
                <button
                  class="btn"
                  style="background:#e0e0e0;font-size:0.75rem;padding:0.15rem 0.4rem"
                  @click="startEditResident(r)"
                >
                  Edit
                </button>
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

      <!-- Add resident form -->
      <div style="margin-top:0.5rem">
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

    <p v-if="!loading && apartments.length === 0" class="card">No apartments registered yet.</p>
    <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();
const apartments = ref<any[]>([]);
const buildings = ref<any[]>([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');
const formError = ref('');

const form = reactive({ buildingId: '', number: '', totalArea: '' });

// Edit resident state
const editingResidentId = ref<number | null>(null);
const editResident = reactive({ firstName: '', lastName: '', ownedArea: '' });

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
    const [bData, aData, rData] = await withOrgContext(() =>
      Promise.all([
        api.get<any>('/api/buildings'),
        api.get<any>('/api/apartments'),
        api.get<any>('/api/residents'),
      ])
    );

    buildings.value = bData['hydra:member'] || bData.member || [];
    const rawApts = aData['hydra:member'] || aData.member || [];
    const rawResidents = rData['hydra:member'] || rData.member || [];

    apartments.value = rawApts.map((a: any) => {
      const bldg = buildings.value.find((b: any) => b['@id'] === a.building || `/api/buildings/${b.id}` === a.building);
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
      })
    );
    form.buildingId = '';
    form.number = '';
    form.totalArea = '';
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
