<script setup>
import { ref } from 'vue';
import Layout from '../../Layout/App.vue';
import { Link, router, usePage, Head, useForm } from "@inertiajs/vue3";
import {
  ExternalLink,
  Trash,
  Dot
} from "lucide-vue-next";


import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();


// Получаем начальные данные из Inertia
const { props: inertiaProps } = usePage();
const products = ref(inertiaProps.data || []);
const categories = ref(inertiaProps.categories || []);
const modalVisible = ref(false); // Для отображения модального окна

const form = useForm({
    name: '',
    sku: '',
    description: '',
    type: '',
    price: '',
    discounted_price: '',
    cost: '',
    stock: '',
    status: 'active',
    category_id: null,
});

// Открытие модального окна
const openModalCreateProduct = () => {
    form.reset();
    modalVisible.value = true;
};

// Сохранение продукта
const saveProduct = () => {
    form.post('/products', {
        onSuccess: () => {
            modalVisible.value = false;
            products.value = page.props.data;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: page.props.flash.success,
                life: 3000,
            });
            
        },
        onError: (error) => {
            const errorMessages = Object.values(error).flat().join('\n');
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errorMessages,
                life: 5000,
            });
        },
    });
};


// Управление состоянием таблицы
const perPage = ref(products.per_page || 10);
const currentPage = ref(products.current_page || 1);
const sortBy = ref('created_at');
const sortDirection = ref('desc');

// Обработчик изменения страницы и количества строк
const onPageChange = (event) => {
  currentPage.value = event.page + 1;
  perPage.value = event.rows;

  loadProducts();
};

// Обработчик изменения сортировки
const onSortChange = (event) => {
  sortBy.value = event.sortField;
  sortDirection.value = event.sortOrder === 1 ? 'asc' : 'desc';

  loadProducts();
};

// Функция загрузки данных
const loadProducts = () => {
  router.get('/products', {
    page: currentPage.value,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_direction: sortDirection.value,
  }, { preserveState: true,
    onSuccess: (page) => {
      products.value = page.props.data;
    }
   });
};

const confirmDelete = (event, data) => {

confirm.require({
    target: event.currentTarget,
    message: 'Ви дійсно хочете видалити?',
    rejectProps: {
        label: 'Ні',
        severity: 'secondary',
        outlined: true
    },
    acceptProps: {
        label: 'Так'
    },
    accept: () => {

        router.delete(`/products/${data.id}`, {
            onSuccess: () => {
              products.value = page.props.data;

                toast.add({ severity: 'info', summary: 'Confirmed', detail: page.props.flash.success, life: 3000 });
            },
            onError: (error) => {
                const errorMessages = Object.values(error).flat().join("\n");
                toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errorMessages,
                life: 5000,
                });
            },
            });



        
    }
});
}



function getStatusName(value) {
    const status = statuses.find(status => status.value === value);
    return status ? status.name : value; // Если не найдено соответствие, возвращается исходное значение
}

const types = [
    { name: 'Простий', value: 'simple' },
    { name: 'Варіативний', value: 'variable' },
];
const statuses = [
    { name: 'Активний', value: 'active' },
    { name: 'Неактивний', value: 'inactive' },
];
 

</script>

<template>
  <Head title="Продукты" />
  <Layout>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div></div>
        <Button label="Додати продукт"  class="mb-3" @click="openModalCreateProduct" />
    </div>
    <!-- Таблица -->
    <DataTable
      paginator 
      showGridlines
      :value="products.data"
      :lazy="true"
      :total-records="products.total"
      :rows="perPage"
      :rows-per-page-options="[10, 20, 50, 100]"
      :first="(currentPage - 1) * perPage"
      :sort-field="sortBy"
      :sort-order="sortDirection === 'asc' ? 1 : -1"
      @page="onPageChange"
      @error="(e) => console.error('Error in DataTable:', e)"
      @sort="onSortChange"
      
    >
      <Column field="id" header="ID" sortable />
      <Column field="name" header="Назва" sortable />
      <Column field="sku" header="SKU" sortable />
      <Column field="stock" header="Наявність" sortable />
      <Column field="price" header="Ціна" sortable />
      <Column field="discounted_price" header="Акційна ціна" sortable />
      
      <Column header="Категорія">
        <template #body="{data}">
         <Tag v-if="data.category?.name" severity="info" :value="data.category?.name"></Tag>
        </template>
      </Column>

      <Column header="Статус" >
        <template #body="{data}">
            <div class="flex gap-3 items-center uppercase" v-if="data.status === 'active'">
              <svg style="width:8px; height:8px;" width="12" height="12" viewBox="0 0 24 24" fill="#00FF00" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="12" />
              </svg>

              {{ getStatusName(data.status) }} 
            </div>
            <div class="flex gap-3 items-center uppercase" v-if="data.status === 'inactive'">
              <svg style="width:8px; height:8px;" width="12" height="12" viewBox="0 0 24 24" fill="#FF0000" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="12" />
              </svg>
              
              {{ getStatusName(data.status) }} 
            </div>
        </template>
     </Column>

      <Column class="">
        <template #body="{ data }">
          <div class="flex gap-3">
          <Link :href="'/products/'+data.id" target="_blank">
            <Button  severity="secondary" ><ExternalLink class="w-4 h-4"  /></Button>
          </Link>
          <Button severity="secondary" @click="confirmDelete($event, data)" ><Trash class="w-4 h-4" /></Button>
        </div>
        </template>
    </Column>
    </DataTable>


    <!-- Модальное окно для добавления продукта -->
    <Dialog header="Додати продукт" v-model:visible="modalVisible" :modal="true" :style="{ width: '50vw' }">
            <form @submit.prevent="saveProduct" class="grid gap-4 grid-cols-2">

                <IftaLabel>
                 <InputText v-model="form.name" id="prdname" class="w-full" />
                 <label for="prdname">Назва</label>
                </IftaLabel>

                <IftaLabel>
                    <InputText v-model="form.sku" id="prdsku" class="w-full"/>
                    <label for="prdsku">SKU</label>
                </IftaLabel>

                <IftaLabel>
                  <InputNumber v-model="form.price" id="prdprice" locale="ua-UA":minFractionDigits="2" class="w-full" />
                  <label for="prdprice">Ціна</label>
                </IftaLabel>
                
                <IftaLabel>
                  <InputNumber v-model="form.discounted_price" locale="ua-UA":minFractionDigits="2" id="prddp" class="w-full"/>
                  <label for="prddp">Акційна ціна</label>
                </IftaLabel>

                <IftaLabel>
                  <InputNumber v-model="form.cost" id="prdcost" locale="ua-UA":minFractionDigits="2" class="w-full" />
                  <label for="prdcost">Собівартість</label>
                </IftaLabel>

                <IftaLabel>
                  <InputNumber v-model="form.stock" id="prdstock" class="w-full" />
                  <label for="prdstock">Наявність</label>
                </IftaLabel>

                <IftaLabel>
                  <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id" id="prdcat" class="w-full" />
                  <label for="prdcat">Категорія</label>
                </IftaLabel>

                <IftaLabel>
                  <Select v-model="form.status" :options="statuses" optionLabel="name" optionValue="value" id="prdstatus" class="w-full" />
                  <label for="prdstatus">Статус</label>
                </IftaLabel>
                <IftaLabel>
                  <Textarea v-model="form.description" id="prddesc" class="w-full" />
                  <label for="prddesc">Опис</label>
                </IftaLabel>
                <IftaLabel>
                  <Select v-model="form.type" :options="types" optionLabel="name" optionValue="value" id="prdtype" class="w-full" />
                  <label for="prdtype">Тип товару</label>
                </IftaLabel>
                <Button label="Зберегти" type="submit" />
            </form>
        </Dialog>


  </Layout>
</template>

