import Ably from 'ably';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const ably = new Ably.Realtime({ 
    key: 'Hozm9A.XUnHEQ:1xrJkVWmc6HN1E5z2YRWmVV1CmTgPXmFbMGS99VhR6E', // Здесь только ключ ID, не полный ключ!
    clientId: 'vue-client',
    autoConnect: true,
    echoMessages: false
});

const channel = ably.channels.get('orders');

// Глобальный массив заблокированных заказов
const lockedOrders = ref(new Set());

const fetchLockedOrders = async () => {
    try {
        lockedOrders.value = new Set();
        const response = await axios.get('/orders/locked');
        response.data.lockedOrders.forEach(orderId => addLockedOrder(orderId));
        console.log(lockedOrders.value);
    } catch (error) {
        console.error('Ошибка загрузки заблокированных заказов:', error);
    }
};

fetchLockedOrders();



const addLockedOrder = (orderId) => {
    lockedOrders.value = new Set([...lockedOrders.value, orderId]); // Создаем новый Set
};


// Подписка на события блокировки заказа
channel.subscribe('order.locked', (msg) => {
    console.log('🔥 Заказ заблокирован:', msg.data);
    fetchLockedOrders();
});

// Подписка на события разблокировки заказа
channel.subscribe('order.unlocked', (msg) => {
    console.log('🔓 Заказ разблокирован:', msg.data);
    fetchLockedOrders();
});




// Функция разблокировки
const unlockOrder = (orderId) => {
    if (!orderId) return;
    console.log(`🔓 Разблокируем заказ ${orderId}`);
    axios.post(`/orders/${orderId}/unlock`).catch(() => {});
};

// Слушаем смену страницы через Inertia.js
router.on('navigate', () => {
    unlockOrder(window.currentLockedOrder);
    window.currentLockedOrder = null;
});

// Слушаем закрытие вкладки
window.addEventListener('beforeunload', () => {
    unlockOrder(window.currentLockedOrder);
});



// Экспортируем всё
export { ably, channel, lockedOrders };
