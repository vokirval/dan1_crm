<script setup>
import { ref } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import { Button, Dialog, InputText } from "primevue";
import { Trash, Settings } from "lucide-vue-next";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

// Получение данных из Inertia
const { props: inertiaProps } = usePage();
const roles = ref(inertiaProps.roles || []);
const permissions = ref(inertiaProps.permissions || []);
const roleForm = ref({
    id: null,
    name: "",
    permissions: [],
});



// Управление состоянием
const isModalVisible = ref(false);

// Управление таблицей
const perPage = ref(roles.value.per_page || 10);
const currentPage = ref(roles.value.current_page || 1);
const sortBy = ref("created_at");
const sortDirection = ref("desc");

// Загрузка ролей
const loadRoles = () => {
    router.get(
        "/roles",
        {
            page: currentPage.value,
            per_page: perPage.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            onSuccess: (page) => {
                roles.value = page.props.roles;
            },
        }
    );
};

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;
    loadRoles();
};

const onSortChange = (event) => {
    sortBy.value = event.sortField;
    sortDirection.value = event.sortOrder === 1 ? "asc" : "desc";
    loadRoles();
};

// Открытие модального окна
const openModal = (role = null) => {
    if (role) {
        roleForm.value = { ...role };
    } else {
        roleForm.value = { id: null, name: "", permissions: [] };
    }
    isModalVisible.value = true;
};

// Сохранение роли
const saveRole = () => {
    const method = roleForm.value.id ? "put" : "post";
    const url = roleForm.value.id ? `/roles/${roleForm.value.id}` : "/roles";

    router[method](url, roleForm.value, {
        onSuccess: () => {
            toast.add({
                severity: "success",
                summary: "Успіх",
                detail: "Роль збережена успішно.",
                life: 3000,
            });
            isModalVisible.value = false;
            roles.value = page.props.roles;
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

// Удаление роли
const confirmDelete = (event, data) => {
    confirm.require({
        target: event.currentTarget,
        message: "Ви дійсно хочете видалити цю роль?",
        rejectProps: { label: "Ні", severity: "secondary", outlined: true },
        acceptProps: { label: "Так" },
        accept: () => {
            router.delete(`/roles/${data.id}`, {
                onSuccess: () => {
                    toast.add({
                        severity: "info",
                        summary: "Видалено",
                        detail: "Роль видалена успішно.",
                        life: 3000,
                    });
                    roles.value = page.props.roles;
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
    <Head title="Ролі" />
    <Layout>
        <div class="flex justify-between items-center mb-3">
            <h1 class="text-xl font-bold">Ролі</h1>
            <Button label="Додати роль" class="mb-3" @click="openModal" />
        </div>
        <!-- Таблица ролей -->
        <DataTable
            paginator
            :value="roles.data"
            :lazy="true"
            showGridlines
            :total-records="roles.total"
            :rows="perPage"
            :rows-per-page-options="[10, 20, 50, 100]"
            :first="(currentPage - 1) * perPage"
            :sort-field="sortBy"
            :sort-order="sortDirection === 'asc' ? 1 : -1"
            @page="onPageChange"
            @sort="onSortChange"
        >
            <Column field="id" header="ID"  />
            <Column field="name" header="Назва"  />
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
            header="Роль"
            :modal="true"
            :style="{ width: '50vw' }"
        >
            <div class="p-fluid">
                <div class="flex flex-col gap-2">
                    <label for="name">Назва Ролі</label>
                    <InputText id="name" v-model="roleForm.name" />
                    {{roleForm.permissions}}
                    <MultiSelect

                      v-model="roleForm.permissions"
                      :options="permissions"
                      optionLabel="name"
                      optionValue="id"
                      placeholder="Оберіть дозволи"
                      filter
                      class="w-full"
                  />
                </div>
            </div>
            <template #footer>
                <Button label="Скасувати" severity="secondary" @click="isModalVisible = false" />
                <Button label="Зберегти" @click="saveRole" />
            </template>
        </Dialog>
    </Layout>
</template>
