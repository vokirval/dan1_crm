<script setup>
import { ref, computed } from "vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";
import { Button, InputText, Textarea } from "primevue";
import { useToast } from "primevue/usetoast";
import { Trash, Check } from "lucide-vue-next";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const { props } = usePage();

console.log(props);
const order = ref(props.order);
const products = ref(props.products);
const selectedProduct = ref(null);
const selectedVariation = ref(null);

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
});

const updateOrder = () => {

  const dataToSubmit = {
    ...form.value,
    delivery_date: formatDateForApi(form.value.delivery_date),
    payment_date: formatDateForApi(form.value.payment_date),
  };


  router.put(`/orders/${order.value.id}`, dataToSubmit, {
    onSuccess: () => {
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
</script>

<template>

  <Head title="Просмотр заказа" />
  <Layout>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <h3 class="font-bold text-lg mb-3">Информация о заказе</h3>

        <div class="mb-4">
          <label for="fullname">Имя</label>
          <InputText id="fullname" v-model="form.delivery_fullname" class="w-full" />
        </div>
        <div class="mb-4">
          <label for="address">Адрес</label>
          <InputText id="address" v-model="form.delivery_address" class="w-full" />
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
          <label for="city">Город</label>
          <InputText id="city" v-model="form.delivery_city" class="w-full" />
        </div>
        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label for="phone">Телефон</label>
            <InputText id="phone" v-model="form.phone" class="w-full" />
          </div>
          <div>
            <label for="email">Email</label>
            <InputText id="email" v-model="form.email" class="w-full" />
          </div>
        </div>
        <div class="mb-4">
          <label for="comment">Комментарий</label>
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

        <h3 class="text-lg font-bold mb-2 mt-3">Добавить товар:</h3>
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
        <Button label="Добавить товар" class="mb-4" @click="addProductToOrder" />

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
        </table>
      </div>
    </div>
  </Layout>
</template>
