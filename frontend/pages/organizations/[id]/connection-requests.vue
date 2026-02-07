<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">Connection Requests</h1>

    <div v-if="loading" class="card">Loading...</div>

    <div v-for="cr in requests" :key="cr.id" class="card">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div>
          <strong>{{ cr.fullName }}</strong>
          <span style="margin-left:0.5rem;color:#666;font-size:0.9rem">{{ cr.phone }}</span>
          <div style="font-size:0.85rem;color:#999;margin-top:0.3rem">
            {{ cr.buildingAddress }} &mdash; Apt #{{ cr.apartmentNumber }}
          </div>
          <div style="font-size:0.8rem;color:#999">
            By: {{ cr.userEmail }} &mdash; {{ new Date(cr.createdAt).toLocaleDateString() }}
          </div>
        </div>
        <span :class="`badge badge-${cr.status}`">{{ cr.status }}</span>
      </div>

      <!-- Review section for pending requests -->
      <div v-if="cr.status === 'pending'" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee">
        <div v-if="!cr.showReview">
          <button class="btn btn-primary" style="font-size:0.85rem" @click="loadResidents(cr)">
            {{ cr.loadingResidents ? 'Loading...' : 'Review' }}
          </button>
        </div>

        <div v-else>
          <p style="font-size:0.9rem;font-weight:500;margin-bottom:0.5rem">Residents in Apt #{{ cr.apartmentNumber }}:</p>
          <div v-if="cr.residents.length === 0" style="color:#666;font-size:0.85rem">No residents registered in this apartment.</div>
          <div v-for="r in cr.residents" :key="r.id" style="display:flex;justify-content:space-between;align-items:center;padding:0.3rem 0;font-size:0.9rem">
            <div>
              <span>{{ r.firstName }} {{ r.lastName }}</span>
              <span style="color:#666;margin-left:0.5rem">{{ r.ownedArea }} m2</span>
              <span v-if="r.userEmail" style="color:#999;margin-left:0.5rem">(linked: {{ r.userEmail }})</span>
            </div>
            <button
              v-if="!r.userEmail"
              class="btn btn-primary"
              style="font-size:0.8rem;padding:0.2rem 0.5rem"
              :disabled="cr.processing"
              @click="approve(cr, r.id)"
            >
              Approve &amp; Link
            </button>
          </div>

          <div style="margin-top:0.7rem;display:flex;gap:0.5rem">
            <button
              class="btn btn-danger"
              style="font-size:0.85rem"
              :disabled="cr.processing"
              @click="reject(cr)"
            >
              Reject
            </button>
            <button class="btn" style="background:#e0e0e0;font-size:0.85rem" @click="cr.showReview = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <p v-if="!loading && requests.length === 0" class="card">No connection requests.</p>
    <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const orgStore = useOrganizationStore();
const requests = ref<any[]>([]);
const loading = ref(true);
const error = ref('');

async function loadData() {
  loading.value = true;
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    const data = await api.get<any>('/api/connection_requests');
    const items = data['hydra:member'] || data.member || [];
    requests.value = items.map((cr: any) => ({
      id: cr.id,
      fullName: cr.fullName,
      phone: cr.phone,
      status: cr.status,
      createdAt: cr.createdAt,
      userEmail: typeof cr.user === 'string' ? cr.user : cr.user?.email || '',
      userId: typeof cr.user === 'string' ? null : cr.user?.id,
      buildingAddress: typeof cr.building === 'string' ? cr.building : cr.building?.address || '',
      apartmentNumber: typeof cr.apartment === 'string' ? cr.apartment : cr.apartment?.number || '',
      apartmentId: typeof cr.apartment === 'string' ? parseInt(cr.apartment.split('/').pop()) : cr.apartment?.id,
      organizationIri: typeof cr.organization === 'string' ? cr.organization : cr.organization?.['@id'],
      showReview: false,
      residents: [],
      loadingResidents: false,
      processing: false,
    }));
  } catch {
    // handle error
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    loading.value = false;
  }
}

async function loadResidents(cr: any) {
  cr.loadingResidents = true;
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    const data = await api.get<any>('/api/residents');
    const allResidents = data['hydra:member'] || data.member || [];
    // Filter residents for this apartment
    cr.residents = allResidents
      .filter((r: any) => {
        const aptRef = typeof r.apartment === 'string' ? r.apartment : r.apartment?.['@id'] || `/api/apartments/${r.apartment?.id}`;
        return aptRef === `/api/apartments/${cr.apartmentId}`;
      })
      .map((r: any) => ({
        id: r.id,
        firstName: r.firstName,
        lastName: r.lastName,
        ownedArea: r.ownedArea,
        userEmail: typeof r.user === 'string' ? r.user : r.user?.email || null,
      }));
    cr.showReview = true;
  } catch {
    // ignore
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    cr.loadingResidents = false;
  }
}

async function approve(cr: any, residentId: number) {
  cr.processing = true;
  error.value = '';
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    await api.patch(`/api/connection_requests/${cr.id}`, {
      status: 'approved',
      resident: `/api/residents/${residentId}`,
    });
    await loadData();
    // Refresh org store to pick up new resident link
    await orgStore.loadMemberships();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    cr.processing = false;
  }
}

async function reject(cr: any) {
  cr.processing = true;
  error.value = '';
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(Number(route.params.id));
  try {
    await api.patch(`/api/connection_requests/${cr.id}`, {
      status: 'rejected',
    });
    await loadData();
  } catch (e: any) {
    error.value = e.message;
  } finally {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
    cr.processing = false;
  }
}

onMounted(loadData);
</script>
