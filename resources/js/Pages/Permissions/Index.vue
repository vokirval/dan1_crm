<script setup>
import { ref } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import { Button, Dialog, InputText } from "primevue";
import { Trash, Plus } from "lucide-vue-next";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

// Получение данных из Inertia
const { props: inertiaProps } = usePage();
const permissions = ref(inertiaProps.permissions || []);
const permissionForm = ref({
    id: null,
    name: "",
});

// Управление состоянием
const isModalVisible = ref(false);

// Управление таблицей
const perPage = ref(permissions.value.per_page || 10);
const currentPage = ref(permissions.value.current_page || 1);
const sortBy = ref("created_at");
const sortDirection = ref("desc");

// Загрузка разрешений
const loadPermissions = () => {
    router.get(
        "/permissions",
        {
            page: currentPage.value,
            per_page: perPage.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            onSuccess: (page) => {
                permissions.value = page.props.permissions;
            },
        }
    );
};

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;
    loadPermissions();
};

const onSortChange = (event) => {
    sortBy.value = event.sortField;
    sortDirection.value = event.sortOrder === 1 ? "asc" : "desc";
    loadPermissions();
};

// Открытие модального окна
const openModal = (permission = null) => {
    if (permission) {
        permissionForm.value = { ...permission };
    } else {
        permissionForm.value = { id: null, name: "" };
    }
    isModalVisible.value = true;
};

// Сохранение разрешения
const savePermission = () => {
    const method = permissionForm.value.id ? "put" : "post";
    const url = permissionForm.value.id
        ? `/permissions/${permissionForm.value.id}`
        : "/permissions";

    router[method](url, permissionForm.value, {
        onSuccess: () => {
            toast.add({
                severity: "success",
                summary: "Успіх",
                detail: "Дозвіл збережено успішно.",
                life: 3000,
            });
            isModalVisible.value = false;
            permissions.value = page.props.permissions;
        },
        onError: (error) => {
            const errorMessages = Object.values(error).flat().join("\n");
            toast.add({
                severity: "error",
                summary: "Помилка",
                detail: errorMessages,
                life: 5000,
            });
        },
    });
};

// Удаление разрешения
const confirmDelete = (event, data) => {
    confirm.require({
        target: event.currentTarget,
        message: "Ви дійсно хочете видалити цей дозвіл?",
        rejectProps: { label: "Ні", severity: "secondary", outlined: true },
        acceptProps: { label: "Так" },
        accept: () => {
            router.delete(`/permissions/${data.id}`, {
                onSuccess: () => {
                    toast.add({
                        severity: "info",
                        summary: "Видалено",
                        detail: "Дозвіл видалено успішно.",
                        life: 3000,
                    });
                    permissions.value = page.props.permissions;
                },
                onError: (error) => {
                    const errorMessages = Object.values(error).flat().join("\n");
                    toast.add({
                        severity: "error",
                        summary: "Помилка",
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
    <Head title="Дозволи" />
    <Layout>
        <div class="flex justify-between items-center mb-3">
            <h1 class="text-xl font-bold">Дозволи</h1>
            <Button label="Додати дозвіл" class="mb-3" @click="openModal">
                <Plus class="w-4 h-4" />
            </Button>
        </div>
        <!-- Таблица разрешений -->
        <DataTable
            paginator
            :value="permissions.data"
            :lazy="true"
            showGridlines
            :total-records="permissions.total"
            :rows="perPage"
            :rows-per-page-options="[10, 20, 50, 100]"
            :first="(currentPage - 1) * perPage"
            :sort-field="sortBy"
            :sort-order="sortDirection === 'asc' ? 1 : -1"
            @page="onPageChange"
            @sort="onSortChange"
        >
            <Column field="id" header="ID" sortable />
            <Column field="name" header="Назва" sortable />
            <Column header="Дії">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button severity="secondary" @click="openModal(data)">
                            <Settings class="w-4 h-4" />
                        </Button>
                        <Button severity="danger" @click="confirmDelete($event, data)">
                            <Trash class="w-4 h-4" />
                        </Button>
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Модальное окно -->
        <Dialog
            v-model:visible="isModalVisible"
            header="Дозвіл"
            :modal="true"
            :style="{ width: '50vw' }"
        >
            <div class="p-fluid">
                <div class="flex flex-col gap-2">
                    <label for="name">Назва Дозволу</label>
                    <InputText id="name" v-model="permissionForm.name" />
                </div>
            </div>
            <template #footer>
                <Button label="Скасувати" severity="secondary" @click="isModalVisible = false" />
                <Button label="Зберегти" @click="savePermission" />
            </template>
        </Dialog>
    </Layout>
</template>
