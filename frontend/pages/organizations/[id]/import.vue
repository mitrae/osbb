<template>
  <div>
    <NuxtLink :to="`/organizations/${route.params.id}`" style="color:#1a73e8;text-decoration:none;font-size:0.9rem">&larr; Back to Organization</NuxtLink>

    <h1 style="margin-top:1rem">Import Registry</h1>

    <div v-if="!auth.isPlatformAdmin" class="card">
      <p class="error">Only platform admins can access this page.</p>
    </div>

    <template v-else>
      <!-- Building selector -->
      <div class="card">
        <div class="form-group">
          <label>Building</label>
          <select v-model="selectedBuildingId" required>
            <option value="" disabled>Select building</option>
            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.address }}</option>
          </select>
        </div>

        <div class="form-group">
          <label>CSV Data</label>
          <textarea
            v-model="csvContent"
            rows="10"
            placeholder="Paste CSV here: Owner Name,Number,Type,Space in m2"
            style="width:100%;font-family:monospace;font-size:0.85rem"
          ></textarea>
        </div>

        <div style="display:flex;gap:0.5rem">
          <button class="btn btn-primary" :disabled="!canImport || importing" @click="doImport">
            {{ importing ? 'Importing...' : 'Import' }}
          </button>
          <button v-if="csvContent" class="btn" style="background:#e0e0e0" @click="csvContent = ''; preview = null; result = null">Clear</button>
        </div>
      </div>

      <!-- Preview -->
      <div v-if="preview" class="card">
        <h2>Preview ({{ preview.totalRows }} rows)</h2>
        <p style="color:#666;font-size:0.9rem">
          {{ preview.apartments }} unique units, {{ preview.totalRows }} residents
        </p>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
              <tr style="background:#f5f5f5;text-align:left">
                <th style="padding:0.4rem 0.6rem;border-bottom:2px solid #ddd">Owner Name</th>
                <th style="padding:0.4rem 0.6rem;border-bottom:2px solid #ddd">Number</th>
                <th style="padding:0.4rem 0.6rem;border-bottom:2px solid #ddd">Type</th>
                <th style="padding:0.4rem 0.6rem;border-bottom:2px solid #ddd">Area (m2)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in preview.rows" :key="i" style="border-bottom:1px solid #eee">
                <td style="padding:0.4rem 0.6rem">{{ row.name }}</td>
                <td style="padding:0.4rem 0.6rem">{{ row.number }}</td>
                <td style="padding:0.4rem 0.6rem">{{ row.type }}</td>
                <td style="padding:0.4rem 0.6rem">{{ row.area }}</td>
              </tr>
              <tr v-if="preview.totalRows > preview.rows.length">
                <td colspan="4" style="padding:0.4rem 0.6rem;color:#999;text-align:center">
                  ... and {{ preview.totalRows - preview.rows.length }} more rows
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Result -->
      <div v-if="result" class="card">
        <h2>Import Result</h2>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:0.5rem">
          <div style="padding:0.5rem 1rem;background:#e8f5e9;border-radius:6px">
            <strong>{{ result.apartments_created }}</strong> apartments created
          </div>
          <div style="padding:0.5rem 1rem;background:#e3f2fd;border-radius:6px">
            <strong>{{ result.apartments_updated }}</strong> apartments updated
          </div>
          <div style="padding:0.5rem 1rem;background:#e8f5e9;border-radius:6px">
            <strong>{{ result.residents_created }}</strong> residents created
          </div>
          <div style="padding:0.5rem 1rem;background:#e3f2fd;border-radius:6px">
            <strong>{{ result.residents_updated }}</strong> residents updated
          </div>
        </div>
        <div v-if="result.errors && result.errors.length > 0" style="margin-top:1rem">
          <h3 style="color:#d32f2f">Errors ({{ result.errors.length }})</h3>
          <ul style="margin:0.5rem 0;padding-left:1.2rem">
            <li v-for="(err, i) in result.errors" :key="i" style="color:#d32f2f;font-size:0.9rem">{{ err }}</li>
          </ul>
        </div>
      </div>

      <p v-if="error" class="error" style="margin-top:1rem">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
const route = useRoute();
const api = useApi();
const auth = useAuthStore();
const orgStore = useOrganizationStore();

const buildings = ref<any[]>([]);
const selectedBuildingId = ref('');
const csvContent = ref('');
const importing = ref(false);
const error = ref('');
const result = ref<any>(null);
const preview = ref<any>(null);

const orgId = computed(() => Number(route.params.id));

const canImport = computed(() => selectedBuildingId.value && csvContent.value.trim());

// Parse CSV client-side for preview
watch(csvContent, (val) => {
  result.value = null;
  if (!val.trim()) {
    preview.value = null;
    return;
  }

  const lines = val.trim().split('\n');
  if (lines.length < 2) {
    preview.value = null;
    return;
  }

  const dataLines = lines.slice(1).filter((l) => l.trim());
  const rows = dataLines.map((line) => {
    const parts = line.split(',');
    return {
      name: parts[0]?.trim() || '',
      number: parts[1]?.trim() || '',
      type: parts[2]?.trim() || '',
      area: parts[3]?.trim() || '',
    };
  });

  const uniqueUnits = new Set(rows.map((r) => `${r.number}|${r.type}`));

  preview.value = {
    totalRows: rows.length,
    apartments: uniqueUnits.size,
    rows: rows.slice(0, 10),
  };
});

function withOrgContext<T>(fn: () => Promise<T>): Promise<T> {
  const savedOrg = orgStore.currentOrgId;
  orgStore.setCurrentOrg(orgId.value);
  return fn().finally(() => {
    if (savedOrg) orgStore.setCurrentOrg(savedOrg);
  });
}

async function loadBuildings() {
  try {
    const bData = await withOrgContext(() => api.get<any>('/api/buildings'));
    buildings.value = bData['hydra:member'] || bData.member || [];
  } catch {
    error.value = 'Failed to load buildings.';
  }
}

async function doImport() {
  if (!canImport.value) return;
  error.value = '';
  result.value = null;
  importing.value = true;

  try {
    result.value = await api.post(
      `/api/organizations/${orgId.value}/buildings/${selectedBuildingId.value}/import`,
      { csv: csvContent.value }
    );
  } catch (e: any) {
    error.value = e.message;
  } finally {
    importing.value = false;
  }
}

onMounted(loadBuildings);
</script>
