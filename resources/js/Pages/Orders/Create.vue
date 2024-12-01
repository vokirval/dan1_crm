<script setup>
import { ref, computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";
import { Button, InputText, Dropdown } from "primevue";
import { useToast } from "primevue/usetoast";

const toast = useToast();
const { props } = usePage();

const statuses = ref(props.statuses);
const paymentMethods = ref(props.paymentMethods);
const deliveryMethods = ref(props.deliveryMethods);
const groups = ref(props.groups);
const users = ref(props.users);
const products = ref(props.products); // Все продукты

const form = ref({
  delivery_fullname: "",
  delivery_address: "",
  delivery_second_address: "",
  delivery_postcode: "",
  delivery_city: "",
  phone: "",
  email: "",
  comment: "",
  order_status_id: null,
  payment_method_id: null,
  delivery_method_id: null,
  group_id: null,
  responsible_user_id: null,
  items: [],
});

const selectedProduct = ref(null);
const selectedVariation = ref(null);

// Получение списка вариаций для выбранного продукта
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

// Метод для добавления товара или вариации в заказ
const addProductToOrder = () => {
  if (!selectedProduct.value) {
    toast.add({
      severity: "warn",
      summary: "Ошибка",
      detail: "Выберите товар перед добавлением.",
      life: 3000,
    });
    return;
  }

  // Проверка на наличие вариаций
  if (selectedProduct.value.variations?.length > 0 && !selectedVariation.value) {
    toast.add({
      severity: "warn",
      summary: "Ошибка",
      detail: "Для данного товара необходимо выбрать вариацию.",
      life: 3000,
    });
    return;
  }

  const itemPrice = selectedProduct.value.variations?.length && selectedVariation.value
    ? selectedProduct.value.variations.find(v => v.id === selectedVariation.value).price
    : selectedProduct.value.price;

  const itemToAdd = {
    product_id: selectedProduct.value.id,
    product_variation_id: selectedVariation.value || null,
    name: selectedProduct.value.name,
    variation_name: selectedVariation.value
      ? productVariations.value.find((v) => v.value === selectedVariation.value).label
      : null,
    quantity: 1,
    price: itemPrice,
    subtotal: itemPrice,
  };

  form.value.items.push(itemToAdd);

  selectedProduct.value = null;
  selectedVariation.value = null;

  toast.add({
    severity: "success",
    summary: "Успешно",
    detail: "Товар добавлен в заказ.",
    life: 3000,
  });
};

// Удаление товара из заказа
const removeOrderItem = (index) => {
  form.value.items.splice(index, 1);
};

// Сохранение заказа
const saveOrder = () => {

  

  router.post("/orders", form.value, {
    onSuccess: () => {
      toast.add({
        severity: "success",
        summary: "Успешно",
        detail: "Заказ успешно создан.",
        life: 3000,
      });
      form.value = {
        delivery_fullname: "",
        delivery_address: "",
        delivery_second_address: "",
        delivery_postcode: "",
        delivery_city: "",
        phone: "",
        email: "",
        comment: "",
        order_status_id: null,
        payment_method_id: null,
        delivery_method_id: null,
        group_id: null,
        responsible_user_id: null,
        items: [],
      };
    },
    onError: (error) => {
      const errorMessages = Object.values(error).flat().join("\n");
      toast.add({
        severity: "error",
        summary: "Ошибка",
        detail: errorMessages,
        life: 5000,
      });
    },
  });
};
</script>

<template>
    <Layout>
      <h2 class="text-xl font-bold mb-4">Создание заказа</h2>
  
      <div class="grid grid-cols-2 gap-4 mb-6">
        <div>
          <label for="fullname" class="block">Имя:</label>
          <InputText v-model="form.delivery_fullname" class="w-full mb-4" placeholder="Имя" />
  
          <label for="address1" class="block">Адрес:</label>
          <InputText v-model="form.delivery_address" class="w-full mb-4" placeholder="Адрес" />

          <label for="address2" class="block">Додаткова адреса:</label>
          <InputText v-model="form.delivery_second_address" class="w-full mb-4" placeholder="Додаткова адреса" />

          <label for="zipcode" class="block">Зіп код:</label>
          <InputText v-model="form.delivery_postcode" class="w-full mb-4" placeholder="Зіп код" />
  
          <label for="city" class="block">Город:</label>
          <InputText v-model="form.delivery_city" class="w-full mb-4" placeholder="Город" />
  
          <label for="phone" class="block">Телефон:</label>
          <InputText v-model="form.phone" class="w-full mb-4" placeholder="Телефон" />
  
          <label for="email" class="block">Email:</label>
          <InputText v-model="form.email" class="w-full mb-4" placeholder="Email" />
  
          <label for="comment" class="block">Комментарий:</label>
          <Textarea v-model="form.comment" class="w-full mb-4" placeholder="Комментарий" />
        </div>
  
        <div>
          <IftaLabel class="mt-5">
            <Select
              v-model="form.order_status_id"
              optionValue="id"
              :options="statuses"
              optionLabel="name"
              placeholder="Статус замовлення"
              class="w-full"
            />
            <label>Статус замовлення</label>
          </IftaLabel>
  
          <IftaLabel class="mt-5">
            <Select
              v-model="form.payment_method_id"
              optionValue="id"
              :options="paymentMethods"
              optionLabel="name"
              placeholder="Метод оплати"
              class="w-full"
            />
            <label>Метод оплати</label>
          </IftaLabel>
  
          <IftaLabel class="mt-5">
            <Select
              v-model="form.delivery_method_id"
              optionValue="id"
              :options="deliveryMethods"
              optionLabel="name"
              placeholder="Метод доставки"
              class="w-full"
            />
            <label>Метод доставки</label>
          </IftaLabel>
  
          <IftaLabel class="mt-5">
            <Select
              v-model="form.group_id"
              optionValue="id"
              :options="groups"
              optionLabel="name"
              placeholder="Група"
              class="w-full"
            />
            <label>Група</label>
          </IftaLabel>
  
          <IftaLabel class="mt-5">
            <Select
              v-model="form.responsible_user_id"
              optionValue="id"
              :options="users"
              optionLabel="name"
              placeholder="Відповідальний"
              class="w-full"
            />
            <label>Відповідальний</label>
          </IftaLabel>
        </div>
      </div>
  
      <h3 class="text-lg font-bold mb-2">Додавання товару:</h3>
      <div class="grid grid-cols-2 gap-4 mb-6">
        <IftaLabel>
          <Select
            v-model="selectedProduct"
            :options="products"
            optionLabel="name"
            placeholder="Оберіть товар"
            class="w-full"
          />
          <label>Товар</label>
        </IftaLabel>
  
        <IftaLabel v-if="productVariations.length">
          <Select
            v-model="selectedVariation"
            :options="productVariations"
            optionLabel="label"
             optionValue="value"
            placeholder="Оберіть варіацію"
            class="w-full"
          />
          <label>Варіація</label>
        </IftaLabel>
      </div>
  
      <Button label="Додати товар" class="mb-4" @click="addProductToOrder" />
  
      <h3 class="text-lg font-bold mb-2">Товари в замовленні:</h3>
      <table class="table-auto w-full border-collapse border border-gray-300">
        <thead>
          <tr>
            <th class="border p-2">Назва</th>
            <th class="border p-2">Атрибути</th>
            <th class="border p-2">Кількість</th>
            <th class="border p-2">Ціна</th>
            <th class="border p-2">Сума</th>
            <th class="border p-2">Дії</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in form.items" :key="index">
            <td class="border p-2">{{ item.name }}</td>
            <td class="border p-2">{{ item.variation_name || '-' }}</td>
            <td class="border p-2">
              <InputText v-model.number="item.quantity" class="w-full" />
            </td>
            <td class="border p-2">
              <InputText v-model.number="item.price" class="w-full" />
            </td>
            <td class="border p-2">{{ item.quantity * item.price }}</td>
            <td class="border p-2 text-center">
              <Button icon="pi pi-trash" class="p-button-danger" @click="removeOrderItem(index)" />
            </td>
          </tr>
        </tbody>
      </table>
    <Button label="Сохранить заказ" class="mt-6" @click="saveOrder" />
  </Layout>
</template>
