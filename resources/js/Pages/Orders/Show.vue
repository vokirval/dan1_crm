<script setup>
import { ref, computed, watch } from "vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";
import { Button, InputText, Textarea } from "primevue";
import { useToast } from "primevue/usetoast";
import { Trash, Check, Pencil, MailPlus, Send, MapPinned, RefreshCcw } from "lucide-vue-next";
import { useConfirm } from "primevue/useconfirm";
import { lockedOrders } from '../../ably'; // Импортируем список заблокированных заказов

const confirm = useConfirm();
const toast = useToast();
const { props } = usePage();


console.log(props);
const duplicateOrders = ref(props.duplicateOrders);
const order = ref(props.order);
const products = ref(props.products);
const selectedProduct = ref(null);
const customSendEmailTemplate = ref(false);
const selectedVariation = ref(null);
const dialogVisible = ref(false);
const emailTemplates = ref(props.emailTemplates || []); // Список шаблонов email
const selectedTemplateId = ref(null); // Выбранный шаблон
const customSubject = ref(""); // Пользовательская тема
const customBody = ref(""); // Пользовательское тело письма
const emailDialogVisible = ref(false); // Управление видимостью диалога
const isPaidAmountFocused = ref(false);
const previewHtml = ref(""); // HTML для предпросмотра
const previewDialogVisible = ref(false); // Видимость модального окна предпросмотра
const macros = ref([]);

const lockOrder = async (orderId) => {
    try {
        await axios.post(`/orders/${orderId}/lock`);
        window.currentLockedOrder = orderId; // Сохраняем ID заблокированного заказа
    } catch (error) {
        alert(error.response.data.error);
        router.get(`/orders/`);
    }
};

lockOrder(order.value.id);

watch(customSendEmailTemplate, (newValue) => {
  if (newValue) {
    fetchMacros();
    selectedTemplateId.value = null;
  }
});

const setTotalAmountToPaidInput = () => {
  form.value.paid_amount = totalAmount(order.value.items);
  isPaidAmountFocused.value = false; // Скрываем подсказку после клика
};

const fetchMacros = async () => {
  try {
    const response = await fetch('/email/macros', {
      headers: {
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
      },
    });
    const data = await response.json();
    macros.value = Object.entries(data).map(([key, description]) => ({
      key,
      description,
    }));
  } catch (error) {
    toast.add({
      severity: "error",
      summary: "Ошибка",
      detail: "Не удалось загрузить макросы.",
      life: 5000,
    });
  }
};
const customBodyTextarea = ref(null);
const insertMacro = (macro) => {
  if (!customBodyTextarea.value) return;

  const textarea = customBodyTextarea.value.$el || customBodyTextarea.value;
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;

  customBody.value =
    customBody.value.substring(0, start) +
    macro +
    customBody.value.substring(end);

  nextTick(() => {
    textarea.selectionStart = textarea.selectionEnd = start + macro.length;
    textarea.focus();
  });
};


const previewTemplate = async () => {
  if (!selectedTemplateId.value) {
    toast.add({
      severity: "warn",
      summary: "Ошибка",
      detail: "Выберите шаблон для предпросмотра.",
      life: 3000,
    });
    return;
  }

  try {
    const response = await fetch(`/orders/${order.value.id}/preview-template`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
      },
      body: JSON.stringify({
        template_id: selectedTemplateId.value,
      }),
    });

    const data = await response.json();

    if (data.success) {
      previewHtml.value = data.preview; // HTML для отображения предпросмотра
      previewDialogVisible.value = true; // Открываем модальное окно
    } else {
      throw new Error("Ошибка получения предпросмотра.");
    }
  } catch (error) {
    toast.add({
      severity: "error",
      summary: "Ошибка",
      detail: error.message,
      life: 5000,
    });
  }
};

const sendEmail = async () => {
  if (!selectedTemplateId.value && (!customSubject.value || !customBody.value)) {
    toast.add({
      severity: "warn",
      summary: "Ошибка",
      detail: "Оберіть шаблон або заповніть тему та лист для відправки.",
      life: 3000,
    });
    return;
  }

  try {
    const response = await fetch(`/orders/${order.value.id}/send-email`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
      },
      body: JSON.stringify({
        template_id: selectedTemplateId.value,
        custom_subject: customSubject.value,
        custom_body: customBody.value,
      }),
    });

    const data = await response.json();

    if (data.success) {
      toast.add({
        severity: "success",
        summary: "Успешно",
        detail: data.message,
        life: 3000,
      });
      emailDialogVisible.value = false; // Закрыть диалог после успешной отправки
    } else {
      throw new Error(data.message || "Не удалось отправить письмо");
    }
  } catch (error) {
    toast.add({
      severity: "error",
      summary: "Ошибка",
      detail: error.message,
      life: 5000,
    });
  }
};


const statuses = ref(props.statuses);
const payment_methods = ref(props.payment_methods);
const delivery_methods = ref(props.delivery_methods);
const groups = ref(props.groups);
const users = ref(props.users);

const page = usePage();


// Методы для обработки дат
const parseDateFromApi = (dateString) => {
  if (!dateString) return null;
  return new Date(dateString.replace(" ", "T")); // Преобразуем "Y-m-d H:i:s" в ISO-формат
};

const formatDateForApi = (date) => {
  if (!date) return null;

  // Если дата — это строка, преобразуем её в объект Date
  if (typeof date === "string") {
    date = new Date(date.replace(" ", "T")); // Преобразуем "Y-m-d H:i:s" в ISO-формат
  }

  // Проверяем, является ли дата объектом Date
  if (!(date instanceof Date) || isNaN(date.getTime())) {
    throw new TypeError("Invalid date provided");
  }

  const pad = (num) => String(num).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

const form = ref({
  delivery_fullname: order.value.delivery_fullname,
  delivery_address: order.value.delivery_address,
  delivery_second_address: order.value.delivery_second_address,
  delivery_postcode: order.value.delivery_postcode,
  delivery_city: order.value.delivery_city,
  phone: order.value.phone,
  email: order.value.email,
  comment: order.value.comment,
  order_status_id: order.value.order_status_id,
  payment_method_id: order.value.payment_method_id,
  delivery_method_id: order.value.delivery_method_id,
  group_id: order.value.group_id,
  responsible_user_id: order.value.responsible_user_id,
  delivery_date: order.value.delivery_date,
  payment_date: order.value.payment_date,
  tracking_number: order.value.tracking_number,
  is_paid: order.value.is_paid,
  paid_amount: order.value.paid_amount,
});

const updateOrder = () => {

  const dataToSubmit = {
    ...form.value,
    delivery_date: formatDateForApi(form.value.delivery_date),
    payment_date: formatDateForApi(form.value.payment_date),
  };


  router.put(`/orders/${order.value.id}`, dataToSubmit, {
    onSuccess: () => {
      discrepanciesList.value = [];
      toast.add({
        severity: "success",
        summary: "Успішно!",
        detail: page.props.flash.success,
        life: 3000,
      });
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

// Вариации товара
const productVariations = computed(() => {
  if (!selectedProduct.value || !selectedProduct.value.variations) {
    return [];
  }
  return selectedProduct.value.variations.map((variation) => ({
    label: variation.attributes
      .map((attr) => `${attr.attribute_name}: ${attr.attribute_value}`)
      .join(", "),
    value: variation.id,
  }));
});

// Добавление товара в заказ
const addProductToOrder = async () => {
  if (!selectedProduct.value) {
    toast.add({
      severity: "warn",
      summary: "Ошибка",
      detail: "Выберите товар перед добавлением.",
      life: 3000,
    });
    return;
  }

  if (
    selectedProduct.value.variations?.length > 0 &&
    !selectedVariation.value
  ) {
    toast.add({
      severity: "warn",
      summary: "Ошибка",
      detail: "Для данного товара необходимо выбрать вариацию.",
      life: 3000,
    });
    return;
  }

  const itemPrice =
    selectedProduct.value.variations?.length && selectedVariation.value
      ? selectedProduct.value.variations.find(
        (v) => v.id === selectedVariation.value
      ).price
      : selectedProduct.value.price;

  const itemToAdd = {
    product_id: selectedProduct.value.id,
    product_variation_id: selectedVariation.value || null,
    name: selectedProduct.value.name,
    variation_name: selectedVariation.value
      ? productVariations.value.find(
        (v) => v.value === selectedVariation.value
      ).label
      : null,
    quantity: 1,
    price: itemPrice,
    subtotal: itemPrice,
  };

  try {
    const response = await fetch(`/orders/${order.value.id}/items`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        'X-Requested-With': 'XMLHttpRequest', // Указывает, что это AJAX-запрос
        "X-CSRF-TOKEN": document
          .querySelector('meta[name="csrf-token"]')
          .getAttribute("content"),
      },
      body: JSON.stringify(itemToAdd),
    });

    if (!response.ok) {
      throw new Error("Ошибка сети");
    }

    const data = await response.json();

    // Обновляем данные заказа
    order.value = data.order;

    toast.add({
      severity: "success",
      summary: "Успешно",
      detail: data.flash.success,
      life: 3000,
    });

    selectedProduct.value = null;
    selectedVariation.value = null;
  } catch (error) {
    toast.add({
      severity: "error",
      summary: "Ошибка",
      detail: error.message,
      life: 5000,
    });
  }
};

const removeOrderItem = (event, orderId, itemId) => {
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
      router.delete(`/orders/${orderId}/items/${itemId}`, {
        onSuccess: () => {
          toast.add({
            severity: "success",
            summary: "Успішно!",
            detail: page.props.flash.success,
            life: 3000,
          });

          order.value.items = order.value.items.filter(
            (item) => item.id !== itemId
          );
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

const updateOrderItem = async (itemId, field, value) => {
  try {
    const response = await fetch(
      `/orders/${order.value.id}/items/${itemId}`,
      {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          'X-Requested-With': 'XMLHttpRequest', // Указывает, что это AJAX-запрос
          "X-CSRF-TOKEN": document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute("content"),
        },
        body: JSON.stringify({ [field]: value }),
      }
    );

    if (!response.ok) {
      throw new Error("Ошибка обновления");
    }

    const data = await response.json();

    // Обновляем данные заказа
    order.value = data.order;

    toast.add({
      severity: "success",
      summary: "Успешно",
      detail: "Товар успешно обновлен.",
      life: 3000,
    });
  } catch (error) {
    toast.add({
      severity: "error",
      summary: "Ошибка",
      detail: error.message,
      life: 5000,
    });
  }
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
};

const totalAmount = (selectedOrder) => {
  return selectedOrder.reduce((total, item) => {
    return total + item.quantity * item.price;
  }, 0);
};

const selectedOrder = ref(null);
const visible = ref(false);
 // Открытие диалога
 const openOrderDialog = (event) => {
  console.log(event);
  selectedOrder.value = event; // Передаем модель заказа
  visible.value = true;
};

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

const changeEmail = () => {
  form.value.email = form.value.phone+'_client@daggi.shop';
}

const discrepanciesList = ref([]); // Хранит список несоответствий


const checkAddress = async () => {

  if (
    !form.value.delivery_address ||
    !form.value.delivery_postcode ||
    !form.value.delivery_city
  ) {
    toast.add({
      severity: "warn",
      summary: "Помилка",
      detail: "Будь ласка, заповніть всі обов'язкові поля: адреса, поштовий індекс, місто.",
      life: 9000,
    });
    return;
  }


  
  // Проверяем, есть ли дробь в адресе и убираем её, если она есть
  const cleanedAddress = form.value.delivery_address.includes("/")
    ? form.value.delivery_address.split("/")[0].trim()
    : form.value.delivery_address;

  const url = `https://api.geoapify.com/v1/geocode/search?street=${encodeURIComponent(cleanedAddress)}&postcode=${encodeURIComponent(form.value.delivery_postcode)}&city=${encodeURIComponent(form.value.delivery_city)}&apiKey=cfb84a334cbb4ddabf3f0dce863d7e2c`;

  try {
    const response = await fetch(url);
    const result = await response.json();

    if (result.features.length === 0) {
      toast.add({
        severity: "error",
        summary: "Помилка перевірки адреси",
        detail: "Адресу не знайдено, перевірте правильність введених даних.",
        life: 9000,
      });
      return;
    }

    let bestMatch = null;
    let highestConfidence = 0;

    // Поиск лучшего совпадения
    result.features.forEach((feature) => {
      const confidence = feature.properties.rank.confidence;
      if (confidence > highestConfidence) {
        highestConfidence = confidence;
        bestMatch = feature;
      }
    });

    if (!bestMatch) {
      toast.add({
        severity: "warn",
        summary: "Адресу не підтверджено",
        detail: "Не вдалося знайти відповідний запис у базі.",
        life: 9000,
      });
      return;
    }

    // Данные от сервиса
    const apiAddress = (bestMatch.properties.street || "") + " " + (bestMatch.properties.housenumber || "");
    const apiPostcode = bestMatch.properties.postcode || "";
    const apiCity = bestMatch.properties.city || "";

    // Данные, которые ввел пользователь
    const userAddress = cleanedAddress;
    const userPostcode = form.value.delivery_postcode.trim();
    const userCity = form.value.delivery_city.trim();

    // Список расхождений
    if (apiAddress && userAddress.toLowerCase() !== apiAddress.toLowerCase()) {
      discrepanciesList.value.push({
        label: "Адреса",
        userValue: userAddress,
        apiValue: apiAddress,
      });
    }

    if (apiPostcode && userPostcode !== apiPostcode) {
      discrepanciesList.value.push({
        label: "ЗІП код",
        userValue: userPostcode,
        apiValue: apiPostcode,
      });
    }

    if (apiCity && userCity.toLowerCase() !== apiCity.toLowerCase()) {
      discrepanciesList.value.push({
        label: "Місто",
        userValue: userCity,
        apiValue: apiCity,
      });
    }

    // Если расхождения есть - выводим их в отдельном блоке
    if (discrepanciesList.value.length > 0) {
      toast.add({
        severity: "warn",
        summary: "Є розбіжності в адресі",
        detail: "Перевірте виправлення у формі нижче.",
        life: 9000,
      });
    } else {
      toast.add({
        severity: "success",
        summary: "Адресу підтверджено",
        detail: `Знайдено точну відповідність: ${bestMatch.properties.formatted}`,
        life: 9000,
      });
    }
  } catch (error) {
    toast.add({
      severity: "error",
      summary: "Помилка",
      detail: "Сталася помилка під час перевірки адреси.",
      life: 9000,
    });
  }
};


</script>

<template>

  <Head title="Просмотр заказа" />
  <Layout>
    <div class="bg-[#0f172a] mb-3" v-if="duplicateOrders[0]">
        <div class="bg-surface-900 text-gray-100 py-4 flex justify-center items-center flex-wrap">
            <div class="font-bold inline-flex gap-1 items-center">🔥 Увага! Є дублікати! 🔥 <Button label="Показати" severity="secondary" @click="dialogVisible = true" /></div>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <h3 class="font-bold text-lg mb-3">Замовлення #{{ order.id }}</h3>

        <div v-if="discrepanciesList.length" class="p-3 bg-yellow-100 border border-yellow-400 rounded mt-3">
          <h4 class="font-bold text-yellow-900">Виявлено розбіжності:</h4>
          <ul class="mt-2 text-yellow-900">
            <li v-for="item in discrepanciesList" :key="item.label">
              <strong>{{ item.label }}:</strong>
              <span class="text-red-600"> ❌ {{ item.userValue }} </span>
              <span class="text-green-600"> → ✅ {{ item.apiValue }}</span>
            </li>
          </ul>
        </div>

        <div class="mb-4">
          <label for="fullname">Им`я</label>
          <InputText id="fullname" v-model="form.delivery_fullname" class="w-full" />
        </div>
        <div class="mb-4 flex">
          <div class="w-full">
            <label for="address">Адреса</label>
            <InputText id="address" v-model="form.delivery_address" class="w-full" />
          </div>
          <Button size="small" @click="checkAddress" class="mt-6 ml-2"><MapPinned class="w-6 h-6"/></Button>
         
        </div>
        <div class="mb-4">
          <label for="address2">Додаткова адреса</label>
          <InputText id="address2" v-model="form.delivery_second_address" class="w-full" />
        </div>
        <div class="mb-4">
          <label for="zipcode">Зіп код</label>
          <InputText id="zipcode" v-model="form.delivery_postcode" class="w-full" />
        </div>
        <div class="mb-4">
          <label for="city">Місто</label>
          <InputText id="city" v-model="form.delivery_city" class="w-full" />
        </div>
        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label for="phone">Телефон</label>
            <InputText id="phone" v-model="form.phone" class="w-full" />
          </div>
          <div class="flex">
            <div class="w-full">
              <label for="email">Email</label>
              <InputText id="email" v-model="form.email" class="w-full" />
            </div>
            <Button size="small" @click="changeEmail" v-if="!form.email" class="mt-6 ml-2"><RefreshCcw class="w-6 h-6"/></Button>
            <Button size="small" @click="emailDialogVisible = true" class="mt-6 ml-2"><MailPlus class="w-6 h-6"/></Button>
          </div>
          <p>IP Юзера: {{ order.ip }}</p>
        </div>
        <div class="mb-4">
          <label for="comment">Коментар</label>
          <Textarea id="comment" v-model="form.comment" class="w-full" />
        </div>
        <IftaLabel class="mt-5">
          <Select v-model="form.payment_method_id" optionValue="id" :options="payment_methods" optionLabel="name"
            placeholder="Метод оплати" class="w-full" />
          <label for="product_quantity">Метод оплати</label>
        </IftaLabel>

        <IftaLabel class="mt-5">
          <Select v-model="form.delivery_method_id" optionValue="id" :options="delivery_methods" optionLabel="name"
            placeholder="Метод доставки" class="w-full" />
          <label for="product_quantity">Метод доставки</label>
        </IftaLabel>
        <Button label="Оновити" @click="updateOrder" class="mt-4" />


      </div>

      <div>
        <h3 class="font-bold text-lg mb-3">Доп. налаштування</h3>
        <IftaLabel class="mt-5">
          <Select v-model="form.order_status_id" optionValue="id" :options="statuses" optionLabel="name"
            placeholder="Статус Замовлення" class="w-full" />
          <label for="product_quantity">Статус Замовлення</label>
        </IftaLabel>

        <IftaLabel class="mt-5">
          <Select v-model="form.group_id" optionValue="id" :options="groups" optionLabel="name" placeholder="Група"
            class="w-full" />
          <label for="product_quantity">Група</label>
        </IftaLabel>

        <IftaLabel class="mt-5">
          <Select v-model="form.responsible_user_id" optionValue="id" :options="users" optionLabel="name"
            placeholder="Відповідальний" class="w-full" />
          <label for="product_quantity">Відповідальний</label>
        </IftaLabel>

        <IftaLabel class="mt-5">
          <DatePicker
            id="delivery_date"
            dateFormat="yy-mm-dd"
            v-model="form.delivery_date"
            showTime
            hourFormat="24"
            fluid
          />
          <label for="delivery_date">Дата отримання посилки клієнтом</label>
        </IftaLabel>

        <IftaLabel class="mt-5">
          <Select  placeholder="Група"  optionLabel="label" optionValue="value" class="w-full" v-model="form.is_paid" 
          :options="[
            { label: 'Ні', value: 0 },
            { label: 'Так', value: 1 }
          ]"  />
          

      
          <label for="is_paid">Оплачено</label>
        </IftaLabel>

        <IftaLabel class="mt-5">
          <DatePicker
            id="payment_date"
            dateFormat="yy-mm-dd"
            v-model="form.payment_date"
            showTime
            hourFormat="24"
            fluid
          />
          <label for="payment_date">Дата онлайн оплати</label>
        </IftaLabel>

        <div class="mb-4 mt-5 relative">
          <label for="paid_amount">Сума оплати</label>
          <InputText
            id="paid_amount"
            v-model="form.paid_amount"
            class="w-full"
            @focus="isPaidAmountFocused = true"
            @blur="isPaidAmountFocused = false"
          />
          <!-- Подсказка с суммой заказа -->
           <div class="mt-3" v-if="isPaidAmountFocused">
            <span
              
              class="bg-green-500 text-white p-2 rounded cursor-pointer shadow"
              @mousedown.stop.prevent="setTotalAmountToPaidInput" 
            >
              {{ formatCurrency(totalAmount(order.items)) }}
            </span>
          </div>
        </div>

        <div class="mb-4">
          <label for="tracking_number">Трекинг номер</label>
          <InputText id="tracking_number" v-model="form.tracking_number" class="w-full" />
        </div>

        <h3 class="text-lg font-bold mb-2 mt-3">Додати товар:</h3>
        <div class="grid grid-cols-2 gap-4 mb-6">
          <IftaLabel>
            <Select v-model="selectedProduct" :options="products" optionLabel="name" placeholder="Выберите товар"
              class="w-full" />
            <label>Товар</label>
          </IftaLabel>

          <IftaLabel v-if="productVariations.length">
            <Select v-model="selectedVariation" :options="productVariations" optionLabel="label" optionValue="value"
              placeholder="Выберите вариацию" class="w-full" />
            <label>Вариация</label>
          </IftaLabel>
        </div>
        <Button label="Додати товар" class="mb-4" @click="addProductToOrder" />

        <h3 class="font-bold text-lg mb-3 mt-5">Товари в замовленні</h3>
        <table class="table-auto w-full border-collapse border border-gray-300">
          <thead>
            <tr>
              <th class="border border-gray-300 p-2">Назва</th>
              <th class="border border-gray-300 p-2">Атрибути</th>
              <th class="border border-gray-300 p-2">
                Кількість
              </th>
              <th class="border border-gray-300 p-2">Ціна</th>
              <th class="border border-gray-300 p-2">Сума</th>
              <th class="border border-gray-300 p-2">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id">
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
                <Inplace>
                  <template #display>
                    {{ item.quantity }}
                  </template>
                  <template #content="{ closeCallback }">
                    <span class="inline-flex items-center gap-2">
                      <InputText v-model.number="item.quantity" class="w-full" />
                      <Button icon="pi pi-times" text severity="danger" @click="updateOrderItem(
                        item.id,
                        'quantity',
                        item.quantity
                      ); closeCallback();"><Check /></Button>
                    </span>
                  </template>
                </Inplace>

              </td>
              <td class="border border-gray-300 p-2">
                <Inplace>
                  <template #display>
                    {{ item.price }}
                  </template>
                  <template #content="{ closeCallback }">
                    <span class="inline-flex items-center gap-2">
                      <InputText v-model.number="item.price" class="w-full" />
                      <Button text severity="danger" @click="updateOrderItem(
                        item.id,
                        'price',
                        item.price
                      ); closeCallback();" ><Check /></Button>
                    </span>
                  </template>
                </Inplace>

              </td>
              <td class="border border-gray-300 p-2">
                {{ formatCurrency(item.quantity * item.price) }}
              </td>
              <td class="border border-gray-300 p-2 text-center">
                <Button severity="secondary" @click="
                  removeOrderItem(
                    $event,
                    order.id,
                    item.id
                  )
                  ">
                  <Trash class="h-4 w-4" />
                </Button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="border border-gray-300 p-2 font-bold text-right">
                Загальна сума:
              </td>
              <td class="border border-gray-300 p-2 font-bold">
                {{ formatCurrency(totalAmount(order.items)) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>


    <h3 class="text-lg font-bold mb-3">Історія відправки листів</h3>
      <table class="table-auto w-full border-collapse border border-gray-300">
        <thead>
          <tr>
            <th class="border border-gray-300 p-2">Дата відправки</th>
            <th class="border border-gray-300 p-2">Статус</th>
            <th class="border border-gray-300 p-2">Email</th>
            <th class="border border-gray-300 p-2">Тема</th>
            <th class="border border-gray-300 p-2">Помилка</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="email in order.email_history" :key="email.id">
            <td class="border border-gray-300 p-2">{{ email.sent_at || 'Не відправлено' }}</td>
            <td class="border border-gray-300 p-2">
              <span v-if="email.status === 'success'" class="text-green-600">Успішно</span>
              <span v-else class="text-red-600">Помилка</span>
            </td>
            <td class="border border-gray-300 p-2">{{ email.to_email }}</td>
            <td class="border border-gray-300 p-2">{{ email.subject }}</td>
            <td class="border border-gray-300 p-2">{{ email.error_message || '-' }}</td>
          </tr>
        </tbody>
      </table>

    

      <Dialog 
    v-model:visible="dialogVisible" 
    header="Дублікати замовлення" 
    :style="{ width: '75vw' }" 
    maximizable 
    modal 
    :contentStyle="{ height: '300px' }"
>
    <div class="overflow-auto">
        <table class="min-w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 p-2">Статус</th>
                    <th class="border border-gray-300 p-2">ID замовлення</th>
                    <th class="border border-gray-300 p-2">Клієнт</th>
                    <th class="border border-gray-300 p-2">Телефон</th>
                    <th class="border border-gray-300 p-2">Email</th>
                    <th class="border border-gray-300 p-2">IP</th>
                    <th class="border border-gray-300 p-2">Товари</th>
                    <th class="border border-gray-300 p-2">Коментар</th>
                    <th class="border border-gray-300 p-2">ЗІП-код</th>
                    <th class="border border-gray-300 p-2">Метод оплати</th>
                    <th class="border border-gray-300 p-2">Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="duplicate in duplicateOrders" :key="duplicate.id" class="even:bg-gray-50">
                    <td class="border border-gray-300 p-2">
                        <span v-if="duplicate.status" 
                              class="rounded p-1 text-white text-xs"
                              :style="{ backgroundColor: `#${duplicate.status.color}` }">
                            {{ duplicate.status?.name }}
                        </span>
                        <span v-else class="rounded p-1 text-white bg-black text-xs">
                            Без статусу
                        </span>
                    </td>
                    <td class="border border-gray-300 p-2">
                        #{{ duplicate.id }}
                    </td>
                    <td class="border border-gray-300 p-2">
                        {{ duplicate.delivery_fullname }}
                    </td>
                    <td class="border border-gray-300 p-2" 
                        :class="{'text-red-700 font-bold': duplicate.phone === order.phone}">
                        {{ duplicate.phone }}
                    </td>
                    <td class="border border-gray-300 p-2" 
                        :class="{'text-red-700 font-bold': duplicate.email === order.email}">
                        {{ duplicate.email }}
                    </td>
                    <td class="border border-gray-300 p-2" 
                        :class="{'text-red-700 font-bold': duplicate.ip === order.ip}">
                        {{ duplicate.ip }}
                    </td>
                    <td class="border border-gray-300 p-2" >
                      <div v-for="item in duplicate.items" :key="item.id">
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
                    </td>
                    <td class="border border-gray-300 p-2" >
                        {{ duplicate.comment || '-' }}
                    </td>
                    <td class="border border-gray-300 p-2" >
                        {{ duplicate.delivery_postcode || '-' }}
                    </td>
                    <td class="border border-gray-300 p-2" >
                        {{ duplicate.payment_method?.name }}
                    </td>
                    <td class="border border-gray-300 p-2 text-center">
                        <Button size="small" @click="openOrderDialog(duplicate)">
                            <Pencil class="w-5 h-5" /> Детально
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</Dialog>




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
            <p><strong>Відповідальний:</strong> {{ selectedOrder.responsible_user?.name }}</p>
            <Button size="small" @click="viewOrder(selectedOrder.id)"><Pencil class="w-5 h-5"/> Редагувати замовлення</Button>
        </div>
      </div>

      <!-- Доставка -->
      <div class="text-base p-5 bg-[#f1f5f9]">
        <div class="grid grid-cols-6 gap-4 ">
          
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
          <p><strong>Трекінг Номер:</strong> {{ selectedOrder.tracking_number || 'N/A' }}</p> 
          
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

  <Dialog
  v-model:visible="emailDialogVisible"
  header="Відправка Email"
  :style="{ width: '50vw' }"
  :breakpoints="{ '960px': '75vw', '640px': '90vw' }"
>
  <div class="grid grid-cols-1 gap-4">
  

    <ToggleButton v-model="customSendEmailTemplate" onLabel="Вибрати зі списку шаблонів" offLabel="Створити лист самостійно" />

      
    <div v-if="customSendEmailTemplate == false">
      <label for="template">Шаблон листа</label>
      <Select
        id="template"
        v-model="selectedTemplateId"
        @change="previewTemplate"
        :options="emailTemplates.map(template => ({ label: template.name, value: template.id }))"
        optionValue="value" optionLabel="label"
        placeholder="Оберіть шаблон"
        class="w-full"
      />
      
      <div v-if="selectedTemplateId">
        <h3 class="mt-5">Превью шаблона:</h3>
       <div class="p-3 border border-[#000]" v-html="previewHtml"></div>
      </div>

    </div>
    <div v-else>
      <div class="mb-6">
        <h3 class="text-lg font-bold mb-3">Доступні макроси</h3>
        <ul class="space-y-1 flex gap-3 w-full flex-wrap">
          <li
            v-for="macro in macros"
            :key="macro.key"
            @click="insertMacro(macro.key)"
            class="bg-gray-100 p-1 rounded shadow cursor-pointer hover:bg-gray-200 "
          >
            <span class="text-xs text-gray-500" v-tooltip.top="macro.description">{{ macro.key }}</span>
          </li>
        </ul>
      </div>
      <div>
        <label for="custom-subject">Тема</label>
        <InputText id="custom-subject" v-model="customSubject" class="w-full" />
      </div>
      <div class="mt-3">
        <label for="custom-body">Лист</label>
        <Textarea
          id="custom-body"
          ref="customBodyTextarea"
          v-model="customBody"
          rows="5"
          class="w-full"
        />
      </div>
    </div>


    
  </div>
  <template #footer>
    <Button class="p-button-success" @click="sendEmail"><Send /> Відправити</Button>
  </template>
</Dialog>



  </Layout>
</template>
