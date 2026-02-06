<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">Apartments</h1>

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

      <!-- Ownerships for this apartment -->
      <div v-if="apt.ownerships.length > 0" style="margin-top:0.7rem;padding-top:0.7rem;border-top:1px solid #eee">
        <div v-for="o in apt.ownerships" :key="o.id" style="font-size:0.9rem;display:flex;justify-content:space-between;padding:0.2rem 0">
          <span>{{ o.userName }}</span>
          <span style="color:#666">{{ o.ownedArea }} m2</span>
        </div>
      </div>

      <!-- Assign ownership form -->
      <div style="margin-top:0.5rem">
        <button
          v-if="!apt.showOwnerForm"
          class="btn btn-sm"
          style="background:#e0e0e0;color:#333;border:none;font-size:0.8rem"
          @click="apt.showOwnerForm = true"
        >
          + Assign Owner
        </button>
        <div v-else style="margin-top:0.5rem;display:flex;gap:0.5rem;align-items:end">
          <div class="form-group" style="margin:0;flex:1">
            <label style="font-size:0.8rem">User ID</label>
            <input v-model="apt.newOwnerId" type="number" placeholder="User ID" style="padding:0.3rem" />
          </div>
          <div class="form-group" style="margin:0;flex:1">
            <label style="font-size:0.8rem">Owned Area (m2)</label>
            <input v-model="apt.newOwnerArea" type="number" step="0.01" placeholder="Area" style="padding:0.3rem" />
          </div>
          <button class="btn btn-primary" style="padding:0.3rem 0.7rem;font-size:0.85rem" @click="assignOwner(apt)">Assign</button>
          <button class="btn" style="background:#e0e0e0;padding:0.3rem 0.7rem;font-size:0.85rem" @click="apt.showOwnerForm = false">Cancel</button>
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

async function loadData() {
  loading.value = true;
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    const [bData, aData, oData] = await Promise.all([
      api.get<any>('/api/buildings'),
      api.get<any>('/api/apartments'),
      api.get<any>('/api/apartment_ownerships'),
    ]);

    buildings.value = bData['hydra:member'] || bData.member || [];
    const rawApts = aData['hydra:member'] || aData.member || [];
    const rawOwns = oData['hydra:member'] || oData.member || [];

    apartments.value = rawApts.map((a: any) => {
      const bldg = buildings.value.find((b: any) => b['@id'] === a.building || `/api/buildings/${b.id}` === a.building);
      const ownerships = rawOwns
        .filter((o: any) => {
          const aptRef = typeof o.apartment === 'string' ? o.apartment : o.apartment?.['@id'] || `/api/apartments/${o.apartment?.id}`;
          return aptRef === `/api/apartments/${a.id}` || aptRef === a['@id'];
        })
        .map((o: any) => ({
          id: o.id,
          userName: typeof o.user === 'string' ? o.user : `${o.user?.firstName || ''} ${o.user?.lastName || ''}`.trim() || `User #${o.user?.id}`,
          ownedArea: o.ownedArea,
        }));

      return {
        ...a,
        buildingAddress: bldg?.address || 'Unknown building',
        ownerships,
        showOwnerForm: false,
        newOwnerId: '',
        newOwnerArea: '',
      };
    });
  } catch {
    // handle error
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    loading.value = false;
  }
}

async function createApartment() {
  formError.value = '';
  submitting.value = true;
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    await api.post('/api/apartments', {
      building: `/api/buildings/${form.buildingId}`,
      number: form.number,
      totalArea: form.totalArea,
    });
    form.buildingId = '';
    form.number = '';
    form.totalArea = '';
    showForm.value = false;
    await loadData();
  } catch (e: any) {
    formError.value = e.message;
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    submitting.value = false;
  }
}

async function assignOwner(apt: any) {
  error.value = '';
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    await api.post('/api/apartment_ownerships', {
      apartment: `/api/apartments/${apt.id}`,
      user: `/api/users/${apt.newOwnerId}`,
      ownedArea: apt.newOwnerArea,
    });
    apt.showOwnerForm = false;
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
  }
}

onMounted(loadData);
</script>
