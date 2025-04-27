<script setup>
import { ref, computed } from "vue";
import Layout from "../../Layout/App.vue";
import { usePage, Head, router, Link } from "@inertiajs/vue3";



//Рауты и текста.
const fetchRoute = "/logs";
const pageTitle = "Перегляд логів";



const { props: inertiaProps } = usePage();
const items = ref(inertiaProps.data || []);

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

const formatDateTime = (date) => {
  if (!date) return "-";

  return new Intl.DateTimeFormat("pl-PL", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false
  }).format(new Date(date));
};
</script>
<template>
    <Head :title="pageTitle" />
    <Layout>
    

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
            <Column field="message" header="Повідомлення"  />

            <Column  header="Замовлення" >
                <template #body="{ data }">
                <Link :href="`/orders/${data.order_id}`" class="p-button p-component p-button-secondary mr-2">#{{ data.order_id}}</Link>
                </template>
            </Column>

            <Column  header="Автоправило" >
                <template #body="{ data }">
                <Link :href="`/auto-rules/${data.auto_rule_id}/edit`" class="p-button p-component p-button-secondary mr-2">#{{ data.auto_rule_id}}</Link>
                </template>
            </Column>

            <Column  header="Дата створення">
                <template #body="{ data }">
                {{ formatDateTime(data.created_at) }}
                </template>
            </Column>

        </DataTable>

      

    </Layout>
</template>
