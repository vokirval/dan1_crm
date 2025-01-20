<script setup>
import { ref, onMounted, computed  } from 'vue';
import Layout from '../../Layout/App.vue';
import { usePage, Head, router, Link } from '@inertiajs/vue3';
import { DataTable, Column, Button } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { Plus, Pencil, Filter, FilterX, Search, RefreshCcw } from 'lucide-vue-next';
import { useConfirm } from "primevue/useconfirm";

const page = usePage();
const toast = useToast();
const confirm = useConfirm();

const frozens = ref({
  'utm_source': false,
  'group': true
});

const filters = ref({
  id: "",
  delivery_fullname: "",
  phone: "",
  ip: "",
  email: "",
});

const { props: inertiaProps } = usePage();
console.log(inertiaProps);
const orders = ref(inertiaProps.data || []);
const statuses = inertiaProps.statuses || [];
const currentStatusId = ref(inertiaProps.currentStatusId || null);
const isLoading = ref(false);
const fetchRoute = "/orders";
const perPage = ref(orders.value.per_page || 10);
const currentPage = ref(orders.value.current_page || 1);
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const showFilters = ref(false);
const visible = ref(false);
const selectedOrder = ref(null);

const selectedStatus = ref(null); // Храним выбранный статус
const popupRef = ref(null); // Ссылка на ConfirmPopup
const actionType = ref(''); // Тип действия (удаление, изменение статуса, комментарий)
const actionData = ref(null); // Данные для действия (например, статус или комментарий)
const commentDialog = ref(null);


 // Открытие диалога
 const openOrderDialog = (event) => {
  selectedOrder.value = event.data; // Передаем модель заказа
  visible.value = true;
};



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
  isLoading.value = true;
  router.get(
    fetchRoute,
    {
      page: currentPage.value,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
      status_id: currentStatusId.value,
      ...filters.value, // Передаем все фильтры
    },
    {
      preserveState: true,
      onSuccess: (page) => {
        orders.value = page.props.data;
      },
      onFinish: () => {
        isLoading.value = false; // Выключаем состояние загрузки
      },
    }
  );
};

const resetFilters = () => {
  filters.value = {
    id: "",
    delivery_fullname: "",
    phone: "",
    ip: "",
    email: "",
  };
  loadOrders();
};

onMounted(() => {
  filters.value = {
    id: inertiaProps.filters.id || "",
    delivery_fullname: inertiaProps.filters.delivery_fullname || "",
    phone: inertiaProps.filters.phone || "",
    ip: inertiaProps.filters.ip || "",
    email: inertiaProps.filters.email || "",
  };
});

const viewOrder = (orderId) => {
  router.get(`/orders/${orderId}`, {}, {
   
  });
};


const selectedProduct = ref([]);

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


// Форматируем название вариации на основе её атрибутов
function formatVariationName(variation) {
  if (
    !variation ||
    !variation.attributes ||
    variation.attributes.length === 0
  ) {
    return "Без атрибутов";
  }

  return variation.attributes
    .map((attr) => `${attr.attribute_name}: ${attr.attribute_value}`)
    .join(", ");
}


const formatCurrency = (value, locale = 'pl-PL', currency = 'PLN') => {
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currency,
  }).format(value);
}


const totalAmount = (selectedOrder) => {
  return selectedOrder.reduce((total, item) => {
    return total + item.quantity * item.price;
  }, 0);
};




// Триггер для массового удаления
const triggerMassDelete = (event) => {
  if (!selectedProduct.value.length) {
    toast.add({ severity: 'warn', summary: 'Ошибка', detail: 'Выберите хотя бы один заказ.', life: 3000, });
    return;
  }
  confirm.require({
    target: event.currentTarget,
    message: "Ви дійсно хочете видалити вибрані замовлення?",
    rejectProps: {
      label: "Ні",
      severity: "secondary",
      outlined: true,
    },
    acceptProps: {
      label: "Так",
    },
    accept: () => {
      router.post('/orders/mass-delete', { order_ids: selectedProduct.value.map(o => o.id) }, {
        onSuccess: () => {
          toast.add({ severity: 'success', summary: 'Успіх!', detail: 'Замовлення успішно видалені!', life: 3000, });
          loadOrders();
        },
        onError: () => {
          toast.add({ severity: 'error', summary: 'Помилка', detail: 'Помилка...', life: 3000, });
        },
      });
    },
  });
};

// Триггер для массовой смены статуса
const triggerMassUpdateStatus = (event) => {
  if (!selectedProduct.value.length || !selectedStatus.value) {
    toast.add({ severity: 'warn', summary: 'Ошибка', detail: 'Выберите заказы и статус.', life: 3000, });
    return;
  }
  confirm.require({
    target: event.currentTarget,
    message: "Ви дійсно хочете оновити статус у вибраних замовленнях?",
    rejectProps: {
      label: "Ні",
      severity: "secondary",
      outlined: true,
    },
    acceptProps: {
      label: "Так",
    },
    accept: () => {
      router.post('/orders/mass-update-status', { 
        order_ids: selectedProduct.value.map(o => o.id), 
        status_id: selectedStatus.value
      }, {
        onSuccess: () => {
          toast.add({ severity: 'success', summary: 'Успішно!', detail: 'Статуси оновлено.', life: 3000, });
          selectedStatus.value = null;
          loadOrders();
        },
        onError: () => {
          toast.add({ severity: 'error', summary: 'Помилка', detail: 'Помилка...', life: 3000, });
        },
      });
    },
  });
};

// Триггер для массового изменения комментариев
const triggerMassUpdateComment = (event, comment) => {
  if (!selectedProduct.value.length) {
    toast.add({ severity: 'warn', summary: 'Ошибка', detail: 'Выберите хотя бы один заказ.', life: 3000, });
    return;
  }
  confirm.require({
    target: event.currentTarget,
    message: "Ви дійсно хочете оновити коментар у вибраних замовленнях?",
    rejectProps: {
      label: "Ні",
      severity: "secondary",
      outlined: true,
    },
    acceptProps: {
      label: "Так",
    },
    accept: () => {
      router.post('/orders/mass-update-comment', { 
        order_ids: selectedProduct.value.map(o => o.id), 
        comment 
      }, {
        onSuccess: () => {
          toast.add({ severity: 'success', summary: 'Успіх!', detail: 'Коментар оновлено!', life: 3000, });
          commentDialog.value = false;
          loadOrders();
        },
        onError: () => {
          commentDialog.value = false;
          toast.add({ severity: 'error', summary: 'Помилка!', detail: 'Помилка...', life: 3000, });
        },
      });
    },
  });
};
</script>

<template>
  <Head title="Замовлення" />
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
        Всі замовлення
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
    <div class="flex justify-between items-center my-4 gap-3">
      <Button @click="loadOrders" severity="secondary" :disabled="isLoading">
        <RefreshCcw 
          class="w-5 h-5 transition-transform duration-500 ease-in-out" 
          :class="{ 'animate-spin': isLoading }" 
        />
      </Button>
      <div class="flex gap-3">
        <Button @click="showFilters = !showFilters" severity="secondary"><Filter class="w-5 h-5" /></Button>
       <Link href="/orders/create"  as="Button" class="p-button p-component p-button-contrast"><Plus /> Додати замовлення</Link>
      </div>
    </div>
    <div v-if="showFilters" class="border border-top p-3 border-[#eee]">
        <div class="mb-4 grid grid-cols-7 gap-3">
          <input
            type="text"
            v-model="filters.id"
            class="border border-gray-300 p-2 rounded"
            placeholder="Пошук за ID"
          />
          <input
            type="text"
            v-model="filters.delivery_fullname"
            class="border border-gray-300 p-2 rounded"
            placeholder="Ім'я"
          />
          <input
            type="text"
            v-model="filters.phone"
            class="border border-gray-300 p-2 rounded"
            placeholder="Телефон"
          />
          <input
            type="text"
            v-model="filters.ip"
            class="border border-gray-300 p-2 rounded"
            placeholder="IP"
          />
          <input
            type="text"
            v-model="filters.email"
            class="border border-gray-300 p-2 rounded"
            placeholder="Пошта"
          />
          <Button class="text-xs" @click="loadOrders"><Search class="w-5 h-5"/> Пошук</Button>
          <Button class="text-xs" @click="resetFilters"><FilterX class="w-5 h-5"/>Скинути</Button>
        </div>
     
      </div>


      <div class="flex gap-3 my-4" v-if="selectedProduct[0]">
          <!-- Кнопка для массового удаления -->
          <Button 
            label="Видалити" 
            icon="pi pi-trash" 
            severity="danger" 
            class="p-button-rounded"
            @click="triggerMassDelete($event)"
          />

          <!-- Выбор статуса -->
          <Select 
            v-model="selectedStatus" 
            :options="statuses.map(s => ({ label: s.name, value: s.id }))" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Оберіть статус" 
            class="w-full md:w-56"
          />

          <!-- Кнопка для подтверждения изменения статуса -->
          <Button 
            label="Редагувати статус" 
            severity="success" 
            class="p-button-rounded"
            @click="triggerMassUpdateStatus($event, selectedStatus)"
          />

          <!-- Кнопка для изменения комментариев -->
          <Button 
            label="Редагувати коментар" 
            icon="pi pi-comment" 
            severity="secondary" 
            class="p-button-rounded"
            @click="commentDialog = true" 
          />

        </div>

      <Dialog v-model:visible="commentDialog" header="Редагувати коментар" modal>
        <template #default>
          <textarea v-model="actionData" rows="3" class="w-full border rounded p-2"></textarea>
          <Button 
            class="mt-4" 
            label="Редагувати коментар" 
            icon="pi pi-check" 
            severity="success" 
            @click="triggerMassUpdateComment($event, actionData)" 
          />
        </template>
      </Dialog>


    <DataTable
  
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
      dataKey="id"
      scrollable 
      @row-dblclick="openOrderDialog"
      size="small"
      :class="{ 'blur-sm pointer-events-none': isLoading }"
    >
    <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
      <Column field="id" header="ID" sortable  />
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

      <Column field="delivery_fullname" header="Контакт" sortable  />
      <Column field="phone" header="Телефон" sortable  />
      <Column field="email" header="Email" sortable  />
      <Column field="comment" header="Коментар" />
      <Column  header="Товари">
        <template #body="{ data }">
          <div v-for="item in data.items" :key="item.id">
              <div class=" text-xs">
                <span v-if="item.product_id">{{
                  item.product.name
                }}</span>
                <span v-else-if="item.product_variation_id">
                  {{
                    item.product_variation.product.name
                  }}</span>
                <span v-else>Товар не знайдено...</span>
                  
                <span v-if="item.product_variation_id">
                  | {{
                    formatVariationName(
                      item.product_variation
                    )
                  }}
                </span>
               
                 |  x{{ item.quantity }}
      
              
                
                 | {{ item.price }}
                 

              </div>
            </div>
        </template>
      </Column>

      <Column field="responsible_user.name" header="Відповідальний"/>

      <Column field="delivery_city" header="Місто" sortable  />
      <Column field="delivery_address" header="Адреса" />
      <Column field="delivery_postcode" header="Зіп код" />
      
      <Column field="payment_method.name" header="Метод оплати" />
      <Column class="w-[40px]" header="Оплата">
        <template #body="{ data }">
            <span v-if="data.is_paid"
            class="rounded flex items-center justify-center p-1 text-white text-xs bg-green-500" >
            Оплачено
            </span>
            <span v-else
            class="rounded flex items-center justify-center p-1 text-white bg-black text-xs"
            >
             Не оплачено
            </span>
        </template>
      </Column>
      <Column field="delivery_method.name" header="Доставка" />
      <Column field="tracking_number" header="Трекинг" />

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

      <Column header="created_at" sortable >
        <template #body="{ data }">
          {{ formatDateTime(data.created_at) }}
        </template>
      </Column>

      <Column header="updated_at" sortable >
        <template #body="{ data }">
          {{ formatDateTime(data.updated_at) }}
        </template>
      </Column>
      
      <Column header="Действия" class="w-[150px]">
        <template #body="{ data }">
          <Button size="small" @click="viewOrder(data.id)"><Pencil class="w-5 h-5"/> Редагувати</Button>
        </template>
      </Column>
    </DataTable>



    <Dialog 
    v-model:visible="visible" maximizable modal header="Деталі замовлення"
    :style="{ width: '100rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
  >
    <div v-if="selectedOrder">

 
      <!-- Основная информация -->
      <div class=" bg-[#eee] rounded py-5 px-2 text-normal border-b ">
        <div class="grid grid-cols-3 gap-4 justify-items-center items-center">
          
          

            <p><strong class="mr-2">Статус замовлення:</strong>
            <span v-if="selectedOrder.status"
            class="rounded  p-1 text-white text-xs"
            :style="{ backgroundColor: `#${selectedOrder.status.color}` }">
            {{ selectedOrder.status?.name }}
            </span>
            <span v-else
            class="rounded p-1 text-white bg-black text-xs"
            >
            Без статусу
            </span>
           </p>
            <p><strong>Трекінг Номер:</strong> {{ selectedOrder.tracking_number || '-' }}</p>
            <Button size="small" @click="viewOrder(selectedOrder.id)"><Pencil class="w-5 h-5"/> Редагувати замовлення</Button>
        </div>
      </div>

      <!-- Доставка -->
      <div class="text-base p-5 bg-[#f1f5f9]">
        <div class="grid grid-cols-5 gap-4 ">
          <p><strong>Ім'я:</strong> {{ selectedOrder.delivery_fullname }}</p>
          <p><strong>Phone:</strong> {{ selectedOrder.phone }}</p>
          <p><strong>Місто:</strong> {{ selectedOrder.delivery_city }}</p>
          <p><strong>ЗІП код:</strong> {{ selectedOrder.delivery_postcode }}</p>
          <p><strong>Адреса:</strong> {{ selectedOrder.delivery_address }}</p>
          <p><strong>Доп. адреса:</strong> {{ selectedOrder.delivery_second_address }}</p>
          
          
          <p><strong>Метод доставки:</strong> {{ selectedOrder.delivery_method?.name }}</p>
          <p><strong>Метод оплати:</strong> {{ selectedOrder.payment_method?.name }}</p>
          <p><strong>Email:</strong> {{ selectedOrder.email }}</p>
          <p><strong>Комент:</strong> {{ selectedOrder.comment || 'N/A' }}</p>
          <p><strong>Відповідальний:</strong> {{ selectedOrder.responsible_user?.name }}</p> 
          
        </div>
      </div>




      



      
        <table class="table-auto w-full border-collapse border border-gray-300 my-5">
          <thead>
            <tr>
              <th class="border border-gray-300 p-2">Назва</th>
              <th class="border border-gray-300 p-2">Атрибути</th>
              <th class="border border-gray-300 p-2">
                Кількість
              </th>
              <th class="border border-gray-300 p-2">Ціна</th>
              <th class="border border-gray-300 p-2">Сума</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in selectedOrder.items" :key="item.id">
              <td class="border border-gray-300 p-2">
                <span v-if="item.product_id">{{
                  item.product.name
                }}</span>
                <span v-else-if="item.product_variation_id">
                  {{
                    item.product_variation.product.name
                  }}</span>
                <span v-else>Товар не знайдено...</span>
              </td>
              <td class="border border-gray-300 p-2">
                <span v-if="item.product_variation_id">
                  {{
                    formatVariationName(
                      item.product_variation
                    )
                  }}
                </span>
                <span v-else> - </span>
              </td>
              <td class="border border-gray-300 p-2">
              
                    {{ item.quantity }}
      
              </td>
              <td class="border border-gray-300 p-2">
                
                    {{ item.price }}
                 

              </td>
              <td class="border border-gray-300 p-2">
                {{ formatCurrency(item.quantity * item.price) }}
              </td>
              
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="border border-gray-300 p-2 font-bold text-right">
                Загальна сума:
              </td>
              <td class="border border-gray-300 p-2 font-bold">
                {{ formatCurrency(totalAmount(selectedOrder.items)) }}
              </td>
            </tr>
          </tfoot>
        </table>
     
      <!-- UTM-метки -->
      <div class="text-base p-5 bg-[#f1f5f9]">
        <div class="grid grid-cols-5 gap-4 mt-2">
          <p><strong>UTM Source:</strong> {{ selectedOrder.utm_source || 'N/A' }}</p>
          <p><strong>UTM Medium:</strong> {{ selectedOrder.utm_medium || 'N/A' }}</p>
          <p><strong>UTM Term:</strong> {{ selectedOrder.utm_term || 'N/A' }}</p>
          <p><strong>UTM Content:</strong> {{ selectedOrder.utm_content || 'N/A' }}</p>
          <p><strong>UTM Campaign:</strong> {{ selectedOrder.utm_campaign || 'N/A' }}</p>
          <p><strong>IP Address:</strong> {{ selectedOrder.ip }}</p>
          <p><strong>Website Reffer:</strong> {{ selectedOrder.website_referrer }}</p>
          
        </div>
      </div>

      <!-- Основная информация -->
      <div class="border-b bg-[#eee] rounded-sm p-2 text-normal">
        <div class="grid grid-cols-2 gap-4 mt-2 justify-items-center">
          <p><strong>Замовлення створено:</strong> {{ formatDateTime(selectedOrder.created_at) }}</p>
          <p><strong>Замовлення оновлено:</strong> {{ formatDateTime(selectedOrder.updated_at) }}</p>
        </div>
      </div>

 
    </div>
  </Dialog>


  </Layout>
</template>

<style>
tbody {
  white-space: nowrap;
}
</style>