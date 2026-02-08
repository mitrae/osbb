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
            {{ cr.buildingAddress }} &mdash; {{ cr.aptLabel }}
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
          <div style="background:#f5f5f5;border-radius:6px;padding:0.75rem;margin-bottom:1rem">
            <p style="font-size:0.8rem;color:#999;margin:0 0 0.3rem;text-transform:uppercase;letter-spacing:0.5px">Requester</p>
            <div style="font-size:0.95rem"><strong>{{ cr.fullName }}</strong> <span style="color:#666">&mdash; {{ cr.phone }}</span></div>
          </div>

          <p style="font-size:0.8rem;color:#999;margin:0 0 0.5rem;text-transform:uppercase;letter-spacing:0.5px">Residents in {{ cr.aptLabel }}</p>
          <div v-if="cr.residents.length === 0" style="color:#666;font-size:0.85rem">No residents registered in this apartment.</div>
          <div
            v-for="r in cr.residents"
            :key="r.id"
            style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0.75rem;border:1px solid #eee;border-radius:6px;margin-bottom:0.4rem"
          >
            <div>
              <strong :style="r.nameMatch ? 'color:#2e7d32' : ''">{{ r.firstName }} {{ r.lastName }}</strong>
              <span v-if="r.nameMatch" style="color:#2e7d32;font-size:0.8rem;margin-left:0.3rem">(name matches)</span>
              <span style="color:#666;margin-left:0.5rem;font-size:0.85rem">{{ r.ownedArea }} m&sup2;</span>
              <span v-if="r.userEmail" style="color:#999;margin-left:0.5rem;font-size:0.85rem">(linked: {{ r.userEmail }})</span>
            </div>
            <button
              v-if="!r.userEmail"
              class="btn btn-primary"
              style="font-size:0.8rem;padding:0.25rem 0.6rem"
              :disabled="cr.processing"
              @click="approve(cr, r.id)"
            >
              Approve &amp; Link
            </button>
            <span v-else style="font-size:0.8rem;color:#999">already linked</span>
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
    requests.value = items.map((cr: any) => {
      const aptObj = typeof cr.apartment === 'object' ? cr.apartment : null;
      const aptType = aptObj?.type || 'apartment';
      const aptNumber = aptObj?.number || (typeof cr.apartment === 'string' ? '' : '');
      return {
        id: cr.id,
        fullName: cr.fullName,
        phone: cr.phone,
        status: cr.status,
        createdAt: cr.createdAt,
        userEmail: typeof cr.user === 'string' ? cr.user : cr.user?.email || '',
        userId: typeof cr.user === 'string' ? null : cr.user?.id,
        buildingAddress: typeof cr.building === 'string' ? cr.building : cr.building?.address || '',
        aptLabel: `${aptType === 'parking' ? 'Parking' : 'Apt'} #${aptNumber}`,
        apartmentId: typeof cr.apartment === 'string' ? parseInt(cr.apartment.split('/').pop()) : cr.apartment?.id,
        organizationIri: typeof cr.organization === 'string' ? cr.organization : cr.organization?.['@id'],
        showReview: false,
        residents: [],
        loadingResidents: false,
        processing: false,
      };
    });
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
    const data = await api.get<any>(`/api/residents?apartment=/api/apartments/${cr.apartmentId}&pagination=false`);
    const residents = Array.isArray(data) ? data : (data['hydra:member'] || data.member || []);
    const reqName = (cr.fullName || '').toLowerCase().trim();
    cr.residents = residents.map((r: any) => {
      const fullName = `${r.firstName || ''} ${r.lastName || ''}`.toLowerCase().trim();
      return {
        id: r.id,
        firstName: r.firstName,
        lastName: r.lastName,
        ownedArea: r.ownedArea,
        userEmail: typeof r.user === 'string' ? r.user : r.user?.email || null,
        nameMatch: reqName && fullName === reqName,
      };
    });
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
