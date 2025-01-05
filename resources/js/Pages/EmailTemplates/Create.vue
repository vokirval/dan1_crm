<template>
    <div>
      <h1 class="text-2xl font-bold mb-4">Create Email Template</h1>
      <form @submit.prevent="saveTemplate">
        <div class="mb-4">
          <label class="block mb-2">Template Name:</label>
          <input v-model="form.name" class="border p-2 w-full" />
        </div>
        <div class="mb-4">
          <label class="block mb-2">Subject:</label>
          <input v-model="form.subject" class="border p-2 w-full" />
        </div>
        <div class="mb-4">
          <label class="block mb-2">Body (with macros):</label>
          <textarea v-model="form.body" class="border p-2 w-full h-40"></textarea>
        </div>
        <button class="bg-blue-500 text-white px-4 py-2 rounded">Save</button>
      </form>
    </div>
  </template>
  
  <script setup>
  import { ref } from "vue";
  import { router } from "@inertiajs/vue3";
  
  const form = ref({
    name: "",
    subject: "",
    body: "",
  });
  
  const saveTemplate = () => {
    router.post("/email-templates", form.value);
  };
  </script>
  