export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore();
  const publicPages = ['/login', '/register'];

  if (!publicPages.includes(to.path) && !auth.isAuthenticated) {
    return navigateTo('/login');
  }

  if (publicPages.includes(to.path) && auth.isAuthenticated) {
    return navigateTo('/');
  }

  // Allow /organizations routes without org context
  if (to.path.startsWith('/organizations')) {
    return;
  }

  // For regular users with no org, redirect to /organizations (except public pages)
  if (auth.isAuthenticated && !auth.isPlatformAdmin && !publicPages.includes(to.path)) {
    const org = useOrganizationStore();
    if (!org.hasOrg && org.allOrgs.length === 0 && to.path !== '/organizations') {
      return navigateTo('/organizations');
    }
  }
});
