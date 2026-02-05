export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore();
  const publicPages = ['/login', '/register', '/admin/login'];

  if (!publicPages.includes(to.path) && !auth.isAuthenticated) {
    return navigateTo('/login');
  }

  if (publicPages.includes(to.path) && auth.isAuthenticated) {
    return navigateTo('/');
  }
});
