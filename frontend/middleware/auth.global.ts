export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore();
  const publicPages = ['/login', '/register', '/admin/login'];

  if (!publicPages.includes(to.path) && !auth.isAuthenticated) {
    return navigateTo('/login');
  }

  if (publicPages.includes(to.path) && auth.isAuthenticated) {
    return navigateTo('/');
  }

  // Allow /organizations routes without org context (user needs to pick one)
  if (to.path.startsWith('/organizations')) {
    return;
  }

  // For regular users with no org, redirect to /organizations (except public pages)
  if (auth.isAuthenticated && auth.user?.type === 'user' && !publicPages.includes(to.path)) {
    const org = useOrganizationStore();
    if (!org.hasOrg && org.approvedMemberships.length === 0 && to.path !== '/organizations') {
      return navigateTo('/organizations');
    }
  }
});
