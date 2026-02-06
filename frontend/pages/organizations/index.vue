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

          <div class="form-group">
            <label>Building</label>
            <select v-model="apply.buildingId" required @change="onApplyBuildingChange" :disabled="!apply.organizationId">
              <option value="" disabled>{{ apply.organizationId ? 'Select building' : 'Select organization first' }}</option>
              <option v-for="b in applyBuildings" :key="b.id" :value="b.id">{{ b.address }}</option>
            </select>
          </div>

          <div class="form-group">
            <label>Apartment</label>
            <select v-model="apply.apartmentId" required :disabled="!apply.buildingId">
              <option value="" disabled>{{ apply.buildingId ? 'Select apartment' : 'Select building first' }}</option>
              <option v-for="a in applyApartments" :key="a.id" :value="a.id">Apt #{{ a.number }} ({{ a.totalArea }} m2)</option>
            </select>
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

const connectedOrgIds = computed(() => new Set(org.allOrgs.map(o => o.id)));
const availableOrgs = computed(() => allOrganizations.value.filter(o => !connectedOrgIds.value.has(o.id)));

// Apply for residency
const showApply = ref(false);
const applySubmitting = ref(false);
const applySubmitted = ref(false);
const applyError = ref('');
const applyBuildings = ref<any[]>([]);
const applyApartments = ref<any[]>([]);
const apply = reactive({
  organizationId: '',
  buildingId: '',
  apartmentId: '',
  fullName: '',
  phone: '',
});

async function onApplyOrgChange() {
  apply.buildingId = '';
  apply.apartmentId = '';
  applyBuildings.value = [];
  applyApartments.value = [];
  if (!apply.organizationId) return;

  try {
    const savedOrg = org.currentOrgId;
    org.setCurrentOrg(Number(apply.organizationId));
    const data = await api.get<any>('/api/buildings');
    applyBuildings.value = data['hydra:member'] || data.member || [];
    if (savedOrg) org.setCurrentOrg(savedOrg);

    if (applyBuildings.value.length === 1) {
      apply.buildingId = applyBuildings.value[0].id;
      await onApplyBuildingChange();
    }
  } catch {
    // ignore
  }
}

async function onApplyBuildingChange() {
  apply.apartmentId = '';
  applyApartments.value = [];
  if (!apply.buildingId) return;

  try {
    const savedOrg = org.currentOrgId;
    org.setCurrentOrg(Number(apply.organizationId));
    const data = await api.get<any>('/api/apartments');
    const allApts = data['hydra:member'] || data.member || [];
    applyApartments.value = allApts.filter((a: any) => {
      const bRef = typeof a.building === 'string' ? a.building : a.building?.['@id'] || `/api/buildings/${a.building?.id}`;
      return bRef === `/api/buildings/${apply.buildingId}`;
    });
    if (savedOrg) org.setCurrentOrg(savedOrg);
  } catch {
    // ignore
  }
}

async function submitApply() {
  applyError.value = '';
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
    apply.buildingId = '';
    apply.apartmentId = '';
    apply.fullName = '';
    apply.phone = '';
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

onMounted(async () => {
  try {
    const data = await api.get<any>('/api/organizations');
    allOrganizations.value = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
  } catch {
    // ignore
  } finally {
    loading.value = false;
  }
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
</style>
