<script setup>
import { ref, onMounted, computed } from 'vue';
import Layout from '../../Layout/App.vue';
import { usePage, Head, router, Link } from '@inertiajs/vue3';
import { DataTable, Column, Button } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { Plus, Pencil, Filter, FilterX, Search, RefreshCcw, Copy, Trash, RefreshCw, MessageCircleMore} from 'lucide-vue-next';
import { useConfirm } from "primevue/useconfirm";
import { lockedOrders } from '../../ably'; // Импортируем список заблокированных заказов

axios.defaults.withCredentials = true;

const users = ref([]);
const payment_methods = ref([]);
const delivery_methods = ref([]);
const groups = ref([]);
const products = ref([]);
const variations = ref([]);

const page = usePage();
const toast = useToast();
const confirm = useConfirm();


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
  delivery_method_id: null,
  tracking_number: "",
  delivery_date: null,
  sent_at: null,
  group_id: null,
  ip: "",
  website_referrer: "",
  utm_source: "",
  utm_medium: "",
  utm_campaign: "",
  utm_content: "",
  utm_term: "",
  created_at: null,
  updated_at: null
});


const { props: inertiaProps } = usePage();
console.log(inertiaProps);
const orders = ref(inertiaProps.data || []);
const statuses = inertiaProps.statuses || [];
const isLoading = ref(false);
const fetchRoute = "/orders";
const perPage = ref(orders.value.per_page || 10);
const currentPage = ref(orders.value.current_page || 1);
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const visible = ref(false);
const selectedOrder = ref(null);

const selectedStatus = ref(null); // Храним выбранный статус
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

const formatDateForApi = (date) => {
  if (!date) return null;

  // Если дата — это строка, преобразуем её в объект Date
  if (typeof date === "string") {
    date = new Date(date.replace(" ", "T"));
  }

  // Проверяем, является ли дата объектом Date
  if (!(date instanceof Date) || isNaN(date.getTime())) {
    throw new TypeError("Invalid date provided");
  }

  const pad = (num) => String(num).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
    date.getDate()
  )}`;
};


const loadOrders = () => {
  isLoading.value = true;

  let activeFilters = { ...filters.value };
  delete activeFilters.created_at;
  delete activeFilters.updated_at;
  delete activeFilters.sent_at;
  delete activeFilters.delivery_date;

  // Если даты выбраны, форматируем их в Y-m-d
  if (filters.value.created_at?.length === 2) {
    activeFilters.created_at_from = formatDateForApi(filters.value.created_at[0]);
    activeFilters.created_at_to = formatDateForApi(filters.value.created_at[1]);
  }

  if (filters.value.updated_at?.length === 2) {
    activeFilters.updated_at_from = formatDateForApi(filters.value.updated_at[0]);
    activeFilters.updated_at_to = formatDateForApi(filters.value.updated_at[1]);
  }

  if (filters.value.sent_at?.length === 2) {
    activeFilters.sent_at_from = formatDateForApi(filters.value.sent_at[0]);
    activeFilters.sent_at_to = formatDateForApi(filters.value.sent_at[1]);
  }

  if (filters.value.delivery_date?.length === 2) {
    activeFilters.delivery_date_from = formatDateForApi(filters.value.delivery_date[0]);
    activeFilters.delivery_date_to = formatDateForApi(filters.value.delivery_date[1]);
  }

  // Убираем пустые параметры
  activeFilters = Object.fromEntries(Object.entries(activeFilters).filter(([_, v]) => v !== "" && v !== null));

  router.get(
    fetchRoute,
    {
      ...activeFilters,
      per_page: perPage.value,
      page: currentPage.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
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
    email: "",
    comment: "",
    responsible_user_id: "",
    delivery_city: "",
    payment_method_id: "",
    is_paid: "",
    delivery_method_id: "",
    tracking_number: "",
    group_id: "",
    ip: "",
    website_referrer: "",
    utm_source: "",
    utm_medium: "",
    utm_campaign: "",
    utm_content: "",
    utm_term: "",
  };
  selectedProduct.value = []
  sortBy.value = "created_at";
  sortDirection.value = "desc";
  loadOrders();
};

onMounted(() => {
  fetchLockedOrdersInIndex();
  filters.value = {
    id: inertiaProps.filters.id || "",
    delivery_fullname: inertiaProps.filters.delivery_fullname || "",
    phone: inertiaProps.filters.phone || "",
    email: inertiaProps.filters.email || "",
    comment: inertiaProps.filters.comment || "",
    responsible_user_id: inertiaProps.filters.responsible_user_id || "",
    delivery_city: inertiaProps.filters.delivery_city || "",
    payment_method_id: inertiaProps.filters.payment_method_id || "",
    is_paid: inertiaProps.filters.is_paid || "",
    delivery_method_id: inertiaProps.filters.delivery_method_id || "",
    tracking_number: inertiaProps.filters.tracking_number || "",
    group_id: inertiaProps.filters.group_id || "",
    ip: inertiaProps.filters.ip || "",
    website_referrer: inertiaProps.filters.website_referrer || "",
    utm_source: inertiaProps.filters.utm_source || "",
    utm_medium: inertiaProps.filters.utm_medium || "",
    utm_campaign: inertiaProps.filters.utm_campaign || "",
    utm_content: inertiaProps.filters.utm_content || "",
    utm_term: inertiaProps.filters.utm_term || "",
    delivery_date: inertiaProps.filters.delivery_date || "",
    sent_at: inertiaProps.filters.sent_at || "",

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
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false
  }).format(new Date(date));
};


const filterByStatus = (statusId) => {
  filters.value.order_status_id = statusId;
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
const triggerMassDelete = async (event) => {
  if (!selectedProduct.value.length) {
    toast.add({
      severity: 'warn',
      summary: 'Ошибка',
      detail: 'Выберите хотя бы один заказ.',
      life: 3000,
    });
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
    accept: async () => {
      try {
        await axios.post('/orders/mass-delete', {
          order_ids: selectedProduct.value.map(o => o.id),
        });

        selectedProduct.value = [];
        toast.add({
          severity: 'success',
          summary: 'Успіх!',
          detail: 'Замовлення успішно видалені!',
          life: 3000,
        });

        loadOrders(); // Перезагрузка заказов
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: 'Помилка',
          detail: error.response?.data?.message || 'Помилка видалення замовлень.',
          life: 3000,
        });
      }
    },
  });
};


const triggerMassUpdateStatus = async (event) => {
  if (!selectedProduct.value.length || !selectedStatus.value) {
    toast.add({
      severity: 'warn',
      summary: 'Ошибка',
      detail: 'Выберите заказы и статус.',
      life: 3000,
    });
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
    accept: async () => {
      try {
        await axios.post('/orders/mass-update-status', {
          order_ids: selectedProduct.value.map(o => o.id),
          status_id: selectedStatus.value
        });

        toast.add({
          severity: 'success',
          summary: 'Успішно!',
          detail: 'Статуси оновлено.',
          life: 3000,
        });

        selectedProduct.value = [];
        selectedStatus.value = null;
        loadOrders(); // Перезагрузка заказов
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: 'Помилка',
          detail: error.response?.data?.message || 'Помилка оновлення статусів.',
          life: 3000,
        });
      }
    },
  });
};


// Триггер для массового изменения комментариев
const triggerMassUpdateComment = async (event, comment) => {
  if (!selectedProduct.value.length) {
    toast.add({
      severity: 'warn',
      summary: 'Ошибка',
      detail: 'Выберите хотя бы один заказ.',
      life: 3000,
    });
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
    accept: async () => {
      try {
        await axios.post('/orders/mass-update-comment', {
          order_ids: selectedProduct.value.map(o => o.id),
          comment
        });

        commentDialog.value = false;
        toast.add({
          severity: 'success',
          summary: 'Успіх!',
          detail: 'Коментар оновлено!',
          life: 3000,
        });

        loadOrders(); // Перезагрузка заказов
      } catch (error) {
        commentDialog.value = false;
        toast.add({
          severity: 'error',
          summary: 'Помилка!',
          detail: error.response?.data?.message || 'Помилка оновлення коментаря.',
          life: 3000,
        });
      }
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

const loadDeliveryMethods = () => {
  if (delivery_methods.value.length > 0) {
    return;
  }
  axios.get('/delivery-methods/getall').then(response => {
    delivery_methods.value = response.data.delivery_methods;
  });
};

const loadGroups = () => {
  if (groups.value.length > 0) {
    return;
  }
  axios.get('/groups/getall').then(response => {
    groups.value = response.data.groups;
  });
};


// Загружаем товары
const loadProducts = () => {
  if (products.value.length > 0) return; // Если уже загружены, не запрашиваем снова
  axios.get('/products/getall').then(response => {
    products.value = response.data.products;
  });
};

// Загружаем вариации при выборе товара
const loadVariations = () => {
  variations.value = [];
  filters.value.variation_id = null; // Сбрасываем вариацию, если товар изменился

  if (!filters.value.product_id) return; // Если товар не выбран – ничего не делаем

  axios.get(`/products/${filters.value.product_id}/variations`).then(response => {
    variations.value = response.data.variations;
  });
};


const getTooltipText = (items) => {
  return items.map(item => {
    const productName = item.product?.name || item.product_variation?.product?.name || "Товар не знайдено";
    const variationName = item.product_variation ? ` | ${formatVariationName(item.product_variation)}` : "";
    return `<span>${productName}${variationName} | x${item.quantity} | ${item.price}</span>`;
  }).join("\n");
};


const copyOrderDetails = async () => {
    let text = `Основна інформація\n`;
    text += `ID: ${selectedOrder.value.id || '-'}\n`;
    text += `ТТН: ${selectedOrder.value.tracking_number || '-'}\n`;
    text += `Зворотна ТТН: ${selectedOrder.value.return_tracking_number || '-'}\n`;
    text += `Статус замовлення: ${selectedOrder.value.status?.name || '-'}\n`;
    text += `Статус Inpost: ${selectedOrder.value.inpost_status || '-'}\n`;
    text += `Відправлено: ${formatDateTime(selectedOrder.value.sent_at || '-')}\n`;
    text += `Дата отримання: ${formatDateTime(selectedOrder.value.delivery_date) || '-'}\n`;
    text += `Група: ${selectedOrder.value.group?.name || '-'}\n`;
    text += `Відповідальний: ${selectedOrder.value.responsible_user?.name || '-'}\n`;
    text += `Коментар: ${selectedOrder.value.comment || '-'}\n\n`;

    text += `Доставка\n`;
    text += `Ім'я та Фамілія: ${selectedOrder.value.delivery_fullname || '-'}\n`;
    text += `Телефон: ${selectedOrder.value.phone || '-'}\n`;
    text += `Email: ${selectedOrder.value.email || '-'}\n`;
    text += `Місто: ${selectedOrder.value.delivery_city || '-'}\n`;
    text += `ЗІП код: ${selectedOrder.value.delivery_postcode || '-'}\n`;
    text += `Метод доставки: ${selectedOrder.value.delivery_method?.name || '-'}\n`;
    text += `Адреса: ${selectedOrder.value.delivery_address || '-'} ${selectedOrder.value.delivery_address_number || '-'}\n\n`;

    text += `Оплата\n`;
    text += `Метод оплати: ${selectedOrder.value.payment_method?.name || '-'}\n`;
    text += `Оплачено: ${selectedOrder.value.is_paid ? '✅ Так' : '❌ Ні'}\n`;
    text += `Дата онлайн оплати: ${formatDateTime(selectedOrder.value.payment_date) || '-'}\n`;
    text += `Сума оплати: ${selectedOrder.value.paid_amount || '0'}\n\n`;

    text += `Товари\n`;
    selectedOrder.value.items.forEach((item, index) => {
        const productName = item.product?.name || item.product_variation?.product?.name || 'Невідомий товар';
        const variationName = item.product_variation
            ? item.product_variation.attributes
                  .map(attr => `${attr.attribute_name}: ${attr.attribute_value}`)
                  .join(', ')
            : '';

        text += `${index + 1}. ${productName} ${variationName ? `(${variationName})` : ''} - ${item.quantity} шт. - ${formatCurrency(item.price)}\n`;
    });

    text += `\nЗагальна сума: ${formatCurrency(totalAmount(selectedOrder.value.items))}\n`;

    try {
        await navigator.clipboard.writeText(text);
        toast.add({ severity: 'success', summary: 'Скопійовано!', detail: 'Інформацію успішно скопійовано.', life: 3000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Помилка!', detail: 'Не вдалося скопіювати інформацію.', life: 3000 });
    }
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
        :class="{ 'font-medium': !filters.order_status_id }" @click="filterByStatus(null)">
        Всі замовлення
      </div>
      <div v-for="status in statuses" :key="status.id"
        class="rounded p-2 text-white min-w-[150px] cursor-pointer hover:scale-105 hover:shadow-sm"
        :class="{ 'font-medium': filters.order_status_id === status.id }" :style="{ backgroundColor: `#${status.color}` }"
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
      <Column field="id" header="ID" sortable :showFilterMenu="false" bodyStyle="text-align:center"
        style="min-width:50px;">
        <template #filter>
          <InputText v-model="filters.id" placeholder="ID" class="w-full" type="search" size="small" />
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
          <Select v-model="filters.order_status_id" optionValue="id" size="small" :options="statuses" optionLabel="name"
            :showClear="!!filters.order_status_id" filter filterPlaceholder="Пошук..." placeholder="Статус"
            class="w-full" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="delivery_fullname" header="Контакт" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.delivery_fullname" placeholder="Ім'я або Фамілія" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="phone" header="Телефон" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.phone" placeholder="Телефон" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="email" header="Email" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.email" placeholder="Email" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="comment" header="Коментар" bodyClass="cursor-help"
        bodyStyle="max-width:250px">
        <template #filter>
          <InputText type="search" v-model="filters.comment" placeholder="Коментар" class="w-full" size="small" />
        </template>
        <template #body="{ data }">
          <div class="w-full h-full truncate"
            v-tooltip.top="{ value: data.comment, showDelay: 1000, hideDelay: 300, class: 'text-sm' }">{{
              data.comment }}</div>
        </template>
      </Column>

      <Column :showFilterMenu="false" header="Товари" bodyStyle="max-width:300px">
        <template #body="{ data }">
          <div v-if="data.items.length > 0"
            v-tooltip.top="{ value: getTooltipText(data.items), showDelay: 500, hideDelay: 300, escape: false, class: 'text-sm custom-tooltip ', }">
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
        <template #filter>
          <div class="flex gap-2">
            <!-- Фильтр по товарам -->
            <Select v-model="filters.product_id" optionValue="id" :options="products"
                optionLabel="name" :showClear="!!filters.product_id" size="small" filter filterPlaceholder="Пошук..."
                placeholder="Товар" class="w-full" @click="loadProducts" @update:modelValue="loadVariations" />

            <!-- Фильтр по вариациям (загружаются после выбора товара) -->
            <Select v-model="filters.variation_id" optionValue="id" v-if="filters.product_id" :options="variations"
                optionLabel="name" :showClear="!!filters.variation_id" size="small" filter filterPlaceholder="Пошук..."
                placeholder="Варіація" class="w-full" />
              </div>
        </template>
      </Column>

      <Column :showFilterMenu="false" field="responsible_user.name" header="Відповідальний">
        <template #filter>
          <Select v-model="filters.responsible_user_id" @click="loadUsers" :options="users"
            :showClear="!!filters.responsible_user_id" size="small" filter filterPlaceholder="Пошук..." optionLabel="name"
            optionValue="id" placeholder="Відповідальний" class="w-full" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="delivery_city" header="Місто" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.delivery_city" placeholder="Місто" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="delivery_address" header="Адреса" />
      <Column :showFilterMenu="false" field="delivery_postcode" header="Зіп код" />

      <Column :showFilterMenu="false" field="payment_method.name" header="Метод оплати">
        <template #filter>
          <Select v-model="filters.payment_method_id" @click="loadPaymentMethods"
            :showClear="!!filters.payment_method_id" size="small" filter filterPlaceholder="Пошук..." :options="payment_methods"
            optionLabel="name" optionValue="id" placeholder="Метод оплати" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" class="w-[40px]" header="Оплата" sortable field="is_paid">
        <template #filter>
          <Select optionLabel="label" optionValue="value" class="w-full" v-model="filters.is_paid"
            :showClear="!!filters.is_paid" size="small" filter filterPlaceholder="Пошук..." placeholder="Оплата" :options="[
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
      <Column :showFilterMenu="false" field="delivery_method.name" header="Доставка">
        <template #filter>
          <Select v-model="filters.delivery_method_id" @click="loadDeliveryMethods"
            :showClear="!!filters.delivery_method_id" size="small" filter filterPlaceholder="Пошук..." :options="delivery_methods"
            optionLabel="name" optionValue="id" placeholder="Доставка" class="w-full" />
        </template>
      </Column>
      <Column :showFilterMenu="false" header="Дата отримання" sortable>
        <template #body="{ data }">
          {{ formatDateTime(data.delivery_date) }}
        </template>
        <template #filter>
          <DatePicker v-model="filters.delivery_date" selectionMode="range" showButtonBar :manualInput="false"
            placeholder="Виберіть діапазон" size="small" showIcon iconDisplay="input" />
        </template>
      </Column>

      <Column :showFilterMenu="false" header="Відправлено" sortable>
        <template #body="{ data }">
          {{ formatDateTime(data.sent_at) }}
        </template>
        <template #filter>
          <DatePicker v-model="filters.sent_at" selectionMode="range" showButtonBar :manualInput="false"
            placeholder="Виберіть діапазон" size="small" showIcon iconDisplay="input" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="tracking_number" header="Трекинг" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.tracking_number" placeholder="Трекинг" class="w-full" size="small" />
        </template>
      </Column>


      <Column :showFilterMenu="false" field="group.name" header="Група">
        <template #filter>
          <Select v-model="filters.group_id" @click="loadGroups" :showClear="!!filters.group_id" size="small" filter
            filterPlaceholder="Пошук..." :options="groups" optionLabel="name" optionValue="id" placeholder="Група"
            class="w-full" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="ip" header="IP" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.ip" placeholder="IP" class="w-full" size="small" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="website_referrer" header="Website Reffer" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.website_referrer" placeholder="Website Reffer" class="w-full" size="small" />
        </template>
      </Column>

      <Column :showFilterMenu="false" field="utm_source" header="utm_source" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.utm_source" placeholder="utm_source" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="utm_medium" header="utm_medium" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.utm_medium" placeholder="utm_medium" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="utm_campaign" header="utm_campaign" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.utm_campaign" placeholder="utm_campaign" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="utm_content" header="utm_content" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.utm_content" placeholder="utm_content" class="w-full" size="small" />
        </template>
      </Column>
      <Column :showFilterMenu="false" field="utm_term" header="utm_term" sortable>
        <template #filter>
          <InputText type="search" v-model="filters.utm_term" placeholder="utm_term" class="w-full" size="small" />
        </template>
      </Column>

      <Column :showFilterMenu="false" header="created_at" sortable>
        <template #body="{ data }">
          {{ formatDateTime(data.created_at) }}
        </template>
        <template #filter>
          <DatePicker v-model="filters.created_at" selectionMode="range" showButtonBar :manualInput="false"
            placeholder="Виберіть діапазон" size="small" showIcon iconDisplay="input" />
        </template>
      </Column>

      <Column :showFilterMenu="false" header="updated_at" sortable>
        <template #body="{ data }">
          {{ formatDateTime(data.updated_at) }}
        </template>
        <template #filter>
          <DatePicker v-model="filters.updated_at" selectionMode="range" showButtonBar :manualInput="false"
            placeholder="Виберіть діапазон" size="small" showIcon iconDisplay="input" />
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

    <div v-if="selectedOrder" class="space-y-3 ">

        <!-- 🟢 Основная информация -->
        <div class="bg-gray-100 rounded-lg p-4 border border-gray-300 shadow-sm">
            <div class="flex justify-between items-center mb-3">
              <h3 class="text-lg font-semibold ">📌 Основна інформація</h3>
              <div class=" text-center">
                <Button size="small" severity="secondary" @click="copyOrderDetails" class="mr-2">
                  <Copy class="w-5 h-5" /> Копіювати інформацію
                </Button>
                  <Button size="small" @click="viewOrder(selectedOrder.id)">
                      <Pencil class="w-5 h-5" /> Редагувати
                  </Button>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <p><strong>ID:</strong> {{ selectedOrder.id || '-' }}</p>
                <p><strong>ТТН:</strong> {{ selectedOrder.tracking_number || '-' }}</p>
                <p><strong>Зворотна ТТН:</strong> {{ selectedOrder.return_tracking_number || '-' }}</p>
                <p><strong>Статус замовлення: </strong>
                    <span v-if="selectedOrder.status" class="rounded p-1 text-white text-xs"
                        :style="{ backgroundColor: `#${selectedOrder.status.color}` }">
                        {{ selectedOrder.status?.name }}
                    </span>
                    <span v-else class="rounded p-1 text-white bg-black text-xs">Без статусу</span>
                </p>
                <p><strong>Статус Inpost: </strong>
                    <span v-if="selectedOrder.inpost_status" class="rounded p-1 text-white bg-black text-xs">
                        {{ selectedOrder.inpost_status }}
                    </span>
                    <span v-else class="rounded p-1 text-white bg-black text-xs">Без статусу</span>
                </p>
                <p><strong>Відповідальний:</strong> {{ selectedOrder.responsible_user?.name || '-' }}</p>
                <p><strong>Відправлено:</strong> {{ formatDateTime(selectedOrder.sent_at) }}</p>
                <p><strong>Дата отримання:</strong> {{ formatDateTime(selectedOrder.delivery_date) }}</p>
                <p><strong>Група:</strong> {{ selectedOrder.group?.name || '-' }}</p>
            </div>
            
        </div>

        <div class="flex gap-4">
          <div class="bg-yellow-100 rounded-lg p-4 border border-yellow-300 shadow-sm w-2/5">
              <h3 class="text-lg font-semibold mb-3">💳 Оплата</h3>
              <div class="grid grid-cols-2 gap-4">
                  <p><strong>Метод оплати:</strong> {{ selectedOrder.payment_method?.name || '-' }}</p>
                  <p><strong>Оплачено:</strong> {{ selectedOrder.is_paid ? '✅ Так' : '❌ Ні' }}</p>
                  <p><strong>Дата онлайн оплати:</strong> {{ formatDateTime(selectedOrder.payment_date) || '-' }}</p>
                  <p><strong>Сума оплати:</strong> {{ selectedOrder.paid_amount || '0' }}</p>
              </div>
          </div>

          <!-- 🔵 Доставка -->
          <div class="bg-blue-100 rounded-lg p-4 border border-blue-300 shadow-sm w-3/5">
              <h3 class="text-lg font-semibold mb-3">🚚 Доставка</h3>
              <div class="grid grid-cols-3 gap-4">
                  <p><strong>Ім'я:</strong> {{ selectedOrder.delivery_fullname }}</p>
                  <p><strong>Телефон:</strong> {{ selectedOrder.phone }}</p>
                  <p><strong>Email:</strong> {{ selectedOrder.email }}</p>
                  <p><strong>Місто:</strong> {{ selectedOrder.delivery_city }}</p>
                  <p><strong>ЗІП код:</strong> {{ selectedOrder.delivery_postcode }}</p>
                  <p><strong>Адреса:</strong> {{ selectedOrder.delivery_address }} {{ selectedOrder.delivery_address_number }}</p>
                  <p><strong>Доп. адреса:</strong> {{ selectedOrder.delivery_second_address || '-' }}</p>
                  <p><strong>Метод доставки:</strong> {{ selectedOrder.delivery_method?.name || '-' }}</p>
              </div>
              <p class="mt-4"><strong>Коментар:</strong> {{ selectedOrder.comment || '-' }}</p>
          </div>
        </div>

        <!-- 🛒 Товары -->
        <div class="bg-green-100 rounded-lg p-4 border border-green-300 shadow-sm">
            <h3 class="text-lg font-semibold mb-3">🛍️ Товари в замовленні</h3>
            <table class="table-auto w-full border-collapse border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">Назва</th>
                        <th class="border border-gray-300 p-2">Атрибути</th>
                        <th class="border border-gray-300 p-2">Кількість</th>
                        <th class="border border-gray-300 p-2">Ціна</th>
                        <th class="border border-gray-300 p-2">Сума</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in selectedOrder.items" :key="item.id" class="text-center">
                        <td class="border border-gray-300 p-2">
                            <span v-if="item.product_id">{{ item.product.name }}</span>
                            <span v-else-if="item.product_variation_id">{{ item.product_variation.product.name }}</span>
                            <span v-else>Товар не знайдено...</span>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <span v-if="item.product_variation_id">{{ formatVariationName(item.product_variation) }}</span>
                            <span v-else>-</span>
                        </td>
                        <td class="border border-gray-300 p-2">{{ item.quantity }}</td>
                        <td class="border border-gray-300 p-2">{{ item.price }}</td>
                        <td class="border border-gray-300 p-2">{{ formatCurrency(item.quantity * item.price) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-bold text-center bg-gray-100">
                        <td colspan="4" class="border border-gray-300 p-2 text-right">Загальна сума:</td>
                        <td class="border border-gray-300 p-2">{{ formatCurrency(totalAmount(selectedOrder.items)) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- 🔴 UTM-метки -->
        <div class="bg-red-100 rounded-lg p-4 border border-red-300 shadow-sm">
            <h3 class="text-lg font-semibold mb-3">📈 Маркетингові дані (UTM-метки)</h3>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <p><strong>UTM Source:</strong> {{ selectedOrder.utm_source || '-' }}</p>
                <p><strong>UTM Medium:</strong> {{ selectedOrder.utm_medium || '-' }}</p>
                <p><strong>UTM Campaign:</strong> {{ selectedOrder.utm_campaign || '-' }}</p>
                <p><strong>UTM Term:</strong> {{ selectedOrder.utm_term || '-' }}</p>
                <p><strong>UTM Content:</strong> {{ selectedOrder.utm_content || '-' }}</p>
                <p><strong>IP:</strong> {{ selectedOrder.ip }}</p>
                <p><strong>Website Referrer:</strong> {{ selectedOrder.website_referrer }}</p>
            </div>
        </div>

        <!-- ⚪ Дополнительные данные -->
        <div class="bg-gray-100 rounded-lg p-4 border border-gray-300 shadow-sm">
            <div class="grid grid-cols-2 gap-4 text-sm">
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

.locked-row {
  opacity: 0.5;
  /* Затемнение */
  pointer-events: none;
  /* Отключение кликов */
}

.custom-tooltip {
  max-width: 600px !important;
  /* Делаем тултип шире */
  white-space: nowrap !important;
  /* Запрещаем перенос строк */
  overflow: hidden !important;
  /* Скрываем лишний текст */
  text-overflow: ellipsis !important;
  /* Добавляем многоточие, если текст не влезает */
}

.p-datatable-tbody>tr.p-datatable-row-selected {
  background: #000 !important;
  color: #fff !important;
}
</style>