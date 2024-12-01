import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { setupPrimeVue } from './components/primevue'; // Импорт PrimeVue настройки
import '../css/app.css';

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) }); // Создаем экземпляр приложения

    app.use(plugin); // Подключаем Inertia плагин
    setupPrimeVue(app); // Настраиваем PrimeVue

    app.mount(el); // Монтируем приложение
  },
});
