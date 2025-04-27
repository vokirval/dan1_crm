<script setup>
import { ref, computed } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router, Link } from "@inertiajs/vue3";
import { Button, Dialog, InputText } from "primevue";
import { Settings, Trash, Workflow } from "lucide-vue-next";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

//Рауты и текста.
const fetchRoute = "/order-statuses";
const saveRoute = "/order-statuses";
const deleteRoute = "/order-statuses";
const pageTitle = "Статус замовлення";
const addButtonLabel = "Додати статус замовлень";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

const { props: inertiaProps } = usePage();
const items = ref(inertiaProps.data || []);
const itemForm = ref({
    id: null,
    name: "",
    color: "",
});

const loadItems = () => {
    router.get(
        fetchRoute,
        {
            page: currentPage.value,
            per_page: perPage.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            onSuccess: (page) => {
                items.value = page.props.data;
            },
        }
    );
};

const perPage = ref(items.value.per_page || 10);
const currentPage = ref(items.value.current_page || 1);
const sortBy = ref("created_at");
const sortDirection = ref("desc");

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;
    loadItems();
};

const onSortChange = (event) => {
    sortBy.value = event.sortField;
    sortDirection.value = event.sortOrder === 1 ? "asc" : "desc";
    loadItems();
};

const isModalVisible = ref(false);
const isModalVisibleAutoRule = ref(false);



const openModal = (item = null) => {
    if (item) {
        itemForm.value = { ...item };
    } else {
        itemForm.value = { id: null, name: "", color: "" };
    }
    isModalVisible.value = true;
};

const openModalAutoRule = (item = null) => {
    if (item) {
        itemForm.value = { ...item };
    } else {
        itemForm.value = { id: null, name: "", color: "" };
    }
    isModalVisibleAutoRule.value = true;
};



const saveItem = () => {
    const method = itemForm.value.id ? "put" : "post";
    const url = itemForm.value.id
        ? `${saveRoute}/${itemForm.value.id}`
        : saveRoute;

    router[method](url, itemForm.value, {
        onSuccess: () => {
            items.value = page.props.data;
            isModalVisible.value = false;
            toast.add({
                severity: "success",
                summary: "Success",
                detail: page.props.flash.success,
                life: 3000,
            });
        },
        onError: (error) => {
            const errorMessages = Object.values(error).flat().join("\n");
            toast.add({
                severity: "error",
                summary: "Error",
                detail: errorMessages,
                life: 5000,
            });
        },
    });
};

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
            router.delete(`${deleteRoute}/${data.id}`, {
                onSuccess: () => {
                    items.value = page.props.data;
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

const confirmDeleteAutoRule = (event, status, data) => {
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
                    isModalVisibleAutoRule.value = false;
                    items.value = page.props.data;
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
    <Head :title="pageTitle" />
    <Layout>
        <div class="flex justify-between mb-3">
            <div></div>
            <Button :label="addButtonLabel" @click="openModal" />
        </div>

        <DataTable
            :value="items.data"
            :paginator="true"
            :rows="perPage"
            :rows-per-page-options="[10, 20, 50, 100]"
            :first="(currentPage - 1) * perPage"
            :total-records="items.total"
            :lazy="true"
            :sort-field="sortBy"
            :sort-order="sortDirection === 'asc' ? 1 : -1"
            @page="onPageChange"
            @sort="onSortChange"
        >
            <Column field="id" header="ID" sortable />
            <Column field="name" header="Назва" sortable />
            <Column class="w-[40px]" header="Автоправила">
            <template #body="{ data }">
                <Link :href="`/order-statuses/${data.id}/auto-rules`">
                <span v-if="data.auto_rules.length > 0" class="text-green-500">
                    {{data.auto_rules.length}}
                </span>
                <span v-else class="text-red-500">0</span>
                </Link>
            </template>
            </Column>
            <Column class="w-[40px]" header="Колір">
            <template #body="{ data }">
                <span
                class="rounded flex items-center justify-center p-2 text-white"
                :style="{ backgroundColor: `#${data.color}` }"
                >
                {{data.name}}
                </span>
            </template>
            </Column>

           

            <Column class="w-[40px]">
                <template #body="{ data }">
                    <div class="flex gap-3">
                        <Button severity="secondary" @click="openModalAutoRule(data)">
                            <Workflow class="w-4 h-4" />
                        </Button>
                        
                        <Button severity="secondary" @click="openModal(data)">
                            <Settings class="w-4 h-4" />
                        </Button>
                        <Button
                            severity="secondary"
                            @click="confirmDelete($event, data)"
                        >
                            <Trash class="w-4 h-4" />
                        </Button>
                        
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="isModalVisible" :header="pageTitle" :modal="true" >
            <InputText v-model="itemForm.name" placeholder="Введіть назву" />
            <div class="flex-1 flex flex-col items-center mt-2">
                <label for="cp-hex" class="font-bold block mb-2"> Оберіть колір </label>
                <ColorPicker v-model="itemForm.color" inputId="cp-hex" format="hex" class="mb-4" style="border:1px solid #eee;" />
                <span>{{ color }}</span>
            </div>
            <template #footer>
                <Button label="Скасувати" severity="secondary" @click="isModalVisible = false" />
                <Button label="Зберегти" @click="saveItem" />
            </template>
        </Dialog>

        <Dialog v-model:visible="isModalVisibleAutoRule" header="Автоправила" :modal="true"  :style="{ width: '70rem' }">
           
            <table class="w-full border-collapse border border-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-200 p-2">Назва</th>
                        <th class="border border-gray-200 p-2">Активно</th>
                        <th class="border border-gray-200 p-2">Дії</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="rule in itemForm.auto_rules" :key="rule.id">
                        <td class="border border-gray-200 p-2">{{ rule.name }}</td>
                        <td class="border border-gray-200 p-2">{{ rule.is_active ? 'Так' : 'Ні' }}</td>
                        <td class="border border-gray-200 p-2">
                            <Link :href="`/auto-rules/${rule.id}/edit`" class="p-button p-component p-button-secondary mr-2">
                                <Settings class="w-4 h-4" />
                            </Link>
                        
                            <Button
                                severity="secondary"
                                @click="confirmDeleteAutoRule($event, itemForm.id, rule)" 
                            >
                                <Trash class="w-4 h-4" />
                            </Button>
                        
                        
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer>
                <Button label="Скасувати" severity="secondary" @click="isModalVisibleAutoRule = false" />
               
                <Link  :href="`/order-statuses/${itemForm.id}/auto-rules/create`" class="p-button p-component p-button-label">Додати автоправило</Link>
                
            </template>
        </Dialog>


    </Layout>
</template>
