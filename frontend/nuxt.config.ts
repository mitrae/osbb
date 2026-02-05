export default defineNuxtConfig({
  devtools: { enabled: true },

  modules: ['@pinia/nuxt'],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
    },
  },

  devServer: {
    host: '0.0.0.0',
    port: 3000,
  },

  compatibilityDate: '2025-01-01',
});
