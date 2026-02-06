export default defineNuxtPlugin(async () => {
  const auth = useAuthStore();
  auth.restore();

  const org = useOrganizationStore();
  org.restore();

  // If authenticated, load memberships
  if (auth.isAuthenticated && auth.user?.type === 'user') {
    await org.loadMemberships();
  }
});
