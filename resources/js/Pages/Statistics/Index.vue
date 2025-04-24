<script setup>
import Layout from "../../Layout/App.vue";
import { ref, reactive, onMounted, watch, computed } from "vue";
import { router, usePage, Head } from "@inertiajs/vue3";
import { Trash } from "lucide-vue-next";

const page = usePage();
const statuses = page.props.statuses || [];
const categories = page.props.categories || [];
const stats = ref(page.props.stats || []);
const productsStats = ref(page.props.products_stats || []);
const users = ref([]);
const orders = ref(page.props.orders || []);
const deliveryMethods = ref([]);
const paymentMethods = ref([]);
const groups = ref([]);

const products = ref([]);
const variationsMap = ref({});
const savedFilters = ref([]);
const newFilterName = ref("");
const isLoading = ref(false);
// Исходные значения для сброса
const DEFAULT_DATE_FILTER = {
  field: 'created_at',
  range: [null, null]
};

const DEFAULT_FILTER = {
  condition: "AND",
  rules: []
};


// Обязательный фильтр по дате
const mandatoryDateFilter = reactive({ ...DEFAULT_DATE_FILTER });

const filter = reactive({ ...DEFAULT_FILTER });

// Полный сброс всех фильтров
const resetAllFilters = () => {
  // 1. Сбрасываем даты
  mandatoryDateFilter.field = 'created_at'
  mandatoryDateFilter.range = [null, null]

  // 2. Полностью пересоздаем объект фильтра
  const newFilter = {
    condition: "AND",
    rules: []
  }

  // 3. Удаляем все существующие свойства
  for (const key in filter) {
    delete filter[key]
  }

  // 4. Добавляем новые свойства
  Object.assign(filter, newFilter)

  // 5. Принудительный триггер обновления
  if (filter.rules) {
    filter.rules = [...filter.rules]
  }
}

// Загрузка данных при отправке формы
const loadStatistics = async () => {
  if (!mandatoryDateFilter.range[0] || !mandatoryDateFilter.range[1]) {
    alert('Будь ласка, оберіть дати');
    return;
  }

  isLoading.value = true;

  try {
    const response = await axios.post('/statistics/filter', {
      mandatory_date: {
        field: mandatoryDateFilter.field,
        range: [
          formatLocalDate(mandatoryDateFilter.range[0]),
          formatLocalDate(mandatoryDateFilter.range[1])
        ]
      },
      filters: filter.rules.length ? filter : null
    });

    orders.value = response.data.orders;
    stats.value = response.data.stats;
    productsStats.value = response.data.products_stats;
  } catch (error) {
    console.error('Ошибка загрузки:', error);
    alert(error.response?.data?.message || 'Ошибка при загрузке статистики');
  } finally {
    isLoading.value = false;
  }
};


// Новый режим агрегации
const aggregationMode = ref('by_product'); // По умолчанию: по товару с атрибутами
const aggregationModes = [
  { label: 'По товару з атрибутами', value: 'by_product_with_attributes' },
  { label: 'По товару без атрибутів', value: 'by_product' },
  { label: 'По категорії', value: 'by_category' },
  { label: 'По атрибутах', value: 'by_attributes' },
];

// Вычисляем агрегированные данные в зависимости от режима
const aggregatedProducts = computed(() => {
  const rawStats = productsStats.value;

  // Вычисляем общее количество и сумму для текущего набора данных
  const totalQuantity = stats.value.order_count || 0;
  const totalSum = (stats.value.total_sum_non_services + stats.value.total_sum_services) || 0;

  let aggregatedData = [];

  if (aggregationMode.value === 'by_product_with_attributes') {
    aggregatedData = rawStats;
  } else if (aggregationMode.value === 'by_product') {
    const aggregated = {};
    rawStats.forEach(item => {
      const key = item.product_name;
      if (!aggregated[key]) {
        aggregated[key] = {
          product_name: item.product_name,
          attributes: null,
          category_name: item.category_name,
          quantity: 0,
          total_sum: 0,
        };
      }
      aggregated[key].quantity += item.quantity;
      aggregated[key].total_sum += item.total_sum;
    });
    aggregatedData = Object.values(aggregated);
  } else if (aggregationMode.value === 'by_category') {
    const aggregated = {};
    rawStats.forEach(item => {
      const key = item.category_name;
      if (!aggregated[key]) {
        aggregated[key] = {
          product_name: null,
          attributes: null,
          category_name: item.category_name,
          quantity: 0,
          total_sum: 0,
        };
      }
      aggregated[key].quantity += item.quantity;
      aggregated[key].total_sum += item.total_sum;
    });
    aggregatedData = Object.values(aggregated);
  } else if (aggregationMode.value === 'by_attributes') {
    const aggregated = {};
    rawStats.forEach(item => {
      if (!item.attributes) return;
      const key = item.attributes;
      if (!aggregated[key]) {
        aggregated[key] = {
          product_name: null,
          attributes: item.attributes,
          category_name: null,
          quantity: 0,
          total_sum: 0,
        };
      }
      aggregated[key].quantity += item.quantity;
      aggregated[key].total_sum += item.total_sum;
    });
    aggregatedData = Object.values(aggregated);
  }

  // Добавляем проценты к каждому элементу
  return aggregatedData.map(item => ({
    ...item,
    quantity_percent: totalQuantity > 0 ? (item.quantity / totalQuantity * 100).toFixed(2) : 0,
    sum_percent: totalSum > 0 ? (item.total_sum / totalSum * 100).toFixed(2) : 0,
  }));
});

console.log(aggregatedProducts.value);

// Сохранение фильтра в localStorage (с датой)
const saveCurrentFilter = async () => {
  if (!newFilterName.value.trim()) {
    alert("Введіть назву фільтра!");
    return;
  }

  try {
    await axios.post('/statistics/saved-filters', {
      name: newFilterName.value.trim(),
      main_filter: filter,
      date_filter: mandatoryDateFilter
    });
    newFilterName.value = "";
    await loadSavedFilters();
  } catch (error) {
    console.error('Ошибка сохранения фильтра:', error);
    alert('Не удалось сохранить фильтр');
  }
};


const loadSavedFilters = async () => {
  try {
    const response = await axios.get('/statistics/saved-filters');
    savedFilters.value = response.data;
  } catch (error) {
    console.error('Ошибка загрузки фильтров:', error);
    savedFilters.value = [];
  }
};

const applySavedFilter = (savedData) => {
  Object.assign(filter, JSON.parse(JSON.stringify(savedData.main_filter)));
  Object.assign(mandatoryDateFilter, JSON.parse(JSON.stringify(savedData.date_filter)));
  
  // Загружаем вариации для продуктов, если есть такие фильтры
  loadVariationsForFilter(savedData.main_filter);
};

const deleteFilter = async (id) => {
  if (!confirm("Видалити фільтр?")) return;
  
  try {
    await axios.delete(`/statistics/saved-filters/${id}`);
    await loadSavedFilters();
  } catch (error) {
    console.error('Ошибка удаления фильтра:', error);
    alert('Не удалось удалить фильтр');
  }
};

onMounted(() => {
  loadUsers();
  loadDeliveryMethods();
  loadPaymentMethods();
  loadGroups();
  loadSavedFilters();
  loadProducts();

  const urlParams = new URLSearchParams(window.location.search);
  const filterParam = urlParams.get("filters");
  if (filterParam) {
    try {
      const parsed = JSON.parse(decodeURIComponent(filterParam));
      Object.assign(filter, parsed);
      loadVariationsForFilter(parsed);
    } catch (e) {
      console.warn("❌ Не вдалося розпарсити фільтри з URL:", e);
    }
  }
});

const loadUsers = async () => {
  const res = await axios.get("/users/getall");
  users.value = res.data.users;
};

const loadDeliveryMethods = async () => {
  const res = await axios.get("/delivery-methods/getall");
  deliveryMethods.value = res.data.delivery_methods;
};

const loadPaymentMethods = async () => {
  const res = await axios.get("/payment-methods/getall");
  paymentMethods.value = res.data.payment_methods;
};

const loadGroups = () => {
  if (groups.value.length > 0) {
    return;
  }
  axios.get('/groups/getall').then(response => {
    groups.value = response.data.groups;
  });
};

const loadProducts = async () => {
  const res = await axios.get("/products/getall");
  products.value = res.data.products;
};

const loadVariations = async (productId) => {
  if (variationsMap.value[productId]) return;
  const res = await axios.get(`/products/${productId}/variations`);
  variationsMap.value[productId] = res.data.variations.map(v => ({
    ...v,
    name: formatVariationName(v),
  }));
};

const loadVariationsForFilter = (group) => {
  const checkRules = (rules) => {
    rules.forEach(rule => {
      if (rule.rules) checkRules(rule.rules);
      else if (rule.field === "product_id" && rule.value) loadVariations(rule.value);
    });
  };
  checkRules(group.rules);
};

const dateFields = [
  { label: 'Дата створення', value: 'created_at' },
  { label: 'Дата оновлення', value: 'updated_at' },
  { label: 'Дата відправки', value: 'sent_at' },
  { label: 'Дата оплати', value: 'payment_date' },
  { label: 'Дата доставки', value: 'delivery_date' },
  { label: 'Дата переказу грошей від Inpost', value: 'inpost_payment_date' },
]

const fields = [
  { label: "Email", value: "email", type: "string" },
  { label: "Телефон", value: "phone", type: "string" },
  { label: "Ім'я одержувача", value: "delivery_fullname", type: "string" },
  { label: "Статус замовлення", value: "order_status_id", type: "multiselect", options: statuses },
  { label: "Метод оплати", value: "payment_method_id", type: "select", options: paymentMethods },
  { label: "Метод доставки", value: "delivery_method_id", type: "select", options: deliveryMethods },
  { label: "Група", value: "group_id", type: "select", options: groups },
  { label: "Відповідальний", value: "responsible_user_id", type: "select", options: users },
  { label: "Адреса доставки", value: "delivery_address", type: "string" },
  { label: "Номер будинку", value: "delivery_address_number", type: "string" },
  { label: "Додаткова адреса", value: "delivery_second_address", type: "string" },
  { label: "Поштовий індекс", value: "delivery_postcode", type: "string" },
  { label: "Місто", value: "delivery_city", type: "string" },
  { label: "Область/штат", value: "delivery_state", type: "string" },
  { label: "IP-адреса", value: "ip", type: "string" },
  { label: "Коментар", value: "comment", type: "string" },
  { label: "Website Reffer", value: "website_referrer", type: "string" },
  { label: "UTM Source", value: "utm_source", type: "string" },
  { label: "UTM Medium", value: "utm_medium", type: "string" },
  { label: "UTM Term", value: "utm_term", type: "string" },
  { label: "UTM Content", value: "utm_content", type: "string" },
  { label: "UTM Campaign", value: "utm_campaign", type: "string" },
  { label: "Дата доставки", value: "delivery_date", type: "date" },
  { label: "Дата відправки", value: "sent_at", type: "date" },
  { label: "Дата переказу грошей від Inpost", value: "inpost_payment_date", type: "date" },
  { label: "Дата оплати", value: "payment_date", type: "date" },
  { label: "Трекінг номер", value: "tracking_number", type: "string" },
  { label: "Оплачено", value: "is_paid", type: "boolean" },
  { label: "Сума сплачена клієнтом", value: "paid_amount", type: "number" },
  { label: "Сума замовлення", value: "calculated_total", type: "number" },
  { label: "Товар", value: "product_id", type: "select", options: products },
  { label: "Варіація товару", value: "product_variation_id", type: "select", options: [] },
  { label: "Категорія товару", value: "category_id", type: "select", options: categories },
  { label: "Кількість товарів в замовленні", value: "items_count", type: "number" },
  { label: "inpost_id", value: "inpost_id", type: "string" },
  { label: "inpost_status", value: "inpost_status", type: "string" },
  { label: "return_tracking_number", value: "return_tracking_number", type: "string" },
];

const operators = {
  string: ["містить", "не містить", "дорівнює", "не дорівнює", "є значення", "немає значення"],
  number: ["=", "!=", "<", "<=", ">", ">="],
  boolean: ["дорівнює"],
  date: ["між"],
  select: ["дорівнює", "не дорівнює"],
  multiselect: ["входить в", "не входить в"],
};

const getOperators = (type) => operators[type] || [];
const getField = (key) => fields.find((f) => f.value === key);
const getType = (field) => getField(field)?.type;
const getOptions = (field, rule = null) => {
  const options = getField(field)?.options;
  if (field === "product_variation_id" && rule?.productId) {
    return variationsMap.value[rule.productId] || [];
  }
  return options?.value ?? options ?? [];
};


const addRule = (group = filter.rules) => {
  group.push({ field: null, operator: null, value: null });
};

const addGroup = (group = filter.rules) => {
  group.push({ condition: "AND", rules: [] });
};

const removeRule = (group, index) => {
  const rule = group[index];
  if (rule.field === "product_id" && index + 1 < group.length && group[index + 1].field === "product_variation_id") {
    group.splice(index, 2);
  } else {
    group.splice(index, 1);
  }
};


const formatLocalDate = (date) => {
  if (!date) return null;
  const d = new Date(date);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};



watch(
  filter,
  () => {
    const handleOperatorChange = (rules) => {
      for (const rule of rules) {
        if (getType(rule.field) === "date") rule.operator = "між"; //Робимо МІЖ дефолтним
        if (rule.rules) handleOperatorChange(rule.rules);
        else if (rule._prevOperator !== rule.operator) {
          rule.value = rule.field === "product_variation_id" ? null : "";
          if (getType(rule.field) === "date" && rule.operator === "між") rule.value = [];
          rule._prevOperator = rule.operator;
        }
      }
    };

    const syncProductVariations = (rules) => {
      for (let i = 0; i < rules.length; i++) {
        const rule = rules[i];
        if (rule.rules) syncProductVariations(rule.rules);
        else if (rule.field === "product_id" && rule.value) {
          loadVariations(rule.value);
          const nextRule = i + 1 < rules.length ? rules[i + 1] : null;
          if (!nextRule || nextRule.field !== "product_variation_id") {
            rules.splice(i + 1, 0, {
              field: "product_variation_id",
              operator: "дорівнює",
              value: null,
              productId: rule.value,
            });
          } else {
            nextRule.productId = rule.value;
          }
        }
      }
    };

    handleOperatorChange(filter.rules);
    syncProductVariations(filter.rules);
  },
  { deep: true }
);

const formatVariationName = (variation) => {
  if (!variation || !variation.attributes) return "Без атрибутів";
  return variation.attributes
    .map((attr) => `${attr.attribute_name}: ${attr.attribute_value}`)
    .join(", ");
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

  <Head title="Статистика" />
  <Layout>
    <div class="p-6 space-y-6">






      <div class="flex gap-4">
        <div class="w-2/3">

          <div
            class="flex gap-3 items-center align-center mb-3  border-gray-200 border bg-white p-3 rounded-md shadow-sm">
            <h2 class="text-lg font-bold">Обов'язковий фільтр по даті:</h2>
            <div class="flex items-center gap-4 flex-wrap">
              <Select v-model="mandatoryDateFilter.field" :options="dateFields" optionLabel="label" optionValue="value"
                placeholder="Оберіть поле дати" class="w-auto" />

              <InputText v-model="mandatoryDateFilter.range[0]" type="date" class="border rounded px-2 py-1" />

              <InputText v-model="mandatoryDateFilter.range[1]" type="date" class="border rounded px-2 py-1" />


            </div>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200">

            <div class="flex items-center gap-3 mb-4">
              <span class="font-semibold text-sm text-gray-700">Головне поєднання умов:</span>
              <select v-model="filter.condition" class="border rounded px-2 py-1 bg-white">
                <option value="AND">AND</option>
                <option value="OR">OR</option>
              </select>
            </div>

            <div class="space-y-4">
              <template v-for="(rule, index) in filter.rules" :key="index">

                <!-- Одиночное правило на основном уровне -->
                <div v-if="!rule.rules"
                  class="flex items-center gap-3 bg-white p-3 rounded-md shadow-sm border border-gray-200 border-l-2 border-l-black">

                  <!--Выбор поля-->
                  <Select v-model="rule.field" optionValue="value" size="small" :options="fields" optionLabel="label"
                    filter filterPlaceholder="Пошук..." placeholder="Оберіть поле" class="w-60" />

                  <!--Выбор оператора-->

                  <Select v-model="rule.operator" :options="getOperators(getType(rule.field))"
                    class="w-48 border rounded" placeholder="Оператор" />

                  <!--Выбор значений-->
                  <Select v-if="getType(rule.field) === 'boolean'" v-model="rule.value"
                    :options="[{ label: 'Так', value: true }, { label: 'Ні', value: false }]" optionLabel="label"
                    optionValue="value" class="w-60 border rounded" placeholder="Оберіть значення" />
                  <Select v-if="getType(rule.field) === 'select' && rule.field !== 'product_variation_id'"
                    v-model="rule.value" :options="getOptions(rule.field, rule)" class="w-60 border rounded"
                    placeholder="Оберіть значення" optionLabel="name" optionValue="id" filter />
                  <Select v-if="rule.field === 'product_variation_id'" v-model="rule.value"
                    :options="getOptions(rule.field, rule)" class="w-60 border rounded" placeholder="Оберіть значення"
                    optionLabel="name" optionValue="id" filter />

                  <MultiSelect v-if="getType(rule.field) === 'multiselect'" v-model="rule.value"
                    :options="getOptions(rule.field, rule)" optionLabel="name" optionValue="id" filter class="w-60" />


                  <InputText v-if="getType(rule.field) === 'string'" v-model="rule.value"
                    class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                  <InputText v-if="getType(rule.field) === 'number'" v-model="rule.value" type="number"
                    class="w-60 border rounded px-2 py-1" placeholder="Число" />

                  <InputText v-if="getType(rule.field) === 'date' && rule.operator !== 'між'" v-model="rule.value"
                    type="date" class="w-60 border rounded px-2 py-1" />
                  <div v-if="getType(rule.field) === 'date' && rule.operator === 'між'" class="flex gap-2">
                    <InputText v-model="rule.value[0]" type="date" class="w-auto border rounded px-2 py-1" />
                    <InputText v-model="rule.value[1]" type="date" class="w-auto border rounded px-2 py-1" />
                  </div>
                  <button @click="removeRule(filter.rules, index)" class="text-red-500 hover:text-red-700">
                    <Trash class="w-5 h-5" />
                  </button>

                </div>

                <!-- Группа первого уровня -->
                <div v-else class="pl-6 border-l-2 border-black bg-white p-3 rounded-md shadow-sm">
                  <div class="flex items-center gap-3 mb-3">
                    <span class="font-semibold text-sm text-black">Група умов (Рівень 1):</span>
                    <select v-model="rule.condition" class="border rounded px-2 py-1 bg-white">
                      <option value="AND">AND</option>
                      <option value="OR">OR</option>
                    </select>
                    <button @click="removeRule(filter.rules, index)" class="text-red-500 hover:text-red-700">
                      <Trash class="w-5 h-5" />
                    </button>
                  </div>

                  <div class="space-y-3">
                    <template v-for="(subRule, subIndex) in rule.rules" :key="subIndex">
                      <!-- Одиночное правило внутри группы первого уровня -->
                      <div v-if="!subRule.rules"
                        class="flex items-center gap-3 bg-white p-3 rounded-md shadow-sm  border border-gray-200 ">


                        <!--Выбор поля-->
                        <Select v-model="subRule.field" optionValue="value" size="small" :options="fields"
                          optionLabel="label" filter filterPlaceholder="Пошук..." placeholder="Оберіть поле"
                          class="w-60" />

                        <!--Выбор оператора-->

                        <Select v-model="subRule.operator" :options="getOperators(getType(subRule.field))"
                          class="w-48 border rounded" placeholder="Оператор" />

                        <!--Выбор значений-->
                        <Select v-if="getType(subRule.field) === 'boolean'" v-model="subRule.value"
                          :options="[{ label: 'Так', value: true }, { label: 'Ні', value: false }]" optionLabel="label"
                          optionValue="value" class="w-60 border rounded" placeholder="Оберіть значення" />
                        <Select v-if="getType(subRule.field) === 'select' && subRule.field !== 'product_variation_id'"
                          v-model="subRule.value" :options="getOptions(subRule.field, subRule)"
                          class="w-60 border rounded" placeholder="Оберіть значення" optionLabel="name" optionValue="id"
                          filter />
                        <Select v-if="subRule.field === 'product_variation_id'" v-model="subRule.value"
                          :options="getOptions(subRule.field, subRule)" class="w-60 border rounded"
                          placeholder="Оберіть значення" optionLabel="name" optionValue="id" filter />

                        <MultiSelect v-if="getType(subRule.field) === 'multiselect'" v-model="subRule.value"
                          :options="getOptions(subRule.field, subRule)" optionLabel="name" optionValue="id" filter
                          class="w-60" />


                        <InputText v-if="getType(subRule.field) === 'string'" v-model="subRule.value"
                          class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                        <InputText v-if="getType(subRule.field) === 'number'" v-model="subRule.value" type="number"
                          class="w-60 border rounded px-2 py-1" placeholder="Число" />

                        <InputText v-if="getType(subRule.field) === 'date' && subRule.operator !== 'між'"
                          v-model="subRule.value" type="date" class="w-60 border rounded px-2 py-1" />
                        <div v-if="getType(subRule.field) === 'date' && subRule.operator === 'між'" class="flex gap-2">
                          <InputText v-model="subRule.value[0]" type="date" class="w-auto border rounded px-2 py-1" />
                          <InputText v-model="subRule.value[1]" type="date" class="w-auto border rounded px-2 py-1" />
                        </div>

                        <button @click="removeRule(rule.rules, subIndex)" class="text-red-500 hover:text-red-700">
                          <Trash class="w-5 h-5" />
                        </button>
                      </div>

                      <!-- Группа второго уровня -->
                      <div v-else
                        class="pl-6 border-l-2 border-black border-t border-t-gray-200 border-b border-b-gray-200 border-r border-r-gray-200 bg-white p-3 rounded-md shadow-sm">
                        <div class="flex items-center gap-3 mb-3">
                          <span class="font-semibold text-sm text-black">Група умов (Рівень 2):</span>
                          <select v-model="subRule.condition" class="border rounded px-2 py-1 bg-white">
                            <option value="AND">AND</option>
                            <option value="OR">OR</option>
                          </select>
                          <button @click="removeRule(rule.rules, subIndex)" class="text-red-500 hover:text-red-700">
                            <Trash class="w-5 h-5" />
                          </button>
                        </div>

                        <div class="space-y-3">
                          <template v-for="(deepRule, deepIndex) in subRule.rules" :key="deepIndex">
                            <div
                              class="flex items-center gap-3 bg-white p-3 rounded-md shadow-sm border border-gray-200">

                              <!--Выбор поля-->
                              <Select v-model="deepRule.field" optionValue="value" size="small" :options="fields"
                                optionLabel="label" filter filterPlaceholder="Пошук..." placeholder="Оберіть поле"
                                class="w-60" />

                              <!--Выбор оператора-->

                              <Select v-model="deepRule.operator" :options="getOperators(getType(deepRule.field))"
                                class="w-48 border rounded" placeholder="Оператор" />

                              <!--Выбор значений-->
                              <Select v-if="getType(deepRule.field) === 'boolean'" v-model="deepRule.value"
                                :options="[{ label: 'Так', value: true }, { label: 'Ні', value: false }]"
                                optionLabel="label" optionValue="value" class="w-60 border rounded"
                                placeholder="Оберіть значення" />
                              <Select
                                v-if="getType(deepRule.field) === 'select' && deepRule.field !== 'product_variation_id'"
                                v-model="deepRule.value" :options="getOptions(deepRule.field, deepRule)"
                                class="w-60 border rounded" placeholder="Оберіть значення" optionLabel="name"
                                optionValue="id" filter />
                              <Select v-if="deepRule.field === 'product_variation_id'" v-model="deepRule.value"
                                :options="getOptions(deepRule.field, deepRule)" class="w-60 border rounded"
                                placeholder="Оберіть значення" optionLabel="name" optionValue="id" filter />

                              <MultiSelect v-if="getType(deepRule.field) === 'multiselect'" v-model="deepRule.value"
                                :options="getOptions(deepRule.field, deepRule)" optionLabel="name" optionValue="id"
                                filter class="w-60" />


                              <InputText v-if="getType(deepRule.field) === 'string'" v-model="deepRule.value"
                                class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                              <InputText v-if="getType(deepRule.field) === 'number'" v-model="deepRule.value"
                                type="number" class="w-60 border rounded px-2 py-1" placeholder="Число" />

                              <InputText v-if="getType(deepRule.field) === 'date' && deepRule.operator !== 'між'"
                                v-model="deepRule.value" type="date" class="w-60 border rounded px-2 py-1" />
                              <div v-if="getType(deepRule.field) === 'date' && deepRule.operator === 'між'"
                                class="flex gap-2">
                                <InputText v-model="deepRule.value[0]" type="date"
                                  class="w-auto border rounded px-2 py-1" />
                                <InputText v-model="deepRule.value[1]" type="date"
                                  class="w-auto border rounded px-2 py-1" />
                              </div>



                              <button @click="removeRule(subRule.rules, deepIndex)"
                                class="text-red-500 hover:text-red-700">
                                <Trash class="w-5 h-5" />
                              </button>
                            </div>
                          </template>
                        </div>

                        <div class="flex gap-2">
                          <Button size="small" variant="link" @click="addRule(subRule.rules)" label="+ Умова" />
                        </div>
                      </div>
                    </template>
                  </div>

                  <div class="flex gap-2 mt-3">
                    <Button size="small" variant="link" @click="addRule(rule.rules)" label="+ Умова" />
                    <Button size="small" @click="addGroup(rule.rules)" severity="secondary" label="+ Група" />

                  </div>
                </div>
              </template>
            </div>

            <div class="flex gap-3 mt-6">

              <Button variant="link" @click="addRule(filter.rules)" label="+ Додати правило" />
              <Button @click="addGroup(filter.rules)" severity="secondary" label="+ Додати групу" />
              <Button @click="resetAllFilters" severity="danger" label="Скинути всі фільтри" />
              <Button @click="loadStatistics" :disabled="isLoading">
                <span v-if="isLoading">Завантаження...</span>
                <span class="" v-else>🔍 Застосувати фільтри</span>
              </Button>


            </div>

          </div>



        </div>

        <div class="w-1/3">
          <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="flex gap-2 items-end mb-4">
              <input v-model="newFilterName" placeholder="Назва фільтра" class="w-64 border rounded px-2 py-1" />
              <button @click="saveCurrentFilter" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">💾
                Зберегти</button>
            </div>


            <div v-if="savedFilters.length" class="space-y-2">
              <div class="font-semibold text-gray-700">📂 Збережені фільтри:</div>
              <div class="flex flex-wrap gap-2">
                <div v-for="f in savedFilters" :key="f.id"
                  class="flex items-center gap-2 border border-gray-300 px-3 py-1 rounded cursor-pointer hover:bg-gray-100 transition"
                  @click="applySavedFilter(f)">
                  <span class="text-sm font-medium">{{ f.name }}</span>
                  <button @click.stop="deleteFilter(f.id)" class="text-red-500 hover:text-red-700">
                    <Trash class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>


          </div>
          <div class="mt-5">
            <Fieldset legend="JSON дебаг:" :toggleable="true" :collapsed="true">
              <pre class="bg-gray-100 p-3 rounded text-xs">{{ JSON.stringify(filter, null, 2) }}</pre>
            </Fieldset>
          </div>
        </div>
      </div>


      <div class="mt-6" v-if="stats.order_count">
        <h2 class="text-2xl font-bold">📊 Статистика</h2>
        <div class="grid grid-cols-2 gap-4 mt-3">
          <div class="bg-gray-100 p-4 rounded-lg">
            <p class="font-semibold">Кількість замовлень: {{ stats?.order_count || 0 }}</p>

            <p>Кількість товарів загальна: {{ (stats.items_count_non_services || 0) + (stats.items_count_services || 0)
            }}</p>
            <p>Кількість товарів (не Services): {{ (stats.items_count_non_services || 0) }}</p>
            <p>Кількість товарів (Services): {{ (stats.items_count_services || 0) }}</p>
          </div>

          <div class="bg-gray-100 p-4 rounded-lg">
            <p>
              Сума загальна: {{ ((stats?.total_sum_non_services || 0) + (stats?.total_sum_services || 0)).toFixed(2)
              }}
              (ПДВ {{ (((stats.total_sum_non_services || 0) + (stats.total_sum_services || 0)) * 0.23).toFixed(2) }})
            </p>
            <p>Сума Основна: {{ (stats.total_sum_non_services || 0).toFixed(2) }}</p>
            <p>Сума Сервісна: {{ (stats.total_sum_services || 0).toFixed(2) }}</p>
          </div>
        </div>
      </div>

      <div class="pt-6">

        <div class="flex gap-4 mt-2">

          <div class="w-2/3" v-if="aggregatedProducts[0]">
            <div class="flex justify-between">
              <h2 class="text-xl font-bold">📦 Статистика по товарах</h2>
              <div class="flex items-center gap-3">


                <Select v-model="aggregationMode" optionValue="value" size="small" :options="aggregationModes"
                  optionLabel="label" placeholder="Агрегація" class="w-full" />

              </div>
            </div>
            <DataTable :value="aggregatedProducts" class="mt-5" showGridlines scrollable size="small"
              :sortField="'category_name'" :sortOrder="1">
              <Column field="product_name" header="Назва товару" sortable
                v-if="aggregationMode !== 'by_category' && aggregationMode !== 'by_attributes'">
                <template #body="{ data }">
                  <span>{{ data.product_name || '—' }}</span>
                </template>
              </Column>
              <Column field="attributes" header="Атрибути" sortable
                v-if="aggregationMode === 'by_product_with_attributes' || aggregationMode === 'by_attributes'">
                <template #body="{ data }">
                  <span>{{ data.attributes || 'Без атрибутів' }}</span>
                </template>
              </Column>
              <Column field="category_name" header="Категорія" sortable v-if="aggregationMode !== 'by_attributes'">
                <template #body="{ data }">
                  <span>{{ data.category_name || '—' }}</span>
                </template>
              </Column>
              <Column field="quantity" header="Кількість" sortable />
              <Column field="total_sum" header="Сума" sortable>
                <template #body="{ data }">
                  <span>{{ data.total_sum.toFixed(2) }}</span>
                </template>
              </Column>
              <Column field="quantity_percent" sortable
                header="Кількість товару в замовленнях / Кількість замовлень * 100">
                <template #body="{ data }">
                  <span>{{ data.quantity_percent }}%</span>
                </template>
              </Column>
              <Column field="sum_percent" sortable header="Сума товару / Загальну суму замовлень * 100">
                <template #body="{ data }">
                  <span>{{ data.sum_percent }}%</span>
                </template>
              </Column>
            </DataTable>
          </div>
          <div class="w-1/3" v-if="stats.status_stats && stats.status_stats[0].count">
            <h2 class="text-xl font-bold">📉 Статистика по статусах</h2>
            <div class="bg-white border border-gray-200 p-4 mt-6">
              <div class="space-y-2">
                <div v-for="stat in stats.status_stats" :key="stat.name" class="flex items-center text-sm ">
                  <!-- Название статуса -->
                  <span class="w-32 text-gray-600">{{ stat.name }}</span>

                  <!-- Прогресс-бар -->
                  <div class="flex-1 mx-2 bg-gray-100 rounded-full h-2.5">
                    <div class="h-full rounded-full bg-blue-400"
                      :style="{ width: `${(stat.count / stats.order_count * 100).toFixed(2)}%` }"></div>
                  </div>

                  <!-- Числовые значения -->
                  <div class="flex w-24 justify-between">
                    <span class="text-gray-700 font-medium">
                      {{ (stat.count / stats.order_count * 100).toFixed(2) }}%
                    </span>
                    <span class="text-gray-500">
                      ({{ stat.count }})
                    </span>
                  </div>

                </div>
              </div>
            </div>
          </div>
          <div v-else class="w-1/3">
            <h2 class="text-xl font-bold">Немає даних для відображення</h2>
            
          </div>


        </div>

      </div>



      <DataTable :value="orders" resizableColumns columnResizeMode="expand" paginator :rows="20" v-if="orders[0]"
        :total-records="orders.length" :rowsPerPageOptions="[5, 10, 20, 50]" showGridlines dataKey="id" scrollable
        size="small">
        <Column field="id" header="ID" bodyStyle="text-align:center" style="min-width:50px;" />
        <Column class="w-[40px]" header="Статус">
          <template #body="{ data }">
            <span v-if="data.status" class="rounded flex items-center justify-center p-1 text-white text-xs"
              :style="{ backgroundColor: `#${data.status.color}` }">{{ data.status.name }}</span>
            <span v-else class="rounded flex items-center justify-center p-1 text-white bg-black text-xs">Без
              статусу</span>
          </template>
        </Column>
        <Column field="calculated_total" header="Сума товарів">
          <template #body="{ data }">{{ Number(data.calculated_total).toFixed(2) }}</template>
        </Column>
        <Column field="delivery_fullname" header="Контакт" />
        <Column field="phone" header="Телефон" />
        <Column field="email" header="Email" />
        <Column field="comment" header="Коментар" bodyClass="cursor-help" bodyStyle="max-width:250px">
          <template #body="{ data }">
            <div class="w-full h-full truncate"
              v-tooltip.top="{ value: data.comment, showDelay: 1000, hideDelay: 300, class: 'text-sm' }">{{ data.comment
              }}</div>
          </template>
        </Column>
        <Column header="Товари" bodyStyle="max-width:300px">
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

        </Column>
        <Column field="responsible_user.name" header="Відповідальний" />
        <Column field="delivery_city" header="Місто" />
        <Column field="delivery_address" header="Адреса" />
        <Column field="delivery_postcode" header="Зіп код" />
        <Column field="payment_method.name" header="Метод оплати" />
        <Column class="w-[40px]" header="Оплата" field="is_paid">
          <template #body="{ data }">
            <span v-if="data.is_paid"
              class="rounded flex items-center justify-center p-1 text-white text-xs bg-green-500">Оплачено</span>
            <span v-else class="rounded flex items-center justify-center p-1 text-white bg-black text-xs">Не
              оплачено</span>
          </template>
        </Column>
        <Column field="delivery_method.name" header="Доставка" />
        <Column header="Дата отримання">
          <template #body="{ data }">{{ formatLocalDate(data.delivery_date) }}</template>
        </Column>
        <Column header="Відправлено">
          <template #body="{ data }">{{ formatLocalDate(data.sent_at) }}</template>
        </Column>
        <Column header="Дата переказу грошей від Inpost">
          <template #body="{ data }">{{ formatLocalDate(data.inpost_payment_date) }}</template>
        </Column>
        <Column field="tracking_number" header="Трекинг" />
        <Column field="group.name" header="Група" />
        <Column field="ip" header="IP" />
        <Column field="website_referrer" header="Website Reffer" />
        <Column field="utm_source" header="utm_source" />
        <Column field="utm_medium" header="utm_medium" />
        <Column field="utm_campaign" header="utm_campaign" />
        <Column field="utm_content" header="utm_content" />
        <Column field="utm_term" header="utm_term" />
        <Column header="created_at">
          <template #body="{ data }">{{ formatLocalDate(data.created_at) }}</template>
        </Column>
        <Column header="updated_at">
          <template #body="{ data }">{{ formatLocalDate(data.updated_at) }}</template>
        </Column>
      </DataTable>
    </div>
  </Layout>
</template>