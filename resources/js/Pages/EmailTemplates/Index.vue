<script setup>
import { ref } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";

const page = usePage();
const { props: inertiaProps } = usePage();

const templates = ref(inertiaProps.templates|| []);

const createTemplate = () => {
  router.visit("/email-templates/create");
};

const editTemplate = (id) => {
  router.visit(`/email-templates/${id}/edit`);
};

const deleteTemplate = (id) => {
  if (confirm("Вы впевнені, що хочете видалити шаблон?")) {
    router.delete(`/email-templates/${id}`, {
      onSuccess: () => templates.value = page.props.templates,
    });
  }
};
</script>


<template>
  <Layout>
    <div>
      <h1 class="text-2xl font-bold mb-4">Email Шаблони</h1>
      <button
        @click="createTemplate"
        class="bg-blue-500 text-white px-4 py-2 rounded mb-4"
      >
        Створити новий шаблон
      </button>
      <table class="w-full border-collapse border">
        <thead>
          <tr class="bg-gray-200">
            <th class="border p-2">ID</th>
            <th class="border p-2">Назва</th>
            <th class="border p-2">Тема</th>
            <th class="border p-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="template in templates" :key="template.id">
            <td class="border p-2">{{ template.id }}</td>
            <td class="border p-2">{{ template.name }}</td>
            <td class="border p-2">{{ template.subject }}</td>
            <td class="border p-2">
              <button
                @click="editTemplate(template.id)"
                class="bg-yellow-500 text-white px-2 py-1 rounded"
              >
                Редагувати
              </button>
              <button
                @click="deleteTemplate(template.id)"
                class="bg-red-500 text-white px-2 py-1 rounded ml-2"
              >
                Видалити
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </Layout>
  </template>
  
