<script setup>
import { ref } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import { Button, Dialog, InputText, MultiSelect } from "primevue";
import { Trash, Pencil } from "lucide-vue-next";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

const { props: inertiaProps } = usePage();

// Данные из сервера
const users = ref(inertiaProps.users || []);
const roles = ref(inertiaProps.roles || []);

// Управление модальными окнами
const isUserModalVisible = ref(false);
const isRoleModalVisible = ref(false);

// Форма пользователя
const userForm = ref({
    id: null,
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

// Роли для пользователя
const selectedRoles = ref([]);



// Управление таблицей
const perPage = ref(roles.value.per_page || 10);
const currentPage = ref(roles.value.current_page || 1);
const sortBy = ref("created_at");
const sortDirection = ref("desc");

// Загрузка пользователей
const loadUsers = () => {
    router.get(
        "/users",
        {
            page: currentPage.value,
            per_page: perPage.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            onSuccess: (page) => {
                users.value = page.props.users;
            },
        }
    );
};

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;
    loadUsers();
};

const onSortChange = (event) => {
    sortBy.value = event.sortField;
    sortDirection.value = event.sortOrder === 1 ? "asc" : "desc";
    loadUsers();
};

// Открытие модального окна пользователя
const openUserModal = (user = null) => {
    if (user) {
        userForm.value = { ...user, password: "", password_confirmation: "" };
    } else {
        userForm.value = { id: null, name: "", email: "", password: "", password_confirmation: "" };
    }
    isUserModalVisible.value = true;
};

// Сохранение пользователя
const saveUser = () => {
    const method = userForm.value.id ? "put" : "post";
    const url = userForm.value.id ? `/users/${userForm.value.id}` : "/users";

    router[method](url, userForm.value, {
        onSuccess: () => {
            toast.add({
                severity: "success",
                summary: "Успіх",
                detail: "Користувач збережений успішно.",
                life: 3000,
            });
            isUserModalVisible.value = false;
            users.value = page.props.users;
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

// Удаление пользователя
const deleteUser = (user) => {
    router.delete(`/users/${user.id}`, {
        onSuccess: () => {
            toast.add({
                severity: "info",
                summary: "Видалено",
                detail: "Користувач видалений успішно.",
                life: 3000,
            });
            users.value = page.props.users;
        },
    });
};

// Открытие модального окна ролей
const openRoleModal = (user) => {
    selectedRoles.value = user.roles.map((role) => role.id); // Загружаем текущие роли
    userForm.value = user;
    isRoleModalVisible.value = true;
};

// Сохранение ролей
const saveRoles = () => {
    router.put(`/users/${userForm.value.id}/roles`, { roles: selectedRoles.value }, {
        onSuccess: () => {
            toast.add({
                severity: "success",
                summary: "Успіх",
                detail: "Ролі оновлено успішно.",
                life: 3000,
            });
            isRoleModalVisible.value = false;
            users.value = page.props.users;
        },
    });
};
</script>

<template>
    <Head title="Користувачі" />
    <Layout>
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-bold">Користувачі</h1>
            <Button label="Додати користувача" @click="openUserModal" />
        </div>


        <DataTable
            paginator
            :value="users.data"
            :lazy="true"
            showGridlines
            :total-records="users.total"
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
            <Column field="email" header="Email"  />
            <Column header="Дії">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button label="Ролі" class="p-button-sm" @click="openRoleModal(data)" />
                        <Button class="p-button-sm" @click="openUserModal(data)"> <Pencil class="w-4 h-4" /></Button>
                        <Button class="p-button-sm p-button-danger" @click="deleteUser(data)"><Trash class="w-4 h-4 "/> </Button>
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Модальное окно пользователя -->
        <Dialog v-model:visible="isUserModalVisible" header="Користувач">
            <div class="p-fluid">
                <div class="mb-3">
                    <label for="name">Ім'я</label>
                    <InputText id="name" v-model="userForm.name" />
                </div>
                <div class="mb-3">
                    <label for="email">Email</label>
                    <InputText id="email" v-model="userForm.email" />
                </div>
                <div class="mb-3" v-if="!userForm.id">
                    <label for="password">Пароль</label>
                    <InputText id="password" type="password" v-model="userForm.password" />
                </div>
                <div class="mb-3" v-if="!userForm.id">
                    <label for="password_confirmation">Підтвердити пароль</label>
                    <InputText id="password_confirmation" type="password" v-model="userForm.password_confirmation" />
                </div>
            </div>
            <template #footer>
                <Button label="Скасувати" @click="isUserModalVisible = false" />
                <Button label="Зберегти" @click="saveUser" />
            </template>
        </Dialog>

        <!-- Модальное окно ролей -->
        <Dialog v-model:visible="isRoleModalVisible" header="Ролі користувача">
            <MultiSelect v-model="selectedRoles" :options="roles" optionLabel="name" optionValue="id" />
            <template #footer>
                <Button label="Скасувати" @click="isRoleModalVisible = false" />
                <Button label="Зберегти" @click="saveRoles" />
            </template>
        </Dialog>
    </Layout>
</template>
