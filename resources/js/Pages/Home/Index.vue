<script setup>
import { Head } from '@inertiajs/vue3'
const reqs = 'Landing Page'

window.location.href = '/login';

const authLink = () => {
  window.location.href = '/login';
}
const signupLink = () => {
  window.location.href = '/signup';
}
</script>

<template>
  <Layout>
    <Head title="Welcome" />
    <h1 class="font-bold text-5xl text-black text-center ">{{reqs}}</h1>
    <div class="flex items-center justify-center gap-3 mt-5">
      <Button @click="authLink">Авторизація</Button>
      <Button @click="signupLink">Рестрація</Button>
    </div>
 </Layout>
</template>

