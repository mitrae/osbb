<template>
  <div>
    <div class="page-header">
      <h1>Organizations</h1>
      <button v-if="auth.isPlatformAdmin" class="btn btn-primary" @click="showCreate = !showCreate">
        {{ showCreate ? 'Cancel' : '+ New Organization' }}
      </button>
    </div>

    <!-- Create organization form (platform admin only) -->
    <div v-if="showCreate" class="card">
      <h2>Create Organization</h2>
      <form @submit.prevent="createOrg">
        <div class="form-group">
          <label>Name</label>
          <input v-model="newOrg.name" type="text" required />
        </div>
        <div class="form-group">
          <label>City</label>
          <input v-model="newOrg.city" type="text" placeholder="e.g. Kyiv" />
        </div>
        <div class="form-group">
          <label>Address</label>
          <input v-model="newOrg.address" type="text" required />
        </div>
        <p v-if="createError" class="error">{{ createError }}</p>
        <button type="submit" class="btn btn-primary" :disabled="creating">
          {{ creating ? 'Creating...' : 'Create Organization' }}
        </button>
      </form>
    </div>

    <!-- My organizations (memberships + resident links) -->
    <div v-if="org.allOrgs.length > 0" class="card">
      <h2>My Organizations</h2>
      <div v-for="o in org.allOrgs" :key="o.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <NuxtLink :to="`/organizations/${o.id}`" style="text-decoration:none;color:inherit">
              <strong>{{ o.name || `Org #${o.id}` }}</strong>
            </NuxtLink>
            <span v-if="o.role" style="margin-left:0.5rem;font-size:0.85rem;color:#666">{{ o.role.replace('ROLE_', '') }}</span>
            <span v-else style="margin-left:0.5rem;font-size:0.85rem;color:#666">Resident</span>
          </div>
          <span class="badge badge-approved">{{ o.source === 'membership' ? 'Member' : 'Resident' }}</span>
        </div>
      </div>
    </div>

    <!-- My Connection Requests -->
    <div v-if="!auth.isPlatformAdmin && myRequests.length > 0" class="card">
      <h2>My Connection Requests</h2>
      <div v-for="cr in myRequests" :key="cr.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <strong>{{ cr.orgName }}</strong>
            <span style="display:block;font-size:0.85rem;color:#666">{{ cr.buildingAddress }} &mdash; {{ cr.aptLabel }}</span>
            <small style="color:#999">{{ new Date(cr.createdAt).toLocaleDateString() }}</small>
          </div>
          <span :class="`badge badge-${cr.status}`">{{ cr.status }}</span>
        </div>
      </div>
    </div>

    <!-- Apply for Residency (regular users) -->
    <div v-if="!auth.isPlatformAdmin" class="card" style="margin-top:1.5rem">
      <div v-if="!showApply" style="text-align:center;padding:1rem 0">
        <p style="color:#666;margin-bottom:1rem">Want to connect to an organization as a resident?</p>
        <button class="btn btn-primary" @click="showApply = true">Apply for Residency</button>
      </div>

      <template v-if="showApply">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
          <h2 style="margin:0">Apply for Residency</h2>
          <button class="btn btn-sm" style="background:#e0e0e0;color:#333" @click="showApply = false">Cancel</button>
        </div>

        <div v-if="applySubmitted" style="text-align:center;padding:1rem 0">
          <h3 style="color:#2e7d32">Request Submitted</h3>
          <p style="color:#666;margin:0.5rem 0">Your request is pending review by the organization administrator.</p>
          <button class="btn btn-primary" style="margin-top:1rem" @click="applySubmitted = false; showApply = false">OK</button>
        </div>

        <form v-else @submit.prevent="submitApply">
          <div class="form-group">
            <label>Organization</label>
            <select v-model="apply.organizationId" required @change="onApplyOrgChange">
              <option value="" disabled>Select organization</option>
              <option v-for="o in availableOrgs" :key="o.id" :value="o.id">
                {{ o.name }}{{ o.city ? ` (${o.city})` : '' }}
              </option>
            </select>
          </div>

          <div class="form-group autocomplete-wrap">
            <label>Building</label>
            <div v-if="selectedBuilding" class="selected-tag">
              {{ selectedBuilding.address }}
              <button type="button" @click="clearBuilding">&times;</button>
            </div>
            <template v-else>
              <input
                v-model="buildingSearch"
                type="text"
                :placeholder="apply.organizationId ? 'Type to search building address...' : 'Select organization first'"
                :disabled="!apply.organizationId"
                autocomplete="off"
                @input="onBuildingInput"
                @focus="onBuildingFocus"
              />
              <ul v-if="buildingDropdownOpen && filteredBuildings.length > 0" class="autocomplete-dropdown">
                <li v-for="b in filteredBuildings" :key="b.id" @mousedown.prevent="selectBuilding(b)">{{ b.address }}</li>
              </ul>
              <div v-if="buildingDropdownOpen && buildingSearch && filteredBuildings.length === 0 && !buildingLoading" class="autocomplete-dropdown" style="padding:0.5rem;color:#999;font-size:0.85rem">
                No buildings found
              </div>
            </template>
          </div>

          <div class="form-group autocomplete-wrap">
            <label>Apartment</label>
            <div v-if="selectedApartment" class="selected-tag">
              {{ selectedApartment.type === 'parking' ? 'Parking' : 'Apt' }} #{{ selectedApartment.number }} ({{ selectedApartment.totalArea }} m&sup2;)
              <button type="button" @click="clearApartment">&times;</button>
            </div>
            <template v-else>
              <input
                v-model="aptSearch"
                type="text"
                :placeholder="apply.buildingId ? 'Type apartment number...' : 'Select building first'"
                :disabled="!apply.buildingId"
                autocomplete="off"
                @input="onAptInput"
                @focus="onAptFocus"
              />
              <ul v-if="aptDropdownOpen && filteredApartments.length > 0" class="autocomplete-dropdown">
                <li v-for="a in filteredApartments" :key="a.id" @mousedown.prevent="selectApartment(a)">
                  {{ a.type === 'parking' ? 'Parking' : 'Apt' }} #{{ a.number }} ({{ a.totalArea }} m&sup2;)
                </li>
                <li v-if="!aptSearch && filteredApartments.length >= 20" class="autocomplete-hint">Type number to narrow results...</li>
              </ul>
              <div v-if="aptDropdownOpen && aptSearch && filteredApartments.length === 0 && !aptLoading" class="autocomplete-dropdown" style="padding:0.5rem;color:#999;font-size:0.85rem">
                No apartments found
              </div>
            </template>
          </div>

          <div class="form-group">
            <label>Full Name</label>
            <input v-model="apply.fullName" type="text" required placeholder="Your full name as in resident records" />
          </div>

          <div class="form-group">
            <label>Phone</label>
            <input v-model="apply.phone" type="tel" required placeholder="+380..." />
          </div>

          <p v-if="applyError" class="error">{{ applyError }}</p>
          <button type="submit" class="btn btn-primary" :disabled="applySubmitting">
            {{ applySubmitting ? 'Submitting...' : 'Submit Request' }}
          </button>
        </form>
      </template>
    </div>

    <!-- All Organizations (platform admin only) -->
    <div v-if="auth.isPlatformAdmin" class="card" style="margin-top:1.5rem">
      <h2>All Organizations</h2>
      <div v-if="loading">Loading...</div>
      <div v-for="o in allOrganizations" :key="o.id" class="list-item">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <NuxtLink :to="`/organizations/${o.id}`" style="text-decoration:none;color:inherit">
              <strong>{{ o.name }}</strong>
            </NuxtLink>
            <span v-if="o.city" style="margin-left:0.5rem;font-size:0.85rem;color:#999">{{ o.city }}</span>
            <span style="display:block;font-size:0.85rem;color:#666">{{ o.address }}</span>
          </div>
        </div>
      </div>
      <p v-if="!loading && allOrganizations.length === 0">No organizations available.</p>
    </div>

    <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const api = useApi();
const auth = useAuthStore();
const org = useOrganizationStore();
const allOrganizations = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const showCreate = ref(false);
const creating = ref(false);
const createError = ref('');
const newOrg = reactive({ name: '', city: '', address: '' });

const myRequests = ref<any[]>([]);

const connectedOrgIds = computed(() => new Set(org.allOrgs.map(o => o.id)));
const availableOrgs = computed(() => allOrganizations.value.filter(o => !connectedOrgIds.value.has(o.id)));

// Apply for residency
const showApply = ref(false);
const applySubmitting = ref(false);
const applySubmitted = ref(false);
const applyError = ref('');
const apply = reactive({
  organizationId: '',
  buildingId: '',
  apartmentId: '',
  fullName: '',
  phone: '',
});

// Building autocomplete
const buildingSearch = ref('');
const buildingDropdownOpen = ref(false);
const buildingLoading = ref(false);
const buildingResults = ref<any[]>([]);
const selectedBuilding = ref<any>(null);
let buildingDebounce: ReturnType<typeof setTimeout> | null = null;

const filteredBuildings = computed(() => buildingResults.value);

function onBuildingFocus() {
  buildingDropdownOpen.value = true;
  if (buildingResults.value.length === 0) fetchBuildings();
}

function onBuildingInput() {
  if (selectedBuilding.value) return;
  if (buildingDebounce) clearTimeout(buildingDebounce);
  buildingDebounce = setTimeout(() => fetchBuildings(), 300);
}

async function fetchBuildings() {
  if (!apply.organizationId) return;
  buildingLoading.value = true;
  try {
    const savedOrg = org.currentOrgId;
    org.setCurrentOrg(Number(apply.organizationId));
    const params = new URLSearchParams({ pagination: 'false' });
    if (buildingSearch.value.trim()) params.set('address', buildingSearch.value.trim());
    const data = await api.get<any>(`/api/buildings?${params}`);
    buildingResults.value = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
    if (savedOrg) org.setCurrentOrg(savedOrg);
  } catch {
    buildingResults.value = [];
  } finally {
    buildingLoading.value = false;
  }
}

function selectBuilding(b: any) {
  selectedBuilding.value = b;
  apply.buildingId = b.id;
  buildingSearch.value = '';
  buildingDropdownOpen.value = false;
  // Clear apartment selection
  clearApartment();
  // Pre-load apartments for this building
  fetchApartments();
}

function clearBuilding() {
  selectedBuilding.value = null;
  apply.buildingId = '';
  buildingSearch.value = '';
  buildingResults.value = [];
  clearApartment();
}

// Apartment autocomplete
const aptSearch = ref('');
const aptDropdownOpen = ref(false);
const aptLoading = ref(false);
const aptResults = ref<any[]>([]);
const selectedApartment = ref<any>(null);
let aptDebounce: ReturnType<typeof setTimeout> | null = null;

const filteredApartments = computed(() => aptResults.value);

function onAptFocus() {
  aptDropdownOpen.value = true;
  if (aptResults.value.length === 0 && apply.buildingId) fetchApartments();
}

function onAptInput() {
  if (selectedApartment.value) return;
  if (aptDebounce) clearTimeout(aptDebounce);
  aptDebounce = setTimeout(() => fetchApartments(), 300);
}

async function fetchApartments() {
  if (!apply.buildingId) return;
  aptLoading.value = true;
  try {
    const savedOrg = org.currentOrgId;
    org.setCurrentOrg(Number(apply.organizationId));
    const params = new URLSearchParams({
      'building': `/api/buildings/${apply.buildingId}`,
    });
    if (aptSearch.value.trim()) {
      params.set('number', aptSearch.value.trim());
      params.set('pagination', 'false');
    } else {
      params.set('itemsPerPage', '20');
    }
    const data = await api.get<any>(`/api/apartments?${params}`);
    aptResults.value = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
    if (savedOrg) org.setCurrentOrg(savedOrg);
  } catch {
    aptResults.value = [];
  } finally {
    aptLoading.value = false;
  }
}

function selectApartment(a: any) {
  selectedApartment.value = a;
  apply.apartmentId = a.id;
  aptSearch.value = '';
  aptDropdownOpen.value = false;
}

function clearApartment() {
  selectedApartment.value = null;
  apply.apartmentId = '';
  aptSearch.value = '';
  aptResults.value = [];
}

async function onApplyOrgChange() {
  apply.buildingId = '';
  apply.apartmentId = '';
  clearBuilding();
  if (!apply.organizationId) return;
  // Pre-load buildings for the selected org
  await fetchBuildings();
  if (buildingResults.value.length === 1) {
    selectBuilding(buildingResults.value[0]);
  }
}

async function submitApply() {
  applyError.value = '';
  if (!apply.buildingId || !apply.apartmentId) {
    applyError.value = 'Please select a building and apartment.';
    return;
  }
  applySubmitting.value = true;
  try {
    await api.post('/api/connection_requests', {
      organization: `/api/organizations/${apply.organizationId}`,
      building: `/api/buildings/${apply.buildingId}`,
      apartment: `/api/apartments/${apply.apartmentId}`,
      fullName: apply.fullName,
      phone: apply.phone,
    });
    applySubmitted.value = true;
    apply.organizationId = '';
    apply.fullName = '';
    apply.phone = '';
    clearBuilding();
    await loadMyRequests();
  } catch (e: any) {
    applyError.value = e.message;
  } finally {
    applySubmitting.value = false;
  }
}

async function createOrg() {
  createError.value = '';
  creating.value = true;
  try {
    const body: any = { name: newOrg.name, address: newOrg.address };
    if (newOrg.city) body.city = newOrg.city;
    await api.post('/api/organizations', body);
    newOrg.name = '';
    newOrg.city = '';
    newOrg.address = '';
    showCreate.value = false;
    const data = await api.get<any>('/api/organizations');
    allOrganizations.value = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
  } catch (e: any) {
    createError.value = e.message;
  } finally {
    creating.value = false;
  }
}

function closeDropdowns(e: Event) {
  const target = e.target as HTMLElement;
  if (!target.closest('.autocomplete-wrap')) {
    buildingDropdownOpen.value = false;
    aptDropdownOpen.value = false;
  }
}

async function loadMyRequests() {
  if (auth.isPlatformAdmin) return;
  try {
    const data = await api.get<any>('/api/connection_requests');
    const items = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
    myRequests.value = items.map((cr: any) => {
      const orgObj = typeof cr.organization === 'object' ? cr.organization : null;
      const bldObj = typeof cr.building === 'object' ? cr.building : null;
      const aptObj = typeof cr.apartment === 'object' ? cr.apartment : null;
      const aptType = aptObj?.type || 'apartment';
      return {
        id: cr.id,
        status: cr.status,
        createdAt: cr.createdAt,
        orgName: orgObj?.name || 'Organization',
        buildingAddress: bldObj?.address || '',
        aptLabel: `${aptType === 'parking' ? 'Parking' : 'Apt'} #${aptObj?.number || '?'}`,
      };
    });
  } catch {
    // ignore
  }
}

onMounted(async () => {
  document.addEventListener('click', closeDropdowns);
  try {
    const [orgData] = await Promise.all([
      api.get<any>('/api/organizations'),
      loadMyRequests(),
    ]);
    allOrganizations.value = Array.isArray(orgData) ? orgData : (orgData['hydra:member'] || orgData.member || []);
  } catch {
    // ignore
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  document.removeEventListener('click', closeDropdowns);
});
</script>

<style scoped>
.list-item {
  padding: 0.7rem 0;
  border-bottom: 1px solid #eee;
}
.list-item:last-child {
  border-bottom: none;
}
.autocomplete-wrap {
  position: relative;
}
.autocomplete-dropdown {
  position: absolute;
  z-index: 10;
  left: 0;
  right: 0;
  max-height: 200px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #ddd;
  border-top: none;
  border-radius: 0 0 6px 6px;
  list-style: none;
  margin: 0;
  padding: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.autocomplete-dropdown li {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  font-size: 0.9rem;
}
.autocomplete-dropdown li:hover {
  background: #e3f2fd;
}
.autocomplete-dropdown li.autocomplete-hint {
  color: #999;
  font-size: 0.8rem;
  font-style: italic;
  cursor: default;
  border-top: 1px solid #eee;
}
.autocomplete-dropdown li.autocomplete-hint:hover {
  background: transparent;
}
.selected-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.3rem;
  padding: 0.25rem 0.6rem;
  background: #e3f2fd;
  border-radius: 4px;
  font-size: 0.85rem;
  color: #1565c0;
}
.selected-tag button {
  background: none;
  border: none;
  color: #1565c0;
  cursor: pointer;
  font-size: 1rem;
  padding: 0;
  line-height: 1;
}
</style>
