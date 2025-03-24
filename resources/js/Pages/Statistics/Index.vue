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
const products = ref([]);
const variationsMap = ref({});
const savedFilters = ref([]);
const newFilterName = ref("");


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

const saveCurrentFilter = () => {
  if (!newFilterName.value.trim()) {
    alert("Введіть назву фільтра!");
    return;
  }
  const all = JSON.parse(localStorage.getItem("filterTemplates") || "[]");
  all.push({
    name: newFilterName.value.trim(),
    data: JSON.parse(JSON.stringify(filter)),
  });
  localStorage.setItem("filterTemplates", JSON.stringify(all));
  newFilterName.value = "";
  loadSavedFilters();
};

const loadSavedFilters = () => {
  try {
    savedFilters.value = JSON.parse(localStorage.getItem("filterTemplates") || "[]");
  } catch {
    savedFilters.value = [];
  }
};

const applySavedFilter = (data) => {
  Object.assign(filter, JSON.parse(JSON.stringify(data)));
  loadVariationsForFilter(filter);
};

const deleteFilter = (index) => {
  if (!confirm("Видалити фільтр?")) return;
  const all = [...savedFilters.value];
  all.splice(index, 1);
  localStorage.setItem("filterTemplates", JSON.stringify(all));
  loadSavedFilters();
};

onMounted(() => {
  loadUsers();
  loadDeliveryMethods();
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

const fields = [
  { label: "Email", value: "email", type: "string" },
  { label: "Телефон", value: "phone", type: "string" },
  { label: "Ім'я одержувача", value: "delivery_fullname", type: "string" },
  { label: "Статуси", value: "order_status_id", type: "multiselect", options: statuses },
  { label: "Оплата", value: "is_paid", type: "boolean" },
  { label: "Місто", value: "delivery_city", type: "string" },
  { label: "Дата доставки", value: "delivery_date", type: "date" },
  { label: "Дата створення", value: "created_at", type: "date" },
  { label: "Сума оплати", value: "paid_amount", type: "number" },
  { label: "Менеджер", value: "responsible_user_id", type: "select", options: users },
  { label: "Метод доставки", value: "delivery_method_id", type: "select", options: deliveryMethods },
  { label: "Сума замовлення", value: "calculated_total", type: "number" },
  { label: "Товар", value: "product_id", type: "select", options: products },
  { label: "Варіація товару", value: "product_variation_id", type: "select", options: [] },
  { label: "Категорія товару", value: "category_id", type: "select", options: categories },
];

const operators = {
  string: ["містить", "не містить", "дорівнює", "не дорівнює"],
  number: ["=", "!=", "<", "<=", ">", ">="],
  boolean: ["дорівнює"],
  date: ["до", "після", "дорівнює", "між"],
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

const filter = reactive({
  condition: "AND",
  rules: [],
});

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

const resetFilter = () => {
  filter.condition = "AND";
  filter.rules = [];
};

const formatLocalDate = (date) => {
  if (!date) return null;
  const d = new Date(date);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

const applyFilter = () => {
  const normalized = JSON.parse(JSON.stringify(filter));
  const normalizeRules = (rules) => {
    return rules.map((rule) => {
      if (rule.rules) {
        rule.rules = normalizeRules(rule.rules);
        return rule;
      }
      const type = getType(rule.field);
      if (type === "date" && rule.value) {
        if (rule.operator === "між") {
          rule.value = Array.isArray(rule.value) && rule.value.length === 2
            ? rule.value.map(val => formatLocalDate(val))
            : [null, null];
        } else {
          rule.value = formatLocalDate(rule.value);
        }
      }
      return rule;
    });
  };
  normalized.rules = normalizeRules(normalized.rules);

  router.get(
    "/statistics",
    { filters: JSON.stringify(normalized) },
    {
      preserveState: true,
      onSuccess: (page) => {
        orders.value = page.props.orders;
        Object.assign(stats.value, page.props.stats);
        productsStats.value = page.props.products_stats;
      },
    }
  );
};

watch(
  filter,
  () => {
    const handleOperatorChange = (rules) => {
      for (const rule of rules) {
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
      <h1 class="text-2xl font-bold">🧠 Конструктор фільтрів</h1>

      <div class="flex gap-4">
        <div class="w-2/3">
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
                <div v-if="!rule.rules" class="flex items-center gap-3 bg-white p-3 rounded-md shadow-sm border border-gray-200 border-l-2 border-l-black">
                  <select v-model="rule.field" class="w-56 border rounded px-2 py-1" placeholder="Поле">
                    <option v-for="field in fields" :value="field.value" :key="field.value">{{ field.label }}</option>
                  </select>
                  <select v-model="rule.operator" class="w-48 border rounded px-2 py-1" placeholder="Оператор">
                    <option v-for="op in getOperators(getType(rule.field))" :value="op" :key="op">{{ op }}</option>
                  </select>
                  <input v-if="getType(rule.field) === 'string'" v-model="rule.value" class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                  <input v-if="getType(rule.field) === 'number'" v-model="rule.value" type="number" class="w-60 border rounded px-2 py-1" placeholder="Число" />
                  <select v-if="getType(rule.field) === 'boolean'" v-model="rule.value" class="w-48 border rounded px-2 py-1">
                    <option :value="true">Так</option>
                    <option :value="false">Ні</option>
                  </select>
                  <select v-if="getType(rule.field) === 'select' && rule.field !== 'product_variation_id'" v-model="rule.value" class="w-60 border rounded px-2 py-1">
                    <option v-for="option in getOptions(rule.field, rule)" :value="option.id" :key="option.id">{{ option.name }}</option>
                  </select>
                  <select v-if="rule.field === 'product_variation_id'" v-model="rule.value" class="w-60 border rounded px-2 py-1" placeholder="Варіація">
                    <option v-for="option in getOptions(rule.field, rule)" :value="option.id" :key="option.id">{{ option.name }}</option>
                  </select>
                
                  <MultiSelect v-if="getType(rule.field) === 'multiselect'" v-model="rule.value" :options="getOptions(rule.field, rule)" optionLabel="name" optionValue="id" filter 
                   class="w-60" />
                  <input v-if="getType(rule.field) === 'date' && rule.operator !== 'між'" v-model="rule.value" type="date" class="w-60 border rounded px-2 py-1" />
                  <div v-if="getType(rule.field) === 'date' && rule.operator === 'між'" class="flex gap-2">
                    <input v-model="rule.value[0]" type="date" class="w-28 border rounded px-2 py-1" />
                    <input v-model="rule.value[1]" type="date" class="w-28 border rounded px-2 py-1" />
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
                      <div v-if="!subRule.rules" class="flex items-center gap-3 bg-white p-3 rounded-md shadow-sm  border border-gray-200 ">
                        <select v-model="subRule.field" class="w-56 border rounded px-2 py-1" placeholder="Поле">
                          <option v-for="field in fields" :value="field.value" :key="field.value">{{ field.label }}</option>
                        </select>
                        <select v-model="subRule.operator" class="w-48 border rounded px-2 py-1" placeholder="Оператор">
                          <option v-for="op in getOperators(getType(subRule.field))" :value="op" :key="op">{{ op }}</option>
                        </select>
                        <input v-if="getType(subRule.field) === 'string'" v-model="subRule.value" class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                        <input v-if="getType(subRule.field) === 'number'" v-model="subRule.value" type="number" class="w-60 border rounded px-2 py-1" placeholder="Число" />
                        <select v-if="getType(subRule.field) === 'boolean'" v-model="subRule.value" class="w-48 border rounded px-2 py-1">
                          <option :value="true">Так</option>
                          <option :value="false">Ні</option>
                        </select>
                        <select v-if="getType(subRule.field) === 'select' && subRule.field !== 'product_variation_id'" v-model="subRule.value" class="w-60 border rounded px-2 py-1">
                          <option v-for="option in getOptions(subRule.field, subRule)" :value="option.id" :key="option.id">{{ option.name }}</option>
                        </select>
                        <select v-if="subRule.field === 'product_variation_id'" v-model="subRule.value" class="w-60 border rounded px-2 py-1" placeholder="Варіація">
                          <option v-for="option in getOptions(subRule.field, subRule)" :value="option.id" :key="option.id">{{ option.name }}</option>
                        </select>
                    
                        <MultiSelect v-if="getType(subRule.field) === 'multiselect'" v-model="subRule.value" :options="getOptions(subRule.field, rule)" optionLabel="name" optionValue="id" filter 
                          class="w-60" />
                        <input v-if="getType(subRule.field) === 'date' && subRule.operator !== 'між'" v-model="subRule.value" type="date" class="w-60 border rounded px-2 py-1" />
                        <div v-if="getType(subRule.field) === 'date' && subRule.operator === 'між'" class="flex gap-2">
                          <input v-model="subRule.value[0]" type="date" class="w-28 border rounded px-2 py-1" />
                          <input v-model="subRule.value[1]" type="date" class="w-28 border rounded px-2 py-1" />
                        </div>
                        <button @click="removeRule(rule.rules, subIndex)" class="text-red-500 hover:text-red-700">
                          <Trash class="w-5 h-5" />
                        </button>
                      </div>

                      <!-- Группа второго уровня -->
                      <div v-else class="pl-6 border-l-2 border-black border-t border-t-gray-200 border-b border-b-gray-200 border-r border-r-gray-200 bg-white p-3 rounded-md shadow-sm">
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
                            <div class="flex items-center gap-3 bg-white p-3 rounded-md shadow-sm border border-gray-200">
                              <select v-model="deepRule.field" class="w-56 border rounded px-2 py-1" placeholder="Поле">
                                <option v-for="field in fields" :value="field.value" :key="field.value">{{ field.label }}</option>
                              </select>
                              <select v-model="deepRule.operator" class="w-48 border rounded px-2 py-1" placeholder="Оператор">
                                <option v-for="op in getOperators(getType(deepRule.field))" :value="op" :key="op">{{ op }}</option>
                              </select>
                              <input v-if="getType(deepRule.field) === 'string'" v-model="deepRule.value" class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                              <input v-if="getType(deepRule.field) === 'number'" v-model="deepRule.value" type="number" class="w-60 border rounded px-2 py-1" placeholder="Число" />
                              <select v-if="getType(deepRule.field) === 'boolean'" v-model="deepRule.value" class="w-48 border rounded px-2 py-1">
                                <option :value="true">Так</option>
                                <option :value="false">Ні</option>
                              </select>
                              <select v-if="getType(deepRule.field) === 'select' && deepRule.field !== 'product_variation_id'" v-model="deepRule.value" class="w-60 border rounded px-2 py-1">
                                <option v-for="option in getOptions(deepRule.field, deepRule)" :value="option.id" :key="option.id">{{ option.name }}</option>
                              </select>
                              <select v-if="deepRule.field === 'product_variation_id'" v-model="deepRule.value" class="w-60 border rounded px-2 py-1" placeholder="Варіація">
                                <option v-for="option in getOptions(deepRule.field, deepRule)" :value="option.id" :key="option.id">{{ option.name }}</option>
                              </select>
                        
                              <MultiSelect v-if="getType(deepRule.field) === 'multiselect'" v-model="deepRule.value" :options="getOptions(deepRule.field, deepRule)" optionLabel="name" optionValue="id" filter 
                              class="w-60" />
                              <input v-if="getType(deepRule.field) === 'date' && deepRule.operator !== 'між'" v-model="deepRule.value" type="date" class="w-60 border rounded px-2 py-1" />
                              <div v-if="getType(deepRule.field) === 'date' && deepRule.operator === 'між'" class="flex gap-2">
                                <input v-model="deepRule.value[0]" type="date" class="w-28 border rounded px-2 py-1" />
                                <input v-model="deepRule.value[1]" type="date" class="w-28 border rounded px-2 py-1" />
                              </div>
                              <button @click="removeRule(subRule.rules, deepIndex)" class="text-red-500 hover:text-red-700">
                                <Trash class="w-5 h-5" />
                              </button>
                            </div>
                          </template>
                        </div>

                        <div class="flex gap-2">
                          <Button size="small" variant="link" @click="addRule(subRule.rules)"  label="+ Умова" />
                        </div>
                      </div>
                    </template>
                  </div>

                  <div class="flex gap-2 mt-3">
                    <Button size="small" variant="link" @click="addRule(rule.rules)"  label="+ Умова" />
                    <Button size="small" @click="addGroup(rule.rules)"  label="+ Група" />

                  </div>
                </div>
              </template>
            </div>

            <div class="flex gap-3 mt-6">
              <button @click="addRule(filter.rules)" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">+ Додати правило</button>
              <button @click="addGroup(filter.rules)" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">+ Додати групу</button>
              <button @click="resetFilter" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Скинути</button>
              <button @click="applyFilter" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">🔍 Застосувати</button>
            </div>
          </div>
        </div>

        <div class="w-1/3">
          <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="flex gap-2 items-end mb-4">
              <input v-model="newFilterName" placeholder="Назва фільтра" class="w-64 border rounded px-2 py-1" />
              <button @click="saveCurrentFilter" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">💾 Зберегти</button>
            </div>
            <div v-if="savedFilters.length" class="space-y-2">
              <div class="font-semibold text-gray-700">📂 Збережені фільтри:</div>
              <div class="flex flex-wrap gap-2">
                <div v-for="(f, index) in savedFilters" :key="index" class="flex items-center gap-2 border border-gray-300 px-3 py-1 rounded cursor-pointer hover:bg-gray-100 transition" @click="applySavedFilter(f.data)">
                  <span class="text-sm font-medium">{{ f.name }}</span>
                  <button @click.stop="deleteFilter(index)" class="text-red-500 hover:text-red-700">
                    <Trash class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-5">
            <Fieldset legend="JSON для перевірки:" :toggleable="true" :collapsed="true">
              <pre class="bg-gray-100 p-3 rounded text-xs">{{ JSON.stringify(filter, null, 2) }}</pre>
            </Fieldset>
          </div>
        </div>
      </div>

      <div class="mt-6">
          <h2 class="text-2xl font-bold">📊 Статистика</h2>
          <div class="grid grid-cols-2 gap-4 mt-2">
            <div class="bg-gray-100 p-4 rounded-lg">
              <p class="font-semibold">Кількість замовлень: {{ stats.order_count }}</p>

              <p>Кількість товарів загальна: {{ (stats.items_count_non_services + stats.items_count_services) }}</p>
              <p>Кількість товарів (не Services): {{ stats.items_count_non_services }}</p>
              <p>Кількість товарів (Services): {{ stats.items_count_services }}</p>
            </div>
            <div class="bg-gray-100 p-4 rounded-lg">
              <p>
                Сума загальна: {{ (stats.total_sum_non_services + stats.total_sum_services).toFixed(2) }} 
                (ПДВ {{ ((stats.total_sum_non_services + stats.total_sum_services) * 0.23).toFixed(2) }})
              </p>
              <p>Сума Основна: {{ stats.total_sum_non_services.toFixed(2) }}</p>
              <p>Сума Сервісна: {{ stats.total_sum_services.toFixed(2) }}</p>
            </div>
          </div>

          <div class="mt-6">
          <h2 class="text-xl font-bold">📦 Статистика по товарах</h2>
          <div class="flex items-center gap-3 mt-2">
            <span class="font-semibold text-sm">Режим відображення:</span>
            <select v-model="aggregationMode" class="border rounded px-2 py-1 bg-white">
              <option v-for="mode in aggregationModes" :value="mode.value" :key="mode.value">{{ mode.label }}</option>
            </select>
          </div>
          <DataTable :value="aggregatedProducts" class="mt-2" showGridlines scrollable size="small">
            <Column field="product_name" header="Назва товару" v-if="aggregationMode !== 'by_category' && aggregationMode !== 'by_attributes'">
              <template #body="{ data }">
                <span>{{ data.product_name || '—' }}</span>
              </template>
            </Column>
            <Column field="attributes" header="Атрибути" v-if="aggregationMode === 'by_product_with_attributes' || aggregationMode === 'by_attributes'">
              <template #body="{ data }">
                <span>{{ data.attributes || 'Без атрибутів' }}</span>
              </template>
            </Column>
            <Column field="category_name" header="Категорія" v-if="aggregationMode !== 'by_attributes'">
              <template #body="{ data }">
                <span>{{ data.category_name || '—' }}</span>
              </template>
            </Column>
            <Column field="quantity" header="Кількість" />
            <Column field="total_sum" header="Сума">
              <template #body="{ data }">
                <span>{{ data.total_sum.toFixed(2) }}</span>
              </template>
            </Column>
            <Column field="quantity_percent" header="Кількість товару в замовленнях / Кількість замовлень * 100">
              <template #body="{ data }">
                <span>{{ data.quantity_percent }}%</span>
              </template>
            </Column>
            <Column field="sum_percent" header="Сума товару / Загальну суму замовлень * 100">
              <template #body="{ data }">
                <span>{{ data.sum_percent }}%</span>
              </template>
            </Column>
          </DataTable>
        </div>
          
        </div>



      <DataTable :value="orders" resizableColumns columnResizeMode="expand" paginator :rows="20" :total-records="orders.length" :rowsPerPageOptions="[5, 10, 20, 50]" showGridlines dataKey="id" scrollable size="small">
        <Column field="id" header="ID" bodyStyle="text-align:center" style="min-width:50px;" />
        <Column class="w-[40px]" header="Статус">
          <template #body="{ data }">
            <span v-if="data.status" class="rounded flex items-center justify-center p-1 text-white text-xs" :style="{ backgroundColor: `#${data.status.color}` }">{{ data.status.name }}</span>
            <span v-else class="rounded flex items-center justify-center p-1 text-white bg-black text-xs">Без статусу</span>
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
            <div class="w-full h-full truncate" v-tooltip.top="{ value: data.comment, showDelay: 1000, hideDelay: 300, class: 'text-sm' }">{{ data.comment }}</div>
          </template>
        </Column>
        <Column header="Товари" bodyStyle="max-width:300px" >
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
            <span v-if="data.is_paid" class="rounded flex items-center justify-center p-1 text-white text-xs bg-green-500">Оплачено</span>
            <span v-else class="rounded flex items-center justify-center p-1 text-white bg-black text-xs">Не оплачено</span>
          </template>
        </Column>
        <Column field="delivery_method.name" header="Доставка" />
        <Column header="Дата отримання">
          <template #body="{ data }">{{ formatLocalDate(data.delivery_date) }}</template>
        </Column>
        <Column header="Відправлено">
          <template #body="{ data }">{{ formatLocalDate(data.sent_at) }}</template>
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