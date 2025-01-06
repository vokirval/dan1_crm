<script setup>
import { ref } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";

const { template } = usePage().props;
const form = ref({
  name: template.name,
  subject: template.subject,
  body: template.body,
});

const submitForm = () => {
  router.put(`/email-templates/${template.id}`, form.value, {
    onSuccess: () => {
      alert("Шаблон успешно обновлен!");
      router.visit("/email-templates");
    },
    onError: (errors) => {
      alert(`Ошибка: ${Object.values(errors).join(", ")}`);
    },
  });
};
</script>

<template>
  <Layout>
    <div>
    <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label class="block mb-2">Назва шаблона:</label>
          <input v-model="form.name" class="border p-2 w-full" />
        </div>
        <div class="mb-4">
          <label class="block mb-2">Тема:</label>
          <input v-model="form.subject" class="border p-2 w-full" />
        </div>
        <div class="mb-4">
          <label class="block mb-2">Лист:</label>
          <textarea v-model="form.body" class="border p-2 w-full h-40"></textarea>
        </div>
        <button class="bg-blue-500 text-white px-4 py-2 rounded">Зберегти</button>
      </form>

      <div class="container mx-auto p-4">
    <h2 class="text-xl font-bold mb-4">Список доступных шорткодов</h2>
    <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
        <thead>
            <tr class="bg-gray-100">
                <th class="py-2 px-4 border-b">Шорткод</th>
                <th class="py-2 px-4 border-b">Описание</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="py-2 px-4 border-b">{order_id}</td>
                <td class="py-2 px-4 border-b">ID заказа.</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{customer_name}</td>
                <td class="py-2 px-4 border-b">Полное имя клиента (поле <code>delivery_fullname</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{customer_email}</td>
                <td class="py-2 px-4 border-b">Электронная почта клиента (поле <code>email</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{customer_phone}</td>
                <td class="py-2 px-4 border-b">Телефон клиента (поле <code>phone</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{order_total}</td>
                <td class="py-2 px-4 border-b">Общая сумма заказа (сумма всех товаров с учетом количества).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{order_date}</td>
                <td class="py-2 px-4 border-b">Дата создания заказа в формате <code>dd.mm.yyyy</code>.</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_address}</td>
                <td class="py-2 px-4 border-b">Основной адрес доставки клиента (поле <code>delivery_address</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_city}</td>
                <td class="py-2 px-4 border-b">Город доставки клиента (поле <code>delivery_city</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_postcode}</td>
                <td class="py-2 px-4 border-b">Почтовый индекс доставки клиента (поле <code>delivery_postcode</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_state}</td>
                <td class="py-2 px-4 border-b">Штат или регион доставки клиента (поле <code>delivery_state</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_country_code}</td>
                <td class="py-2 px-4 border-b">Код страны доставки (поле <code>delivery_country_code</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{tracking_number}</td>
                <td class="py-2 px-4 border-b">Трекинг-номер заказа (поле <code>tracking_number</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{is_paid}</td>
                <td class="py-2 px-4 border-b">Статус оплаты (<code>Оплачено</code> или <code>Не оплачено</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{paid_amount}</td>
                <td class="py-2 px-4 border-b">Оплаченная сумма (поле <code>paid_amount</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_date}</td>
                <td class="py-2 px-4 border-b">Дата доставки (поле <code>delivery_date</code>) в формате <code>dd.mm.yyyy hh:mm:ss</code>.</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{payment_date}</td>
                <td class="py-2 px-4 border-b">Дата оплаты (поле <code>payment_date</code>) в формате <code>dd.mm.yyyy hh:mm:ss</code>.</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{payment_method}</td>
                <td class="py-2 px-4 border-b">Метод оплаты (название из связанной модели <code>PaymentMethod</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{delivery_method}</td>
                <td class="py-2 px-4 border-b">Метод доставки (название из связанной модели <code>DeliveryMethod</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{responsible_user}</td>
                <td class="py-2 px-4 border-b">Ответственный пользователь (имя из связанной модели <code>User</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{group_name}</td>
                <td class="py-2 px-4 border-b">Название группы, к которой принадлежит заказ.</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{order_status}</td>
                <td class="py-2 px-4 border-b">Статус заказа (название из связанной модели <code>OrderStatus</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{utm_source}</td>
                <td class="py-2 px-4 border-b">UTM-метка источника трафика (поле <code>utm_source</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{utm_medium}</td>
                <td class="py-2 px-4 border-b">UTM-метка типа трафика (поле <code>utm_medium</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{utm_term}</td>
                <td class="py-2 px-4 border-b">UTM-метка термина или ключевого слова (поле <code>utm_term</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{utm_content}</td>
                <td class="py-2 px-4 border-b">UTM-метка контента (поле <code>utm_content</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{utm_campaign}</td>
                <td class="py-2 px-4 border-b">UTM-метка кампании (поле <code>utm_campaign</code>).</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{product_table}</td>
                <td class="py-2 px-4 border-b">HTML-таблица с товарами, включающая название, количество, цену и сумму.</td>
            </tr>
            <tr>
                <td class="py-2 px-4 border-b">{comment}</td>
                <td class="py-2 px-4 border-b">Комментарий клиента к заказу (поле <code>comment</code>).</td>
            </tr>
        </tbody>
    </table>
</div>


  </div>
  </Layout>
  </template>
  

  