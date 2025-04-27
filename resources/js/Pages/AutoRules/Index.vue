<script setup>
import { ref, computed } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router, Link } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const { props: props } = usePage();
const items = ref(props.data || []);
const orderStatus = ref(props.orderStatus || {});
const rules = ref(props.rules || []);
const page = usePage();

const confirmDelete = (event, data) => {
    confirm.require({
        target: event.currentTarget,
        message: "Ви дійсно хочете видалити?",
        rejectProps: {
            label: "Ні",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Так",
        },
        accept: () => {
            router.delete(`/order-statuses/${props.orderStatus.id}/auto-rules/${data.id}`, {
                onSuccess: () => {
                    rules.value = page.props.rules;
                    toast.add({
                        severity: "info",
                        summary: "Deleted",
                        detail: page.props.flash.success,
                        life: 3000,
                    });
                },
                onError: (error) => {
                    const errorMessages = Object.values(error)
                        .flat()
                        .join("\n");
                    toast.add({
                        severity: "error",
                        summary: "Error",
                        detail: errorMessages,
                        life: 5000,
                    });
                },
            });
        },
    });
};
</script>

<template>
    <Head :title="'Автоправила для ' + orderStatus.name" />
    <Layout>
        <div class="p-6 space-y-6">
            <h1 class="text-2xl font-bold">Автоправила для статусу: {{ orderStatus.name }}</h1>
            <Link :href="`/order-statuses/${orderStatus.id}/auto-rules/create`" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Створити правило</Link>
            <div class="mt-4">
                <table class="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-200 p-2">Назва</th>
                            <th class="border border-gray-200 p-2">Активно</th>
                            <th class="border border-gray-200 p-2">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in rules" :key="rule.id">
                            <td class="border border-gray-200 p-2">{{ rule.name }}</td>
                            <td class="border border-gray-200 p-2">{{ rule.is_active ? 'Так' : 'Ні' }}</td>
                            <td class="border border-gray-200 p-2">
                                <Link :href="order-statuses/auto-rules/edit" class="text-blue-500 hover:underline">Редагувати</Link>
                                <Button @click="confirmDelete($event, rule)" class="text-blue-500 hover:underline">Видалити</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Layout>
</template>