<script setup>
import { ref, onMounted, computed } from 'vue';
import Layout from '../../Layout/App.vue';
import { usePage, Head, router, Link } from '@inertiajs/vue3';
import { DataTable, Column, Button } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { Plus, Pencil, Filter, FilterX, Search, RefreshCcw, Copy, Trash, RefreshCw, MessageCircleMore } from 'lucide-vue-next';
import { useConfirm } from "primevue/useconfirm";
import { lockedOrders } from '../../ably'; // Импортируем список заблокированных заказов

axios.defaults.withCredentials = true;

const users = ref([]);
const payment_methods = ref([]);

const page = usePage();
const toast = useToast();
const confirm = useConfirm();

const frozens = ref({
  'utm_source': false,
  'group': false
});

const filters = ref({
  id: "",
  order_status_id: null,
  delivery_fullname: "",
  phone: "",
  email: "",
  comment: "",
  responsible_user_id: null,
  delivery_city: "",
  payment_method_id: null,
  is_paid: "",
  ip: "",
  delivery_method_id: null,
  created_at: null,
  updated_at: null
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

  // Убираем пустые параметры фильтрации
  const activeFilters = Object.fromEntries(
    Object.entries(filters.value).filter(([_, v]) => v !== "" && v !== null)
  );

  router.get(
    fetchRoute,
    {
      page: currentPage.value,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
      order_status_id: currentStatusId.value,
      ...activeFilters, // Передаем только активные фильтры
    },
    {
      preserveState: true,
      onSuccess: (page) => {
        fetchLockedOrdersInIndex();
        orders.value = page.props.data;
      },
      onFinish: () => {
        isLoading.value = false;
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
    comment: "",
    responsible_user_id: "",
    delivery_city: "",
    payment_method_id: "",
    is_paid: "",
  };
  selectedProduct.value = []
  sortBy.value = "created_at";
  sortDirection.value = "desc";
  currentStatusId.value = "";
  loadOrders();
};

onMounted(() => {
  fetchLockedOrdersInIndex();
  filters.value = {
    id: inertiaProps.filters.id || "",
    delivery_fullname: inertiaProps.filters.delivery_fullname || "",
    phone: inertiaProps.filters.phone || "",
    ip: inertiaProps.filters.ip || "",
    email: inertiaProps.filters.email || "",
    comment: inertiaProps.filters.comment || "",
    responsible_user_id: inertiaProps.filters.responsible_user_id || "",
    delivery_city: inertiaProps.filters.delivery_city || "",
    payment_method_id: inertiaProps.filters.payment_method_id || "",
    is_paid: inertiaProps.filters.is_paid || "",
    
  };
});




const viewOrder = (orderId) => {
  if (lockedOrders.value.has(orderId)) {
    alert('🚫 Це замовлення вже відкрито іншим менеджером!');
    return;
  }
  router.get(`/orders/${orderId}`);
};




const selectedProduct = ref([]);

const formatDateTime = (date) => {
  if (!date) return "-";

  return new Intl.DateTimeFormat("pl-PL", {
    timeZone: "Europe/Warsaw",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false
  }).format(new Date(date));
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
          selectedProduct.value = [];
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
        order_status_id: selectedStatus.value
      }, {
        onSuccess: () => {
          selectedProduct.value = [];
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
          selectedProduct.value = [];
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
const rowClass = (data) => {
  return lockedOrders.value.has(data.id) ? 'locked-row' : ''; // Если заказ заблокирован, применяем стиль
};


const fetchLockedOrdersInIndex = async () => {
  try {
    lockedOrders.value = new Set();
    const response = await axios.get('/orders/locked');
    response.data.lockedOrders.forEach(orderId => addLockedOrder(orderId));
    console.log(lockedOrders.value);
  } catch (error) {
    console.error('Ошибка загрузки заблокированных заказов:', error);
  }
};

const addLockedOrder = (orderId) => {
  lockedOrders.value = new Set([...lockedOrders.value, orderId]); // Создаем новый Set
};

const duplicateOrder = (orderId) => {
  if (!selectedProduct.value.length) {
    toast.add({ severity: 'warn', summary: 'Ошибка', detail: 'Выберите хотя бы один заказ.', life: 3000, });
    return;
  }
  confirm.require({
    message: "Ви дійсно хочете дублювати це замовлення?",
    target: event.currentTarget,
    accept: () => {
      router.post(`/orders/${orderId}/duplicate`, {}, {
        onSuccess: () => {
          selectedProduct.value = [];
          toast.add({ severity: 'success', summary: 'Успіх!', detail: 'Замовлення продубльовано!', life: 3000 });
          loadOrders();
        },
        onError: () => {
          toast.add({ severity: 'error', summary: 'Помилка', detail: 'Помилка дублювання замовлення.', life: 3000 });
        },
      });
    },
    reject: () => { }
  });
};


const loadUsers = () => {
  if (users.value.length > 0) {
    return;
  }
  axios.get('/users/getall').then(response => {
    users.value = response.data.users;
  });
};

const loadPaymentMethods = () => {
  if (payment_methods.value.length > 0) {
    return;
  }
  axios.get('/payment-methods/getall').then(response => {
    payment_methods.value = response.data.payment_methods;
  });
};

const getTooltipText = (items) => {
  return items.map(item => {
    const productName = item.product?.name || item.product_variation?.product?.name || "Товар не знайдено";
    const variationName = item.product_variation ? ` | ${formatVariationName(item.product_variation)}` : "";
    return `<span>${productName}${variationName} | x${item.quantity} | ${item.price}</span>`;
  }).join("\n");
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
      <div class="rounded p-2 text-white min-w-[150px] bg-[#020617] cursor-pointer hover:scale-105 hover:shadow-sm"
        :class="{ 'font-medium': !currentStatusId }" @click="filterByStatus(null)">
        Всі замовлення
      </div>
      <div v-for="status in statuses" :key="status.id"
        class="rounded p-2 text-white min-w-[150px] cursor-pointer hover:scale-105 hover:shadow-sm"
        :class="{ 'font-medium': currentStatusId === status.id }" :style="{ backgroundColor: `#${status.color}` }"
        @click="filterByStatus(status.id)">
        {{ status.name }} ({{ status.orders_count }})
      </div>
    </div>
    <div class="flex justify-between items-center my-4 gap-3">

      <Toolbar class="w-full">
        <template #start>
          <Button @click="loadOrders" outlined :disabled="isLoading">
            <RefreshCcw class="w-5 h-5 transition-transform duration-500 ease-in-out"
              :class="{ 'animate-spin': isLoading }" />
          </Button>




          <Button class=" ml-3" outlined @click="selectedProduct = []">Вибрано: <b>{{ selectedProduct.length
          }}</b></Button>

          <!-- Дублирование заказа -->
          <Button class=" ml-3" severity="secondary" v-if="selectedProduct.length === 1"
            @click="duplicateOrder(selectedProduct[0].id)">
            <Copy class="w-5 h-5" /> Дублювати
          </Button>

          <!-- Массовое удаление -->
          <Button severity="secondary" class=" ml-3" v-if="selectedProduct.length > 0"
            @click="triggerMassDelete($event)">
            <Trash class="w-5 h-5" /> Видалити
          </Button>

          <!-- Кнопка редактирования комментария -->
          <Button severity="secondary" class=" ml-3" v-if="selectedProduct.length > 0" @click="commentDialog = true">
            <MessageCircleMore class="w-5 h-5" /> Редагувати коментар
          </Button>
        </template>

        <template #center>
          <Select v-model="selectedStatus" v-if="selectedProduct.length > 0"
            :options="statuses.map(s => ({ label: s.name, value: s.id }))" optionLabel="label" optionValue="value"
            placeholder="Змінити статус" class="w-56" />
          <Button severity="secondary" class=" ml-3" v-if="selectedProduct.length > 0 && selectedStatus"
            @click="triggerMassUpdateStatus($event, selectedStatus)">
            <RefreshCw class="w-5 h-5" /> Змінити статус
          </Button>

        </template>

        <template #end>
          <!-- Выпадающее меню для редактирования -->

          <Button severity="secondary" @click="resetFilters">
            <FilterX class="w-5 h-5" /> Скинути фільтри
          </Button>

          <Button class="ml-3" @click="loadOrders">
            <Search class="w-5 h-5" /> Пошук
          </Button>

          <Link href="/orders/create" as="Button" class="p-button p-component p-button-contrast ml-3">
          <Plus class="w-5 h-5" /> Додати </Link>
        </template>
      </Toolbar>
    </div>



    <Dialog v-model:visible="commentDialog" header="Редагувати коментар" modal>
      <template #default>
        <textarea v-model="actionData" rows="3" class="w-full border rounded p-2"></textarea>
        <Button class="mt-4" label="Редагувати коментар" icon="pi pi-check" severity="success"
          @click="triggerMassUpdateComment($event, actionData)" />
      </template>
    </Dialog>


    <DataTable v-model:selection="selectedProduct" :value="orders.data" resizableColumns columnResizeMode="expand"
      showGridlines :paginator="true" :rows="perPage" :rows-per-page-options="[10, 20, 50, 100]"
      :first="(currentPage - 1) * perPage" :total-records="orders.total" :lazy="true" :sort-field="sortBy"
      :sort-order="sortDirection === 'asc' ? 1 : -1" @page="onPageChange" @sort="onSortChange" dataKey="id" scrollable
      @row-dblclick="openOrderDialog" size="small" filterDisplay="row" selectionMode="multiple" 
      :class="{ 'blur-sm pointer-events-none': isLoading }" :rowClass="rowClass">
      <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
      <Column field="id" header="ID" sortable :showFilterMenu="false" bodyStyle="text-align:center" style="min-width:50px;">
        <template #filter>
          <InputText v-model="filters.id" placeholder="ID" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" class="w-[40px]" header="Статус" sortField="order_status_id" :sortable="true">
        <template #body="{ data }">
          <span v-if="data.status" class="rounded flex items-center justify-center p-1 text-white text-xs"
            :style="{ backgroundColor: `#${data.status.color}` }">
            {{ data.status.name }}
          </span>
          <span v-else class="rounded flex items-center justify-center p-1 text-white bg-black text-xs">
            Без статусу
          </span>
        </template>
        <template #filter>
          <Select v-model="filters.order_status_id" optionValue="id" :options="statuses" optionLabel="name"
            :showClear="true" placeholder="Статус" class="w-full" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="delivery_fullname" header="Контакт" sortable>
        <template #filter>
          <InputText v-model="filters.delivery_fullname" placeholder="Ім'я або Фамілія" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="phone" header="Телефон" sortable>
        <template #filter>
          <InputText v-model="filters.phone" placeholder="Телефон" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="email" header="Email" sortable>
        <template #filter>
          <InputText v-model="filters.email" placeholder="Email" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="comment" header="Коментар" bodyClass="cursor-help"
        bodyStyle="max-width:250px">
        <template #filter>
          <InputText v-model="filters.comment" placeholder="Коментар" class="w-full" />
        </template>
        <template #body="{ data }">
          <div class="w-full h-full truncate" v-tooltip.top="{ value: data.comment, showDelay: 1000, hideDelay: 300, class: 'text-sm' }">{{
            data.comment }}</div>
        </template>
      </Column>

      <Column :showFilterMenu="false" header="Товари" bodyStyle="max-width:200px">
        <template #body="{ data }">
          <div v-if="data.items.length > 0" v-tooltip.top="{ value: getTooltipText(data.items), showDelay: 500, hideDelay: 300, escape:false, class: 'text-sm custom-tooltip ',  }">
            <!-- Первый товар -->
            <div class="text-sm truncate">
              <span v-if="data.items[0].product_id">
                {{ data.items[0].product.name }}
              </span>
              <span v-else-if="data.items[0].product_variation_id">
                {{ data.items[0].product_variation.product.name }}
              </span>
              <span v-else>Товар не знайдено...</span>

              <span v-if="data.items[0].product_variation_id">
                | {{ formatVariationName(data.items[0].product_variation) }}
              </span>

              | x{{ data.items[0].quantity }}
              | {{ data.items[0].price }}
            </div>

           
          </div>
        </template>
      </Column>

      <Column :showFilterMenu="false" field="responsible_user.name" header="Відповідальний">
        <template #filter>
          <Select v-model="filters.responsible_user_id" @click="loadUsers" :options="users" :showClear="true"
            optionLabel="name" optionValue="id" placeholder="Відповідальний" class="w-full" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="delivery_city" header="Місто" sortable>
        <template #filter>
          <InputText v-model="filters.delivery_city" placeholder="Місто" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="delivery_address" header="Адреса" />
      <Column :showFilterMenu="false" field="delivery_postcode" header="Зіп код" />

      <Column :showFilterMenu="false" field="payment_method.name" header="Метод оплати">
        <template #filter>
          <Select v-model="filters.payment_method_id" @click="loadPaymentMethods" :showClear="true"
            :options="payment_methods" optionLabel="name" optionValue="id" placeholder="Метод оплати"
            class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" class="w-[40px]" header="Оплата" sortable field="is_paid">
        <template #filter>
          <Select optionLabel="label" optionValue="value" class="w-full" v-model="filters.is_paid" :showClear="true" placeholder="Оплата"
            :options="[
                { label: 'Так', value: 1 },
                { label: 'Ні', value: 0 },
            ]" />
   
        </template>
        <template #body="{ data }">
          <span v-if="data.is_paid"
            class="rounded flex items-center justify-center p-1 text-white text-xs bg-green-500">
            Оплачено
          </span>
          <span v-else class="rounded flex items-center justify-center p-1 text-white bg-black text-xs">
            Не оплачено
          </span>
        </template>
      </Column>
      <Column :showFilterMenu="false" field="delivery_method.name" header="Доставка" />
      <Column :showFilterMenu="false" field="tracking_number" header="Трекинг" />

      <Column :showFilterMenu="false" field="group.name" header="Група" alignFrozen="right" :frozen="frozens.group">
        <template #header>
          <ToggleButton v-model="frozens.group" onLabel="-" offLabel="+" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="ip" header="IP" />
      <Column :showFilterMenu="false" field="website_referrer" header="Website Reffer" />

      <Column :showFilterMenu="false" field="utm_source" header="utm_source" alignFrozen="right"
        :frozen="frozens.utm_source">
        <template #header>
          <ToggleButton v-model="frozens.utm_source" onLabel="-" offLabel="+" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="utm_medium" header="utm_medium" alignFrozen="right"
        :frozen="frozens.utm_medium" />
      <Column :showFilterMenu="false" field="utm_campaign" header="utm_campaign" />
      <Column :showFilterMenu="false" field="utm_content" header="utm_content" />
      <Column :showFilterMenu="false" field="utm_term" header="utm_term" />

      <Column :showFilterMenu="false" header="created_at" sortable>
        <template #body="{ data }">
          {{ formatDateTime(data.created_at) }}
        </template>
      </Column>

      <Column :showFilterMenu="false" header="updated_at" sortable>
        <template #body="{ data }">
          {{ formatDateTime(data.updated_at) }}
        </template>
      </Column>

      <Column header="Действия" class="w-[150px]">
        <template #body="{ data }">
          <Button size="small" @click="viewOrder(data.id)">
            <Pencil class="w-5 h-5" /> Редагувати
          </Button>
        </template>
      </Column>
    </DataTable>



    <Dialog v-model:visible="visible" maximizable modal header="Деталі замовлення" :style="{ width: '100rem' }"
      :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
      <div v-if="selectedOrder">


        <!-- Основная информация -->
        <div class=" bg-[#eee] rounded py-5 px-2 text-normal border-b ">
          <div class="flex gap-4 justify-items-center items-center text-left text-sm mb-3">


            <p class="w-1/12"><strong>ID:</strong><br> {{ selectedOrder.id || '-' }}</p>

            <p class="w-2/12"><strong>ТТН:</strong><br> {{ selectedOrder.tracking_number || '-' }}</p>
            <p class="w-2/12"><strong>Зворотна ТТН:</strong><br> {{ selectedOrder.return_tracking_number || '-' }}</p>
            <p class="w-2/12"><strong>Статус замовлення:</strong><br>
            <div v-if="selectedOrder.status" class="rounded  p-1 text-white text-xs max-w-[100px] text-center"
              :style="{ backgroundColor: `#${selectedOrder.status.color}` }">
              {{ selectedOrder.status?.name }}
            </div>
            <span v-else class="rounded p-1 text-white bg-black text-xs">
              Без статусу
            </span>
            </p>
            <p class="w-2/12"><strong>Статус Inpost:</strong><br>
              <span v-if="selectedOrder.inpost_status" class="rounded p-1 text-white bg-black text-xs">
                {{ selectedOrder.inpost_status }}
              </span>
              <span v-else class="rounded p-1 text-white bg-black text-xs">
                Без статусу
              </span>
            </p>
            <div class="w-3/12 text-center">
              <Button size="small" @click="viewOrder(selectedOrder.id)">
                <Pencil class="w-5 h-5" /> Редагувати замовлення
              </Button>
            </div>
          </div>
          <hr>
          <div class="grid grid-cols-4 gap-4 justify-center items-center text-sm text-center mt-3">
            <p><strong>Метод оплати:</strong> {{ selectedOrder.payment_method?.name }}</p>
            <p><strong>Оплачено:</strong> {{ selectedOrder.is_paid ? 'Так' : 'Ні' }}</p>
            <p><strong>Дата онлайн оплати:</strong> {{ formatDateTime(selectedOrder.payment_date) }}</p>
            <p><strong>Сума оплати:</strong> {{ selectedOrder.paid_amount || 0 }}</p>
          </div>
        </div>

        <!-- Доставка -->
        <div class="text-sm p-5 bg-[#f1f5f9] ">
          <div class="grid grid-cols-5 gap-4 ">
            <p><strong>Ім'я:</strong> {{ selectedOrder.delivery_fullname }}</p>
            <p><strong>Phone:</strong> {{ selectedOrder.phone }}</p>
            <p><strong>Місто:</strong> {{ selectedOrder.delivery_city }}</p>
            <p><strong>ЗІП код:</strong> {{ selectedOrder.delivery_postcode }}</p>
            <p><strong>Адреса:</strong> {{ selectedOrder.delivery_address }}</p>
            <p><strong>Доп. адреса:</strong> {{ selectedOrder.delivery_second_address }}</p>


            <p><strong>Метод доставки:</strong> {{ selectedOrder.delivery_method?.name }}</p>
            <p><strong>Email:</strong> {{ selectedOrder.email }}</p>
            <p><strong>Комент:</strong> {{ selectedOrder.comment || 'N/A' }}</p>
            <p><strong>Відповідальний:</strong> {{ selectedOrder.responsible_user?.name }}</p>
            <p><strong>Група:</strong> {{ selectedOrder.group?.name }}</p>

          </div>
        </div>









        <table class="table-auto w-full border-collapse border border-gray-300 my-5 text-sm">
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
        <div class="text-sm p-5 bg-[#f1f5f9]">
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
          <div class="grid grid-cols-3 gap-4 mt-2 justify-items-center">
            <p><strong>Замовлення створено:</strong> {{ formatDateTime(selectedOrder.created_at) }}</p>
            <p><strong>Замовлення оновлено:</strong> {{ formatDateTime(selectedOrder.updated_at) }}</p>
            <p><strong>Дата отримання клієнтом:</strong> {{ formatDateTime(selectedOrder.delivery_date) }}</p>

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

.locked-row {
  opacity: 0.5;
  /* Затемнение */
  pointer-events: none;
  /* Отключение кликов */
}
.custom-tooltip {
  max-width: 600px !important;  /* Делаем тултип шире */
  white-space: nowrap !important;  /* Запрещаем перенос строк */
  overflow: hidden !important;  /* Скрываем лишний текст */
  text-overflow: ellipsis !important;  /* Добавляем многоточие, если текст не влезает */
}
.p-datatable-tbody > tr.p-datatable-row-selected {
  background: #000!important;
  color: #fff!important;
}


</style>