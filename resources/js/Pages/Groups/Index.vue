<script setup>
import { ref, computed } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import { Button, Dialog, InputText, MultiSelect } from "primevue";
import { Users, Trash, Settings } from "lucide-vue-next";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

const fetchRoute = "/groups";
const saveRoute = "/groups";
const deleteRoute = "/groups";
const userRoute = "/groups"; // Base route for user actions

const pageTitle = "Група";
const addButtonLabel = "Додати групу";

const { props: inertiaProps } = usePage();
const items = ref(inertiaProps.data || []);
const users = ref(inertiaProps.users || []); // Все пользователи
const groupUsers = ref([]); // Пользователи выбранной группы
const multiSelectUsers = computed(() => {
    const existingUserIds = groupUsers.value.map(user => user.id);
    return users.value.filter(user => !existingUserIds.includes(user.id));
});
const selectedUsers = ref([]); // Массив выбранных пользователей для добавления
const selectedGroup = ref(null); // Выбранная группа для управления

const itemForm = ref({
    id: null,
    name: "",
});

const isModalVisible = ref(false);
const isUserModalVisible = ref(false);

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

const openModal = (item = null) => {
    if (item) {
        itemForm.value = { ...item };
    } else {
        itemForm.value = { id: null, name: "" };
    }
    isModalVisible.value = true;
};

const saveItem = () => {
    const method = itemForm.value.id ? "put" : "post";
    const url = itemForm.value.id ? `${saveRoute}/${itemForm.value.id}` : saveRoute;

    router[method](url, itemForm.value, {
        onSuccess: () => {
            loadItems();
            isModalVisible.value = false;
            toast.add({
                severity: "success",
                summary: "Success",
                detail: page.props.flash.success,
                life: 3000,
            });
        },
        onError: (error) => {
            toast.add({
                severity: "error",
                summary: "Error",
                detail: Object.values(error).flat().join("\n"),
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
                    loadItems();
                    toast.add({
                        severity: "info",
                        summary: "Deleted",
                        detail: page.props.flash.success,
                        life: 3000,
                    });
                },
                onError: (error) => {
                    toast.add({
                        severity: "error",
                        summary: "Error",
                        detail: Object.values(error).flat().join("\n"),
                        life: 5000,
                    });
                },
            });
        },
    });
};

const openUserModal = (group) => {
    selectedGroup.value = group;
    groupUsers.value = group.users || [];
    isUserModalVisible.value = true;
};



const addUsersToGroup = () => {
    if (!selectedUsers.value.length) return;

    router.post(`${userRoute}/${selectedGroup.value.id}/users`, { user_ids: selectedUsers.value }, {
        onSuccess: () => {
            groupUsers.value.push(...users.value.filter(user => selectedUsers.value.includes(user.id)));
            selectedUsers.value = [];
            toast.add({
                severity: "success",
                summary: "Success",
                detail: "Користувачі додані в групу.",
                life: 3000,
            });
        },
        onError: (error) => {
            toast.add({
                severity: "error",
                summary: "Error",
                detail: Object.values(error).flat().join("\n"),
                life: 5000,
            });
        },
    });
};

const removeUserFromGroup = (userId) => {
    router.delete(`${userRoute}/${selectedGroup.value.id}/users/${userId}`, {
        onSuccess: () => {
            groupUsers.value = groupUsers.value.filter(user => user.id !== userId);
            items.value = page.props.data;
            toast.add({
                severity: "success",
                summary: "Success",
                detail: "Користувач видалений з групи.",
                life: 3000,
            });
        },
        onError: (error) => {
            toast.add({
                severity: "error",
                summary: "Error",
                detail: Object.values(error).flat().join("\n"),
                life: 5000,
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
            <Column header="Користувачі">
                <template #body="{ data }">
                    <Button severity="secondary" @click="openUserModal(data)">
                            <Users class="w-4 h-4" /> {{ data.users?.length }}
                        </Button>
                    
                </template>
            </Column>
            <Column class="w-[40px]">
                <template #body="{ data }">
                    <div class="flex gap-3">
                        <Button severity="secondary" @click="openModal(data)">
                            <Settings class="w-4 h-4" />
                        </Button>
                        <Button severity="secondary" @click="confirmDelete($event, data)">
                            <Trash class="w-4 h-4" />
                        </Button>
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="isModalVisible" :header="pageTitle" :modal="true">
            <InputText v-model="itemForm.name" placeholder="Введіть назву" />
            <template #footer>
                <Button label="Скасувати" severity="secondary" @click="isModalVisible = false" />
                <Button label="Зберегти" @click="saveItem" />
            </template>
        </Dialog>

        <Dialog v-model:visible="isUserModalVisible" header="Користувачі Групи" :modal="true" :style="{ width: '50vw' }">
            <h4>Користувачі:</h4>
            <ul class="divide-y divide-[#e2e8f0] ">
                <li v-for="user in groupUsers" :key="user.id" class="flex justify-between items-center py-2">
                    {{ user.name }}
                    <Button severity="secondary" size="small" @click="removeUserFromGroup(user.id)">
                        <Trash class="w-4 h-4" />
                    </Button>
                </li>
            </ul>

            <h4 class="mt-4">Додати Користувача:</h4>
            <MultiSelect
                filter 
                v-model="selectedUsers"
                :options="multiSelectUsers"
                optionLabel="name"
                optionValue="id"
                class="w-full"
                placeholder="Оберіть користувачів"
            />

            <template #footer>
                <Button label="Скасувати" severity="secondary" @click="isUserModalVisible = false" />
                <Button label="Додати" :disabled="!selectedUsers.length" @click="addUsersToGroup" />
            </template>
        </Dialog>
    </Layout>
</template>
