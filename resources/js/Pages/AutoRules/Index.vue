<script setup>
import { ref, computed } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router, Link } from "@inertiajs/vue3";
import { Settings, Trash } from "lucide-vue-next";
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
            router.delete(`/auto-rules/${data.id}`, {
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
            <div class="flex items-center mb-4 justify-between">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold">Автоправила для статусу: </h1>
                    <div
                    class="rounded  p-2 text-white"
                    :style="{ backgroundColor: `#${orderStatus.color}` }"
                    > {{ orderStatus.name }}</div>
                </div>
                
            
                <Link :href="`/order-statuses/${orderStatus.id}/auto-rules/create`" class="p-button p-component p-button-label">Створити правило</Link>
           </div>



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
                                <Link :href="`/auto-rules/${rule.id}/edit`" class="p-button p-component p-button-secondary mr-2">
                                <Settings class="w-4 h-4" />
                            </Link>
                        
                            <Button
                                severity="secondary"
                                @click="confirmDelete($event, rule)"
                            >
                                <Trash class="w-4 h-4" />
                            </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Layout>
</template>