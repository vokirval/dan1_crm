<script setup>
import { ref } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import { Button, Dialog, InputText } from "primevue";
import { Settings, Trash } from "lucide-vue-next";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

// Получаем данные из Inertia
const { props: inertiaProps } = usePage();
const categories = ref(inertiaProps.data || []);
const categoryForm = ref({
    id: null,
    name: "",
});

// Загрузка категорий
const loadCategories = () => {
    router.get(
        "/categories",
        {
            page: currentPage.value,
            per_page: perPage.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            onSuccess: (page) => {
                categories.value = page.props.data;
            },
        }
    );
};

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;

    loadCategories();
};

// Обработчик изменения сортировки
const onSortChange = (event) => {
    sortBy.value = event.sortField;
    sortDirection.value = event.sortOrder === 1 ? "asc" : "desc";

    loadCategories();
};

// Состояние модального окна
const isModalVisible = ref(false);

// Управление состоянием таблицы
const perPage = ref(categories.value.per_page || 10);
const currentPage = ref(categories.value.current_page || 1);
const sortBy = ref("created_at");
const sortDirection = ref("desc");

// Обработчик открытия модального окна для добавления/редактирования
const openModal = (category = null) => {
    if (category) {
        categoryForm.value = { ...category }; // Редактирование
    } else {
        categoryForm.value = { id: null, name: "" }; // Новая категория
    }
    isModalVisible.value = true;
};

// Сохранение категории
const saveCategory = () => {
    const method = categoryForm.value.id ? "put" : "post";
    const url = categoryForm.value.id
        ? `/categories/${categoryForm.value.id}`
        : "/categories";

    router[method](url, categoryForm.value, {
        onSuccess: () => {
            categories.value = page.props.data;
            toast.add({
                severity: "success",
                summary: "Success",
                detail: page.props.flash.success,
                life: 3000,
            });
            isModalVisible.value = false;
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
            router.delete(`/categories/${data.id}`, {
                onSuccess: () => {
                    categories.value = page.props.data;
                    toast.add({
                        severity: "info",
                        summary: "Confirmed",
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
    <Head title="Категорії" />
    <Layout>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div></div>
            <Button label="Додати категорію" class="mb-3" @click="openModal" />
        </div>
        <!-- Таблица -->
        <DataTable
            paginator
            :value="categories.data"
            :lazy="true"
            showGridlines
            :total-records="categories.total"
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
            <Column class="w-[40px]">
                <template #body="{ data }">
                    <div class="flex gap-3">
                        <Button severity="secondary" @click="openModal(data)"
                            ><Settings class="w-4 h-4"
                        /></Button>
                        <Button
                            severity="secondary"
                            @click="confirmDelete($event, data)"
                            ><Trash class="w-4 h-4"
                        /></Button>
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Модальное окно -->
        <Dialog
            v-model:visible="isModalVisible"
            header="Категорія"
            :modal="true"
            :closable="true"
            :style="{ width: '50vw' }"
        >
            <div class="p-fluid">
                <div class="flex flex-col gap-2">
                    <label for="name">Назва Категорії</label>
                    <InputText id="name" v-model="categoryForm.name" />
                </div>
            </div>
            <template #footer>
                <Button
                    severity="secondary"
                    label="Скасувати"
                    class="ml-2"
                    @click="isModalVisible = false"
                />
                <Button label="Зберегти" @click="saveCategory" />
            </template>
        </Dialog>
    </Layout>
</template>
