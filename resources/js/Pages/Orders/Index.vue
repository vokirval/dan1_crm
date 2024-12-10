<script setup>
import { ref, onMounted, computed  } from 'vue';
import Layout from '../../Layout/App.vue';
import { usePage, Head, router, Link } from '@inertiajs/vue3';
import { DataTable, Column, Button } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { Plus } from 'lucide-vue-next';

const page = usePage();
const toast = useToast();

const frozens = ref({
  'utm_source': false,
  'group': true
});

const { props: inertiaProps } = usePage();
const orders = ref(inertiaProps.data || []);
const statuses = inertiaProps.statuses || [];
const currentStatusId = ref(inertiaProps.currentStatusId || null);

const fetchRoute = "/orders";
const perPage = ref(orders.value.per_page || 10);
const currentPage = ref(orders.value.current_page || 1);
const sortBy = ref('created_at');
const sortDirection = ref('desc');

const onPageChange = (event) => {
  currentPage.value = event.page + 1;
  perPage.value = event.rows;
  loadOrders();
};

const onSortChange = (event) => {
  sortBy.value = event.sortField;
  sortDirection.value = event.sortOrder === 1 ? 'asc' : 'desc';
  loadOrders();
};

const loadOrders = () => {
  router.get(
    fetchRoute,
    {
      page: currentPage.value,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
      status_id: currentStatusId.value,
    },
    {
      preserveState: true,
      onSuccess: (page) => {
        orders.value = page.props.data;
      },
    }
  );
};

const viewOrder = (orderId) => {
  router.get(`/orders/${orderId}`, {}, {
   
  });
};


const cm = ref();
const selectedProduct = ref();
const metaKey = ref(true);
const menuModel = ref([
    {label: 'Перегляд', icon: 'pi pi-fw pi-search', command: () => viewProduct(selectedProduct)},
    {label: 'Змінити статус', icon: 'pi pi-fw pi-times', command: () => deleteProduct(changeStatus)}
]);

const onRowContextMenu = (event) => {
    cm.value.show(event.originalEvent);
};
const viewProduct = (product) => {
    toast.add({severity: 'info', summary: 'Product Selected', detail: product.value.name, life: 3000});
};
const changeStatus = (product) => {
    toast.add({severity: 'info', summary: 'Product Selected', detail: product.value.name, life: 3000});
};



onMounted(() => {
  const tableContainer = document.querySelector('.p-datatable-table-container');
  const listStatuses = document.querySelector('.list-statuses'); 
  if (tableContainer) {
    tableContainer.addEventListener('wheel', (e) => {
      if (e.deltaY !== 0) {
        e.preventDefault();
        tableContainer.scrollLeft += e.deltaY;
      }
    });
  }
  if (listStatuses) {
    listStatuses.addEventListener('wheel', (e) => {
      if (e.deltaY !== 0) {
        e.preventDefault();
        listStatuses.scrollLeft += e.deltaY;
      }
    });
  }
});
const formatDateTime = (date) => {
  const options = { 
    day: '2-digit', 
    month: '2-digit', 
    year: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit' 
  };
  return new Date(date).toLocaleString('ru-RU', options);
};

const filterByStatus = (statusId) => {
  currentStatusId.value = statusId;
  loadOrders();
};

</script>

<template>
  <Head title="Заказы" />
  <Layout>
    <div class="w-full flex overflow-x-scroll overflow-y-hidden gap-3 align-start p-3 list-statuses bg-[#eee] rounded
    [&::-webkit-scrollbar]:h-2
  [&::-webkit-scrollbar-track]:bg-gray-100
  [&::-webkit-scrollbar-thumb]:bg-gray-300
  [&::-webkit-scrollbar-track]:rounded-full
  [&::-webkit-scrollbar-thumb]:rounded-full">
  <div
        class="rounded p-2 text-white min-w-[150px] bg-[#020617] cursor-pointer hover:scale-105 hover:shadow-sm"
        :class="{ 'font-medium': !currentStatusId }"
        @click="filterByStatus(null)"
      >
        Всі ({{ orders.total }})
      </div>
      <div
        v-for="status in statuses"
        :key="status.id"
        class="rounded p-2 text-white min-w-[150px] cursor-pointer hover:scale-105 hover:shadow-sm"
        :class="{ 'font-medium': currentStatusId === status.id }"
        :style="{ backgroundColor: `#${status.color}` }"
        @click="filterByStatus(status.id)"
      >
        {{ status.name }} ({{ status.orders_count }})
      </div>
    </div>
    <div class="flex justify-between items-center my-4">
      <h2 class="text-xl font-semibold">Список замовлень</h2>
      <Link href="/orders/create"  as="Button" class="p-button p-component p-button-contrast"><Plus /> Додати замовлення</Link>
    </div>

    <ContextMenu ref="cm" :model="menuModel" @hide="selectedProduct = null" />
    <DataTable
    contextMenu v-model:contextMenuSelection="selectedProduct"
                @rowContextmenu="onRowContextMenu"
      v-model:selection="selectedProduct" 
      :value="orders.data"
      :paginator="true"
      :rows="perPage"
      :rows-per-page-options="[10, 20, 50, 100]"
      :first="(currentPage - 1) * perPage"
      :total-records="orders.total"
      :lazy="true"
      :sort-field="sortBy"
      :sort-order="sortDirection === 'asc' ? 1 : -1"
      @page="onPageChange"
      @sort="onSortChange"
      showGridlines
      selectionMode="multiple"
      :metaKeySelection="metaKey"
      dataKey="id"
      scrollable 
    class=""
    >
      <Column field="id" header="ID" />
      <Column class="w-[40px]" header="Статус">
        <template #body="{ data }">
            <span v-if="data.status"
            class="rounded flex items-center justify-center p-1 text-white text-xs"
            :style="{ backgroundColor: `#${data.status.color}` }"
            >
            {{data.status.name}}
            </span>
            <span v-else
            class="rounded flex items-center justify-center p-1 text-white bg-black text-xs"
            >
            Без статусу
            </span>
        </template>
      </Column>

      <Column field="delivery_fullname" header="Контакт" />
      <Column field="phone" header="Телефон" />
      <Column field="comment" header="Коментар" />

      <Column field="responsible_user.name" header="Відповідальний"/>

      <Column field="delivery_city" header="Місто" />
      <Column field="delivery_address" header="Адреса" />
      <Column field="delivery_postcode" header="Зіп код" />
      
      <Column field="payment_method.name" header="Оплата" />
      <Column field="delivery_method.name" header="Доставка" />

      <Column field="group.name" header="Група" alignFrozen="right" :frozen="frozens.group">
      <template #header>
          <ToggleButton v-model="frozens.group" onLabel="-" offLabel="+" />
      </template>
      </Column>

      <Column field="ip" header="IP" />
      <Column field="website_referrer" header="Website Reffer" />
      
      <Column field="utm_source" header="utm_source" alignFrozen="right" :frozen="frozens.utm_source" >
        <template #header>
          <ToggleButton v-model="frozens.utm_source" onLabel="-" offLabel="+" />
      </template>
      </Column>
      <Column field="utm_medium" header="utm_medium" alignFrozen="right" :frozen="frozens.utm_medium" />
      <Column field="utm_campaign" header="utm_campaign" />
      <Column field="utm_content" header="utm_content" />
      <Column field="utm_term" header="utm_term" />

      <Column header="created_at">
        <template #body="{ data }">
          {{ formatDateTime(data.created_at) }}
        </template>
      </Column>

      <Column header="updated_at">
        <template #body="{ data }">
          {{ formatDateTime(data.updated_at) }}
        </template>
      </Column>
      
      <Column header="Действия" class="w-[150px]">
        <template #body="{ data }">
          <Button
            label="Просмотр"
            class="p-button-info p-button-sm"
            @click="viewOrder(data.id)"
          />
        </template>
      </Column>
    </DataTable>
  </Layout>
</template>

<style>
tbody {
  white-space: nowrap;
}
</style>