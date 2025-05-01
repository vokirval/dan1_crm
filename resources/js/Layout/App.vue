<script setup>
import {
    ChartPie,
    CircleUser,
    Home,
    Package,
    ShoppingCart,
    Users,
    Settings,
    PanelRightOpen,
    PanelRightClose,
    ArrowLeft
} from "lucide-vue-next";
import { Link, usePage } from "@inertiajs/vue3";
import { useForm } from "@inertiajs/vue3";
import { ref, computed  } from "vue";

const form = useForm({});
const isOpen = ref(false);

function dropDownMenu() {
    isOpen.value = !isOpen.value;
}

const logout = () => {
    form.post("/logout");
};

const collapsed = ref(false);
const expandedItem = ref(null);

const menuItems = ref([
    {
        label: "Головна",
        icon: "Home",
        route: "/dashboard",
    },
    {
        label: "Замовлення",
        icon: "ShoppingCart",
        children: [
            { label: "Всі замовлення", route: "/orders" },
            { label: "Статуси замовлень", route: "/order-statuses" },
            { label: "Методи оплати", route: "/payment-methods" },
            { label: "Методи доставки", route: "/delivery-methods" },
            { label: "Логи", route: "/logs" },

        ],
    },
    {
        label: "Товари",
        icon: "Package",
        children: [
            { label: "Усі товари", route: "/products" },
            { label: "Категорії", route: "/categories" },
        ],
    },
    {
        label: "Статистика",
        icon: "ChartPie",
        children: [
            { label: "Вся статистика", route: "/statistics" },
        ],
    },
    {
        label: "Налаштування",
        icon: "Settings",
        children: [
            { label: "Користувачі", route: "/users" },
            { label: "Шаблони Email", route: "/email-templates" },
            { label: "Групи користувачів", route: "/groups" },
            { label: "Ролі", route: "/roles" },
            { label: "Дозволи", route: "/permissions" },
        ],
    },
]);

const toggleSubMenu = (item) => {
    expandedItem.value = expandedItem.value === item ? null : item;
};

// Функция для динамической загрузки иконок
const icons = { Home, ShoppingCart, Users, Package, ChartPie, Settings };
const getIcon = (iconName) => icons[iconName] || null;




const page = usePage();

const isOrderDetailsPage = computed(() => {
    const match = page.url.match(/^\/orders\/[^/]+/);
    return !!match;
});

const isChildPage = computed(() => {
  const pathSegments = page.url.split("/").filter(Boolean);
  return pathSegments.length > 1; // Если сегментов больше 1, это дочерняя страница
});

// Возвращаем пользователя назад
const goBack = () => {
    window.history.back(); // Возвращаемся на предыдущую страницу, если есть referrer
};


</script>

<template>
    <div class="flex min-h-screen w-full" :class="{ collapsed: !collapsed }">
        <div class="menu-container" v-if="collapsed" :class="{ collapsed: !collapsed }" >
        <Menu :model="menuItems" class="menu-content" >
            <template #start>
                <div class="logo-container">
                    <span v-if="collapsed" class="text-xl font-semibold">DAGGI SHOP</span>
                </div>
            </template>
            <template #item="{ item, props }">
                <div>
                    <a v-bind="props" class="menu-item flex items-center" @click="toggleSubMenu(item)">
                        <component :is="getIcon(item.icon)" class="menu-icon w-5 h-5 mr-2" />
                        <span v-if="collapsed">{{ item.label }}</span>
                    </a>
                    <ul v-if="item.children && expandedItem === item" class="submenu">
                        
                        <li v-for="subItem in item.children" :key="subItem.label" class="submenu-item">
                            <Link :href="subItem.route" class="flex items-center">
                                <span>{{ subItem.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </template>

        </Menu>
        
    </div> 
        <div class="flex flex-col w-full">
            <header
                class="flex h-14 items-center gap-2 border-b bg-muted/40 px-4 lg:h-[40px] lg:px-2 divide-x"
            >
                <div>
                    <Button severity="contrast" variant="text" v-if="!collapsed" @click="collapsed = true;"><PanelRightClose class="w-6 h-6"/></Button>
                   <Button severity="contrast" variant="text" v-if="collapsed" @click="collapsed = false;"><PanelRightOpen class="w-6 h-6"/></Button>
                </div>
               
                <div class="w-full flex-1 pl-2">
                    
                        <Button severity="contrast" variant="text" @click="goBack">
                            <ArrowLeft class="w-6 h-6" /> Повернутись
                        </Button>
                   
                </div>
                <!-- Триггер кнопки -->

                <div class="relative" @click="dropDownMenu">
                    <!-- Триггер кнопки -->
                    <button
                        class="rounded-full p-2 bg-secondary hover:bg-secondary-dark"
                        aria-label="Toggle user menu"
                    >
                        <CircleUser />
                        <span class="sr-only">Toggle user menu</span>
                    </button>

                    <!-- Контент выпадающего меню -->
                    <div
                        v-if="isOpen"
                        class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-50"
                    >
                        <div class="px-4 py-2 text-sm font-bold text-gray-700">
                            Мій аккаунт
                        </div>
                        <div class="border-t border-gray-200"></div>
                        <button
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                            Налаштування
                        </button>
                        <button
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                            Підтримка
                        </button>
                        <div class="border-t border-gray-200"></div>
                        <button
                            @click="logout"
                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
                        >
                            Вийти
                        </button>
                    </div>
                </div>
            </header>
            <main class="flex flex-1 flex-col p-4 ">
                
              
                <slot />
                <Toast />
                <ConfirmPopup></ConfirmPopup>
            </main>
        </div>
    </div>
</template>


<style scoped>

</style>