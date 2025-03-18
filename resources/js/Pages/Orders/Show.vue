<script setup>
import { ref, computed, watch } from "vue";
import { usePage, Head, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";
import { Button, InputText, Textarea } from "primevue";
import { useToast } from "primevue/usetoast";
import {
    Trash,
    Check,
    Pencil,
    MailPlus,
    Send,
    MapPinned,
    RefreshCcw,
    PackagePlus,
    FileBox,
    FolderSync,
    Truck,
    MessageCirclePlus
} from "lucide-vue-next";
import { useConfirm } from "primevue/useconfirm";
import { lockedOrders } from "../../ably"; // Импортируем список заблокированных заказов

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
const showBodyEmail = ref(false); // Управление видимостью диалога
const bodyEmail = ref(""); // Управление видимостью диалога
const isPaidAmountFocused = ref(false);
const previewHtml = ref(""); // HTML для предпросмотра
const previewDialogVisible = ref(false); // Видимость модального окна предпросмотра
const macros = ref([]);
const inpostModalVisible = ref(false);
const errorMessages = ref([]);
const loadingInpost = ref(false);
let trackingCheckInterval = null;

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
        const response = await axios.get(
            `/email/macros`
        );

        macros.value = Object.entries(response.data).map(([key, description]) => ({
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
            detail: "Оберіть шаблон для предпросмотра.",
            life: 3000,
        });
        return;
    }

    try {
        const response = await axios.post(
            `/orders/${order.value.id}/preview-template`,
            {
                template_id: selectedTemplateId.value,
            }
        );

        if (response.data.success) {
            previewHtml.value = response.data.preview; // HTML для отображения предпросмотра
            previewDialogVisible.value = true; // Открываем модальное окно
        } else {
            throw new Error("Ошибка получения предпросмотра.");
        }
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Ошибка",
            detail: error.response?.data?.message || error.message,
            life: 5000,
        });
    }
};

const sendEmail = async () => {
    if (
        !selectedTemplateId.value &&
        (!customSubject.value || !customBody.value)
    ) {
        toast.add({
            severity: "warn",
            summary: "Ошибка",
            detail: "Оберіть шаблон або заповніть тему та лист для відправки.",
            life: 3000,
        });
        return;
    }

    try {

        const response = await axios.post(
            `/orders/${order.value.id}/send-email`,
            {
                template_id: selectedTemplateId.value,
                custom_subject: customSubject.value,
                custom_body: customBody.value,
            }
        );



        if (response.data.success) {
            toast.add({
                severity: "success",
                summary: "Успешно",
                detail: response.data.message,
                life: 3000,
            });
            emailDialogVisible.value = false; // Закрыть диалог после успешной отправки
        } else {
            throw new Error(response.data.message || "Не удалось отправить письмо");
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
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate()
    )} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(
        date.getSeconds()
    )}`;
};

const form = ref({
    delivery_fullname: order.value.delivery_fullname,
    delivery_address: order.value.delivery_address,
    delivery_address_number: order.value.delivery_address_number,
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
    sent_at: order.value.sent_at,
    payment_date: order.value.payment_date,
    tracking_number: order.value.tracking_number,
    is_paid: order.value.is_paid,
    paid_amount: order.value.paid_amount,
});

const updateOrder = () => {
    const dataToSubmit = {
        ...form.value,
        delivery_date: formatDateForApi(form.value.delivery_date),
        sent_at: formatDateForApi(form.value.sent_at),
        payment_date: formatDateForApi(form.value.payment_date),
    };

    router.put(`/orders/${order.value.id}`, dataToSubmit, {
        onSuccess: (page) => {
            order.value = page.props.order;
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
            detail: "Ви не вибрали товар!",
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
            detail: "Ви не вибрали варіацію!",
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
        const response = await axios.post(
            `/orders/${order.value.id}/items`,
            itemToAdd
        );

        // Обновляем данные заказа
        order.value = response.data.order;

        toast.add({
            severity: "success",
            summary: "Успешно",
            detail: response.data.flash.success,
            life: 3000,
        });

        selectedProduct.value = null;
        selectedVariation.value = null;
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Ошибка",
            detail: error.response?.data?.message || error.message,
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
        const response = await axios.put(
            `/orders/${order.value.id}/items/${itemId}`, // URL
            { [field]: value }, // Тело запроса
        );

        // Обновляем данные заказа
        order.value = response.data.order;

        // Уведомление об успехе
        toast.add({
            severity: "success",
            summary: "Успешно",
            detail: "Продукт успішно оновлено!",
            life: 3000,
        });
    } catch (error) {
        // Уведомление об ошибке
        toast.add({
            severity: "error",
            summary: "Ошибка",
            detail: error.response?.data?.message || error.message, // Обработка ошибки
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

const formatCurrency = (value, locale = "pl-PL", currency = "PLN") => {
    return new Intl.NumberFormat(locale, {
        style: "currency",
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
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    };
    return new Date(date).toLocaleString("ru-RU", options);
};

const changeEmail = () => {
    form.value.email = form.value.phone + "_client@daggi.shop";
};

const discrepanciesList = ref([]); // Хранит список несоответствий

const checkAddress = async () => {
    discrepanciesList.value = [];
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

    const url = `https://api.geoapify.com/v1/geocode/search?street=${encodeURIComponent(
        cleanedAddress
    )}&housenumber=${encodeURIComponent(
        form.value.delivery_address_number
    )}&postcode=${encodeURIComponent(
        form.value.delivery_postcode
    )}&city=${encodeURIComponent(
        form.value.delivery_city
    )}&apiKey=cfb84a334cbb4ddabf3f0dce863d7e2c`;

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

        if (!bestMatch.properties.street) {
            toast.add({
                severity: "warn",
                summary: "Адресу не підтверджено",
                detail: "Не вдалося знайти відповідний запис у базі.",
                life: 9000,
            });
            return;
        }

        // Данные от сервиса
        const apiAddress =
            (bestMatch.properties.street || "") +
            " " +
            (bestMatch.properties.housenumber || "");
        const apiPostcode = bestMatch.properties.postcode || "";
        const apiCity = bestMatch.properties.city || "";

        // Данные, которые ввел пользователь
        const userAddress = cleanedAddress+' '+form.value.delivery_address_number;
        const userPostcode = form.value.delivery_postcode.trim();
        const userCity = form.value.delivery_city.trim();

        // Список расхождений
        if (
            apiAddress &&
            userAddress.toLowerCase() !== apiAddress.toLowerCase()
        ) {
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
            discrepanciesList.value.push({
                label: "Ймовірна адреса",
                userValue: "",
                apiValue: bestMatch.properties.formatted,
            });

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
                life: 40000,
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

const splitFullName = (fullName) => {
    if (!fullName) return { firstName: "", lastName: "" };
    const parts = fullName.trim().split(" ");
    return {
        firstName: parts[0] || "",
        lastName: parts.slice(1).join(" ") || "-",
    };
};



const inpostData = ref({});


const referenceLimit = 100;
const commentLimit = 100;

const referenceText = ref(""); 
const commentText = ref("");
const referenceLength = computed(() => referenceText.value.length);
const commentLength = computed(() => commentText.value.length);


const openInpostModal = () => {
    // Формируем данные для comment и reference

    order.value = { ...order.value, ...form.value };
    const { firstName, lastName } = splitFullName(order.value.delivery_fullname);

    const commentParts = [];
    const referenceParts = [];

    let packageDimensions = null; // Габариты для Inpost
    let packageWeight = null; // Вес для Inpost
    
    order.value.items.forEach((item) => {
        let productName = "";
        let productId = "";
        let variationName = "";
        let variationId = "";

        let categoryName = item.product?.category?.name || item.product_variation?.product?.category?.name || '';

        // Пропускаем товары из категории "Services"
        if (categoryName === "Services") {
            return;
        }

        // Проверяем, является ли товар вариативным
        if (item.product_variation) {
            productName = item.product_variation.product.short_name || item.product_variation.product.name;
            productId = item.product_variation.product.id;
            variationId = item.product_variation.id;
            variationName = item.product_variation.attributes
                .map((attr) => attr.attribute_value)
                .join(",");
        } else if (item.product) {
            productName = item.product.short_name || item.product.name;
            productId = item.product.id;
        } else {
            productName = 'null';
            productId = 'null';

        }

        const quantity = item.quantity;

        // Формируем строки для comment и reference
        const commentString = `${productName}${variationName ? "," + variationName : ""
            },${quantity}`;
        const referenceString = `${productId}${variationId ? "," + variationId : ""
            },${quantity}`;

        commentParts.push(commentString);
        referenceParts.push(referenceString);

        // Устанавливаем габариты и вес только с первого подходящего товара
        if (!packageDimensions) {
            packageDimensions = {
                length: item.product?.length || item.product_variation?.product?.length || 0, // 400 мм по умолчанию
                width: item.product?.width || item.product_variation?.product?.width || 0, // 300 мм по умолчанию
                height: item.product?.height || item.product_variation?.product?.height || 0, // 80 мм по умолчанию
                unit: "mm",
            };

            packageWeight = {
                amount: item.product?.weight || item.product_variation?.product?.weight || 0, // 1 кг по умолчанию
                unit: "kg",
            };
        }


    });


    // Если ни один товар не подходит, устанавливаем стандартные размеры
    if (!packageDimensions) {
        packageDimensions = {
            length: 0,
            width: 0,
            height: 0,
            unit: "mm",
        };
        packageWeight = {
            amount: 0,
            unit: "kg",
        };
    }

    
    // Формируем строки для reference и comments с ограничением
    referenceText.value = (order.value.id + "|" + referenceParts.join(";")).substring(0, referenceLimit);
    commentText.value = (commentParts.join(";")).substring(0, commentLimit);

    inpostData.value = {
        sender: {
            company_name: "Daggi sp. z o.o.",
            first_name: "Danylo",
            last_name: "Dyakiv",
            email: "paczki@daggi.shop",
            phone: "516146453",
            address: {
                street: "Sokołowska",
                building_number: "10",
                city: "Wypędy",
                post_code: "05-090",
                country_code: "PL",
            },
        },
        receiver: {
            first_name: firstName,
            last_name: lastName,
            email: order.value.email || "",
            phone: order.value.phone || "",
            address: {
                street: order.value.delivery_address || "",
                building_number: order.value.delivery_address_number || "",
                city: order.value.delivery_city || "",
                post_code: order.value.delivery_postcode || "",
                country_code: "PL",
            },
        },
        parcels: [
            {
                id: "small_package",
                dimensions: packageDimensions,
                weight: packageWeight,
            },
        ],
        insurance: {
            amount: totalAmount(order.value.items).toFixed(2) || 0,
            currency: "PLN",
        },
        cod: {
            amount: order.value.is_paid
                ? 0
                : totalAmount(order.value.items).toFixed(2) || 0,
            currency: "PLN",
        },
        additional_services: ["email", "sms"],
        service: "inpost_courier_standard",
        reference: referenceText.value, // Используем обрезанный reference
        comments: commentText.value, // Используем обрезанный comment
    };
    inpostModalVisible.value = true;
};

const validatePhone = (phone) => {
    const regex = /^\d{9}$/; // Только 9 цифр
    return regex.test(phone);
};

const sendToInpost = async () => {
    errorMessages.value = []; // Очищаем ошибки перед запросом
    if (!validatePhone(inpostData.value.receiver.phone)) {
        errorMessages.value.push(
            "❌ Невірний номер телефону одержувача, повинно бути 9 цифр."
        );
        inpostModalVisible.value = false;
        return; // ❌ НЕ ОТПРАВЛЯЕМ, если номер неверный
    }
    try {
        const response = await axios.post(
            `/orders/${order.value.id}/create-inpost`,
            inpostData.value
        );

        if (response.data.success) {
            toast.add({
                severity: "success",
                summary: "Успішно",
                detail: "Замовлення успішно створено в InPost",
                life: 3000,
            });

            // Запускаем проверку трек-номера
            checkTrackingNumber();
        }
    } catch (error) {
        if (error.response?.data?.details) {
            errorMessages.value = formatErrors(error.response.data.details);
        } else {
            errorMessages.value.push(
                error.response?.data?.message || "Невідома помилка"
            );
        }
        inpostModalVisible.value = false;
        toast.add({
            severity: "error",
            summary: "Помилка",
            detail: "Помилка при створенні замовлення в InPost. Дивіться деталі.",
            life: 5000,
        });
    }
};

// 🔥 **Функция проверки наличия ТТН в БД**
const checkTrackingNumber = () => {
    console.log("hello");
    loadingInpost.value = true;
    trackingCheckInterval = setInterval(async () => {
        try {
            const response = await axios.get(
                `/api/orders/${order.value.id}/check-tracking`
            );
            if (response.data.tracking_number) {
                clearInterval(trackingCheckInterval);
                loadingInpost.value = false; // Разблокируем сайт
                inpostModalVisible.value = false;
                toast.add({
                    severity: "success",
                    summary: "ТТН отримано",
                    detail: `Трекінг номер: ${response.data.tracking_number}`,
                    life: 5000,
                });
                location.reload();
            }
        } catch (error) {
            console.log("Очікуємо ТТН...");
        }
    }, 6000);
};

const formatErrors = (errors, prefix = "") => {
    let messages = [];

    Object.entries(errors).forEach(([key, value]) => {
        const fieldName = prefix ? `${prefix} → ${key}` : key;

        if (Array.isArray(value) && typeof value[0] === "string") {
            // Простой массив ошибок, например: "phone": ["invalid"]
            messages.push(`${fieldName}: ${value.join(", ")}`);
        } else if (Array.isArray(value)) {
            // Вложенные объекты (например, "receiver": [{ "phone": ["invalid"] }])
            value.forEach((item) => {
                messages = messages.concat(formatErrors(item, fieldName));
            });
        } else if (typeof value === "object") {
            // Ошибка-объект
            messages = messages.concat(formatErrors(value, fieldName));
        }
    });

    return messages;
};

if (order.value.inpost_id && !order.value.tracking_number) {
    checkTrackingNumber();
}

const copyToClipboard = async (caption) => {
    try {
        await navigator.clipboard.writeText(caption);
        toast.add({
            severity: "success",
            summary: "Скопійовано!",
            life: 5000,
        });
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Виникла помилка!",
            life: 5000,
        });
        console.error("Failed to copy to clipboard:", error);
    }
};
</script>

<template>
    <div v-if="loadingInpost" class="overlay">
        <div class="overlay-content">
            <div class="spinner"></div>
            <p>Чекаємо створення ТТН...</p>
        </div>
    </div>

    <Head title="Просмотр заказа" />
    <Layout>
  
        <!-- 🔥 Выводим ошибки ЧИТАБЕЛЬНО 🔥 -->
        <div v-if="errorMessages.length" class="mt-4 mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            <h4 class="font-bold">Помилки:</h4>
            <ul>
                <li v-for="(error, index) in errorMessages" :key="index">
                    ⚠️ {{ error }}
                </li>
            </ul>
        </div>
        <div class="flex justify-between items-center gap-3">
            <div class="w-2/4 flex flex-wrap items-center gap-3 rounded-xl bg-teal-50/50 p-2 border border-gray-300">
                <Button type="button" size="small" variant="outlined" @click="copyToClipboard(order.id)">ID: {{ order.id
                    }}
                </Button>
                <div v-if="order.inpost_id">
                    <Button type="button" size="small" variant="outlined"
                        @click="copyToClipboard(order.inpost_id)">Inpost ID: {{ order.inpost_id }}
                    </Button>
                </div>
                <div v-if="order.tracking_number">
                    <Button type="button" size="small" variant="outlined"
                        @click="copyToClipboard(order.tracking_number)">ТТН: {{ order.tracking_number }}
                    </Button>
                </div>
                <div v-if="order.return_tracking_number">
                    <Button type="button" size="small" variant="outlined"
                        @click="copyToClipboard(order.return_tracking_number)">Зворотна ТТН: {{
                            order.return_tracking_number }}
                    </Button>
                </div>
            </div>
            <div class="w-2/4 flex gap-3 items-center">
                <InputGroup>
                    <InputGroupAddon :style="{
                        backgroundColor: statuses.find(
                            (s) => s.id === form.order_status_id
                        )?.color
                            ? '#' +
                            statuses.find(
                                (s) => s.id === form.order_status_id
                            ).color
                            : '#000',
                    }"></InputGroupAddon>
                    <IftaLabel>
                        <Select v-model="form.order_status_id" optionValue="id" :options="statuses" optionLabel="name"
                            placeholder="Статус Замовлення" class="w-full" />
                        <label for="product_quantity">Статус Замовлення</label>
                    </IftaLabel>
                </InputGroup>
                <div class="w-full">
                    <IftaLabel v-if="order.inpost_id || order.tracking_number">
                        <InputText id="inpost_status" v-model="order.inpost_status" placeholder="Інформація відсутня..."
                            disabled class="w-full" />
                        <label for="inpost_status">Статус Inpost</label>
                    </IftaLabel>
                    <Button v-if="!order.inpost_id && !order.tracking_number" class="w-full" size="large"
                        @click="openInpostModal">
                        <Truck class="w-6 h-6" /> Створити ТТН в Inpost
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="discrepanciesList.length" class="p-3 bg-yellow-100 border border-yellow-400 rounded mt-3">
            <h4 class="font-bold text-yellow-900">Виявлено розбіжності:</h4>
            <ul class="mt-2 text-yellow-900">
                <li v-for="item in discrepanciesList" :key="item.label">
                    <strong>{{ item.label }}:</strong>
                    <span class="text-red-600" v-if="item.userValue">
                        ❌ {{ item.userValue }}
                    </span>
                    <span class="text-green-600" v-if="item.apiValue">
                        → ✅ {{ item.apiValue }}</span>
                </li>
            </ul>
        </div>

        <div class="grid grid-cols-2 gap-4 text-base">
            <div>
                <div class="mt-2">
                    <Fieldset legend="Дані клієнта" :toggleable="true" :collapsed="false">
                        <div class="mb-4 flex gap-3 items-end">
                            <div class="w-full">
                                <label for="fullname">Им`я</label>
                                <InputText id="fullname" v-model="form.delivery_fullname" class="w-full" />
                            </div>
                            <div class="w-full" v-if="duplicateOrders[0]">
                                
                                 <Button label="🔥 Увага! Є дублікати! 🔥" @click="dialogVisible = true" class="w-full" />
                            </div>
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div>
                                <label for="phone">Телефон</label>
                                <InputText id="phone" v-model="form.phone" class="w-full"  :invalid="form.phone.length !== 6" />
                                <Message v-if="form.phone.length !== 6" size="small" severity="error" variant="simple">В номері більше або меньше 6 символів</Message>

                            </div>
                            <div class="flex">
                                <div class="w-full">
                                    <label for="email">Email</label>
                                    <InputText id="email" v-model="form.email" class="w-full" />
                                </div>
                                <Button size="small" @click="changeEmail" v-if="!form.email" class="mt-6 ml-2">
                                    <RefreshCcw class="w-6 h-6" />
                                </Button>
                                <Button size="small" @click="emailDialogVisible = true" class="mt-6 ml-2">
                                    <MailPlus class="w-6 h-6" />
                                </Button>
                            </div>
                        </div>
                        <div class="mb-4 flex gap-3">
                            <div class="w-3/12">
                                <label for="city">Місто</label>
                                <InputText id="city" v-model="form.delivery_city" class="w-full" />
                            </div>

                            <div class="w-2/12">
                                <label for="zipcode">Зіп код</label>
                                <InputText id="zipcode" v-model="form.delivery_postcode" class="w-full" />
                            </div>
                            <div class="w-full">
                                <label for="address">Адреса</label>
                                <InputText id="address" v-model="form.delivery_address" class="w-full" />
                            </div>
                            <div class="w-2/12">
                                <label for="address">Будинок</label>
                                <InputText id="address_number" v-model="form.delivery_address_number" class="w-full" />
                            </div>
                            <div class="w-1/12 text-center">
                                <Button size="small" @click="checkAddress" class="mt-6">
                                    <MapPinned class="w-6 h-6" />
                                </Button>
                            </div>
                        </div>
                        <div class="mb-4" v-if="form.delivery_second_address">
                            <label for="address2">Додаткова адреса</label>
                            <InputText id="address2" v-model="form.delivery_second_address" class="w-full" />
                        </div>
                        <p>IP Юзера: {{ order.ip }}</p>
                    </Fieldset>
                </div>

                <div class="flex gap-3 mt-5">
                    <IftaLabel class="w-full">
                        <Select v-model="form.payment_method_id" optionValue="id" :options="payment_methods"
                            optionLabel="name" placeholder="Метод оплати" class="w-full" />
                        <label for="product_quantity">Метод оплати</label>
                    </IftaLabel>

                    <IftaLabel class="w-full">
                        <Select v-model="form.delivery_method_id" optionValue="id" :options="delivery_methods"
                            optionLabel="name" placeholder="Метод доставки" class="w-full" />
                        <label for="product_quantity">Метод доставки</label>
                    </IftaLabel>
                </div>

                <div class="mb-4 mt-4">
                    <label for="comment">Коментар</label>
                    <Textarea id="comment" v-model="form.comment" class="w-full" />
                </div>

                <div class="mt-2">
                    <Fieldset legend="Додаткові поля" :toggleable="true" :collapsed="true">
                        <div class="w-full">
                            <label for="tracking_number">ТТН</label>
                            <InputText id="tracking_number" v-model="form.tracking_number" class="w-full" />
                        </div>
                        <IftaLabel class="mt-5">
                            <DatePicker id="delivery_date" dateFormat="yy-mm-dd" v-model="form.delivery_date" showTime
                                hourFormat="24" fluid />
                            <label for="delivery_date">Дата отримання</label>
                        </IftaLabel>
                        <IftaLabel class="mt-5">
                            <DatePicker id="sent_at" dateFormat="yy-mm-dd" v-model="form.sent_at" showTime
                                hourFormat="24" fluid />
                            <label for="sent_at">Відправлено</label>
                        </IftaLabel>
                        <div class="mt-5 flex gap-3">
                            <IftaLabel class="w-full">
                                <Select v-model="form.group_id" optionValue="id" :options="groups" optionLabel="name"
                                    placeholder="Група" class="w-full" />
                                <label for="product_quantity">Група</label>
                            </IftaLabel>

                            <IftaLabel class="w-full">
                                <Select v-model="form.responsible_user_id" optionValue="id" :options="users"
                                    optionLabel="name" placeholder="Відповідальний" class="w-full" />
                                <label for="product_quantity">Відповідальний</label>
                            </IftaLabel>
                        </div>
                    </Fieldset>
                </div>

                
            </div>

            <div>
                <div class="mt-2">
                    <Fieldset legend="Налаштування Оплати" :toggleable="true" :collapsed="false">
                        <div class="flex gap-3">
                            <IftaLabel class="w-full">
                                <Select optionLabel="label" optionValue="value" class="w-full" v-model="form.is_paid"
                                    :options="[
                                        { label: 'Ні', value: 0 },
                                        { label: 'Так', value: 1 },
                                    ]" />
                                <label for="is_paid">Оплачено</label>
                            </IftaLabel>

                            <IftaLabel class="w-full">
                                <DatePicker id="payment_date" dateFormat="yy-mm-dd"  v-model="form.payment_date" showTime
                                    hourFormat="24" fluid />
                                <label for="payment_date">Дата онлайн оплати</label>
                            </IftaLabel>

                            <div class="w-full relative">
                                <FloatLabel variant="in">
                                    <InputText id="paid_amount" v-model="form.paid_amount" class="w-full"
                                        @focus="isPaidAmountFocused = true" @blur="isPaidAmountFocused = false" />
                                    <label for="in_label">Сума оплати</label>
                                </FloatLabel>

                                <!-- Подсказка с суммой заказа -->
                                <div class="mt-3" v-if="isPaidAmountFocused">
                                    <span class="bg-green-500 text-white p-2 rounded cursor-pointer shadow"
                                        @mousedown.stop.prevent="
                                            setTotalAmountToPaidInput
                                        ">
                                        {{
                                            formatCurrency(
                                                totalAmount(order.items)
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Fieldset>
                </div>

                <div class="mt-2">
                    <Fieldset legend="Товари в замовленні" :toggleable="true" :collapsed="false">
                        <div class="flex justify-between gap-3 mb-5">
                            <div class="grid grid-cols-2 gap-3 w-2/3">
                                <IftaLabel>
                                    <Select v-model="selectedProduct" :options="products" size="small"
                                        optionLabel="name" placeholder="Оберіть товар" class="w-full" />
                                    <label>Товар</label>
                                </IftaLabel>

                                <IftaLabel v-if="productVariations.length">
                                    <Select v-model="selectedVariation" :options="productVariations" size="small"
                                        optionLabel="label" optionValue="value" placeholder="Оберіть варіацію"
                                        class="w-full" />
                                    <label>Варіація</label>
                                </IftaLabel>
                            </div>
                            <Button class="mb-4 w-1/3" @click="addProductToOrder">
                                <PackagePlus class="w-6 h-6" /> Додати
                                товар
                            </Button>
                        </div>

                        <table
                            class="table-auto w-full border-collapse border border-gray-300 rounded-xl bg-teal-50/50 p-2">
                            <thead>
                                <tr>
                                    <th class="border border-gray-300 p-2">
                                        Назва
                                    </th>
                                    <th class="border border-gray-300 p-2">
                                        Атрибути
                                    </th>
                                    <th class="border border-gray-300 p-2">
                                        Кількість
                                    </th>
                                    <th class="border border-gray-300 p-2">
                                        Ціна
                                    </th>
                                    <th class="border border-gray-300 p-2">
                                        Сума
                                    </th>
                                    <th class="border border-gray-300 p-2">
                                        Действия
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="border border-gray-300 p-2">
                                        <span v-if="item.product_id">{{
                                            item.product.name
                                        }}</span>
                                        <span v-else-if="
                                            item.product_variation_id
                                        ">
                                            {{
                                                item.product_variation.product
                                                    .name
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
                                                    <InputText v-model.number="item.quantity
                                                        " class="w-full" />
                                                    <Button icon="pi pi-times" text severity="danger" @click="
                                                        updateOrderItem(
                                                            item.id,
                                                            'quantity',
                                                            item.quantity
                                                        );
                                                    closeCallback();
                                                    ">
                                                        <Check />
                                                    </Button>
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
                                                    <InputText v-model.number="item.price
                                                        " class="w-full" />
                                                    <Button text severity="danger" @click="
                                                        updateOrderItem(
                                                            item.id,
                                                            'price',
                                                            item.price
                                                        );
                                                    closeCallback();
                                                    ">
                                                        <Check />
                                                    </Button>
                                                </span>
                                            </template>
                                        </Inplace>
                                    </td>
                                    <td class="border border-gray-300 p-2">
                                        {{
                                            formatCurrency(
                                                item.quantity * item.price
                                            )
                                        }}
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
                                        {{
                                            formatCurrency(
                                                totalAmount(order.items)
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </Fieldset>
                </div>
                <Button size="large" @click="updateOrder" class="mt-4 w-full">
                    <FolderSync class="w-6 h-6" /> Зберегти замовлення
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 justify-items-center items-center my-4">
            <div class="w-full">
                <Fieldset legend="Email історія" :toggleable="true" :collapsed="false">
                    <table class="table-auto w-full border-collapse border border-gray-300 text-sm my-2">
                        <thead>
                            <tr>
                                <th class="border border-gray-300 p-2">Дата відправки</th>
                                <th class="border border-gray-300 p-2">Статус</th>
                                <th class="border border-gray-300 p-2">Email</th>
                                <th class="border border-gray-300 p-2">Тема</th>
                                <th class="border border-gray-300 p-2">Помилка</th>
                                <th class="border border-gray-300 p-2">Лист</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="email in order.email_history" :key="email.id">
                                <td class="border border-gray-300 p-2">
                                    {{ email.sent_at || "Не відправлено" }}
                                </td>
                                <td class="border border-gray-300 p-2">
                                    <span v-if="email.status === 'success'" class="text-green-600">Успішно</span>
                                    <span v-else class="text-red-600">Помилка</span>
                                </td>
                                <td class="border border-gray-300 p-2">
                                    {{ email.to_email }}
                                </td>
                                <td class="border border-gray-300 p-2">
                                    {{ email.subject }}
                                </td>
                                <td class="border border-gray-300 p-2">
                                    {{ email.error_message || "-" }}
                                </td>
                                <td class="border border-gray-300 p-2">
                                    <Button label="Показати лист" @click="
                                        bodyEmail = email.body;
                                    showBodyEmail = true;
                                    " />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Fieldset>
            </div>

            <div class="w-full">
                <Fieldset legend="Історія замовлення" :toggleable="true" :collapsed="false">
                    <Timeline :value="order.fullfull_history">
                        <template #opposite="slotProps">
                            <small class="text-surface-500 dark:text-surface-400">{{ formatDateTime(slotProps.item.created_at) }}</small>
                        </template>
                        <template #content="slotProps">
                            {{slotProps.item.comment}}
                        </template>
                    </Timeline>
                </Fieldset>
            </div>
        </div>


        

        <Dialog v-model:visible="dialogVisible" header="Дублікати замовлення" :style="{ width: '75vw' }" maximizable
            modal :contentStyle="{ height: '100vh' }">
            <div class="overflow-auto">
                <table class="min-w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 p-2">Статус</th>
                            <th class="border border-gray-300 p-2">
                                ID замовлення
                            </th>
                            <th class="border border-gray-300 p-2">Контакт</th>
                            <th class="border border-gray-300 p-2">Телефон</th>
                            <th class="border border-gray-300 p-2">Email</th>
                            <th class="border border-gray-300 p-2">IP</th>
                            <th class="border border-gray-300 p-2">Товари</th>
                            <th class="border border-gray-300 p-2">Коментар</th>
                            <th class="border border-gray-300 p-2">ЗІП-код</th>
                            <th class="border border-gray-300 p-2">
                                Метод оплати
                            </th>
                            <th class="border border-gray-300 p-2">Дата замовлення</th>
                            <th class="border border-gray-300 p-2">Дія</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="duplicate in duplicateOrders" :key="duplicate.id" class="even:bg-gray-50">
                            <td class="border border-gray-300 p-2">
                                <span v-if="duplicate.status" class="rounded p-1 text-white text-xs" :style="{
                                    backgroundColor: `#${duplicate.status.color}`,
                                }">
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
                            <td class="border border-gray-300 p-2" :class="{
                                'text-red-700 font-bold':
                                    duplicate.phone === order.phone,
                            }">
                                {{ duplicate.phone }}
                            </td>
                            <td class="border border-gray-300 p-2" :class="{
                                'text-red-700 font-bold':
                                    duplicate.email === order.email,
                            }">
                                {{ duplicate.email }}
                            </td>
                            <td class="border border-gray-300 p-2" :class="{
                                'text-red-700 font-bold':
                                    duplicate.ip === order.ip,
                            }">
                                {{ duplicate.ip }}
                            </td>
                            <td class="border border-gray-300 p-2">
                                <div v-for="item in duplicate.items" :key="item.id">
                                    <div class="text-xs">
                                        <span v-if="item.product_id">{{
                                            item.product.name
                                        }}</span>
                                        <span v-else-if="
                                            item.product_variation_id
                                        ">
                                            {{
                                                item.product_variation.product
                                                    .name
                                            }}</span>
                                        <span v-else>Товар не знайдено...</span>

                                        <span v-if="item.product_variation_id">
                                            |
                                            {{
                                                formatVariationName(
                                                    item.product_variation
                                                )
                                            }}
                                        </span>

                                        | x{{ item.quantity }}

                                        | {{ item.price }}
                                    </div>
                                </div>
                            </td>
                            <td class="border border-gray-300 p-2">
                                {{ duplicate.comment || "-" }}
                            </td>
                            <td class="border border-gray-300 p-2">
                                {{ duplicate.delivery_postcode || "-" }}
                            </td>
                            <td class="border border-gray-300 p-2">
                                {{ duplicate.payment_method?.name }}
                            </td>
                            <td class="border border-gray-300 p-2">
                                {{ formatDateTime(duplicate.created_at) }}
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

        <Dialog v-model:visible="visible" maximizable modal header="Деталі замовлення" :style="{ width: '100rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <div v-if="selectedOrder">
                <!-- Основная информация -->
                <div class="bg-[#eee] rounded py-5 px-2 text-normal border-b">
                    <div class="grid grid-cols-3 gap-4 justify-items-center items-center">
                        <p>
                            <strong class="mr-2">Статус замовлення:</strong>
                            <span v-if="selectedOrder.status" class="rounded p-1 text-white text-xs" :style="{
                                backgroundColor: `#${selectedOrder.status.color}`,
                            }">
                                {{ selectedOrder.status?.name }}
                            </span>
                            <span v-else class="rounded p-1 text-white bg-black text-xs">
                                Без статусу
                            </span>
                        </p>
                        <p>
                            <strong>Відповідальний:</strong>
                            {{ selectedOrder.responsible_user?.name }}
                        </p>
                        <Button size="small" @click="viewOrder(selectedOrder.id)">
                            <Pencil class="w-5 h-5" /> Редагувати
                            замовлення
                        </Button>
                    </div>
                </div>

                <!-- Доставка -->
                <div class="text-base p-5 bg-[#f1f5f9]">
                    <div class="grid grid-cols-6 gap-4">
                        <p>
                            <strong>Ім'я:</strong>
                            {{ selectedOrder.delivery_fullname }}
                        </p>
                        <p><strong>Phone:</strong> {{ selectedOrder.phone }}</p>
                        <p>
                            <strong>Місто:</strong>
                            {{ selectedOrder.delivery_city }}
                        </p>
                        <p>
                            <strong>ЗІП код:</strong>
                            {{ selectedOrder.delivery_postcode }}
                        </p>
                        <p>
                            <strong>Адреса:</strong>
                            {{ selectedOrder.delivery_address }}
                        </p>
                        <p>
                            <strong>Доп. адреса:</strong>
                            {{ selectedOrder.delivery_second_address }}
                        </p>

                        <p>
                            <strong>Метод доставки:</strong>
                            {{ selectedOrder.delivery_method?.name }}
                        </p>
                        <p>
                            <strong>Метод оплати:</strong>
                            {{ selectedOrder.payment_method?.name }}
                        </p>
                        <p><strong>Email:</strong> {{ selectedOrder.email }}</p>
                        <p>
                            <strong>Комент:</strong>
                            {{ selectedOrder.comment || "N/A" }}
                        </p>
                        <p>
                            <strong>Трекінг Номер:</strong>
                            {{ selectedOrder.tracking_number || "N/A" }}
                        </p>
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
                                {{
                                    formatCurrency(
                                        totalAmount(selectedOrder.items)
                                    )
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- UTM-метки -->
                <div class="text-base p-5 bg-[#f1f5f9]">
                    <div class="grid grid-cols-5 gap-4 mt-2">
                        <p>
                            <strong>UTM Source:</strong>
                            {{ selectedOrder.utm_source || "N/A" }}
                        </p>
                        <p>
                            <strong>UTM Medium:</strong>
                            {{ selectedOrder.utm_medium || "N/A" }}
                        </p>
                        <p>
                            <strong>UTM Term:</strong>
                            {{ selectedOrder.utm_term || "N/A" }}
                        </p>
                        <p>
                            <strong>UTM Content:</strong>
                            {{ selectedOrder.utm_content || "N/A" }}
                        </p>
                        <p>
                            <strong>UTM Campaign:</strong>
                            {{ selectedOrder.utm_campaign || "N/A" }}
                        </p>
                        <p>
                            <strong>IP Address:</strong> {{ selectedOrder.ip }}
                        </p>
                        <p>
                            <strong>Website Reffer:</strong>
                            {{ selectedOrder.website_referrer }}
                        </p>
                    </div>
                </div>

                <!-- Основная информация -->
                <div class="border-b bg-[#eee] rounded-sm p-2 text-normal">
                    <div class="grid grid-cols-2 gap-4 mt-2 justify-items-center">
                        <p>
                            <strong>Замовлення створено:</strong>
                            {{ formatDateTime(selectedOrder.created_at) }}
                        </p>
                        <p>
                            <strong>Замовлення оновлено:</strong>
                            {{ formatDateTime(selectedOrder.updated_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="emailDialogVisible" header="Відправка Email" modal :style="{ width: '50vw' }"
            :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
            <div class="grid grid-cols-1 gap-4">
                <ToggleButton v-model="customSendEmailTemplate" onLabel="Вибрати зі списку шаблонів"
                    offLabel="Створити лист самостійно" />

                <div v-if="customSendEmailTemplate == false">
                    <label for="template">Шаблон листа</label>
                    <Select id="template" v-model="selectedTemplateId" @change="previewTemplate" :options="emailTemplates.map((template) => ({
                        label: template.name,
                        value: template.id,
                    }))
                        " optionValue="value" optionLabel="label" placeholder="Оберіть шаблон" class="w-full" />

                    <div v-if="selectedTemplateId">
                        <h3 class="mt-5">Превью шаблона:</h3>
                        <div class="p-3 border border-[#000]" v-html="previewHtml"></div>
                    </div>
                </div>
                <div v-else>
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-3">Доступні макроси</h3>
                        <ul class="space-y-1 flex gap-3 w-full flex-wrap">
                            <li v-for="macro in macros" :key="macro.key" @click="insertMacro(macro.key)"
                                class="bg-gray-100 p-1 rounded shadow cursor-pointer hover:bg-gray-200">
                                <span class="text-xs text-gray-500" v-tooltip.top="macro.description">{{ macro.key
                                }}</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <label for="custom-subject">Тема</label>
                        <InputText id="custom-subject" v-model="customSubject" class="w-full" />
                    </div>
                    <div class="mt-3">
                        <label for="custom-body">Лист</label>
                        <Textarea id="custom-body" ref="customBodyTextarea" v-model="customBody" rows="5"
                            class="w-full" />
                    </div>
                </div>
            </div>
            <template #footer>
                <Button class="p-button-success" @click="sendEmail">
                    <Send /> Відправити
                </Button>
            </template>
        </Dialog>

        <Dialog v-model:visible="showBodyEmail" maximizable modal header="Тіло листа" :style="{ width: '50rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <p class="m-0" v-html="bodyEmail"></p>
        </Dialog>

        <Dialog v-model:visible="inpostModalVisible" header="Створення замовлення в InPost" :style="{ width: '90vw' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }" maximizable modal>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div>
                        <Fieldset legend="Налаштування Одержувача" :toggleable="true" :collapsed="false">
                            <div class="flex gap-2">
                                <div class="mb-4 w-full">
                                    <label>Ім'я</label>
                                    <InputText v-model="inpostData.receiver.first_name" class="w-full" />
                                </div>
                                <div class="mb-4 w-full">
                                    <label>Прізвище</label>
                                    <InputText v-model="inpostData.receiver.last_name" class="w-full" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="mb-4 w-full">
                                    <label>Email</label>
                                    <InputText v-model="inpostData.receiver.email" class="w-full" />
                                </div>
                                <div class="mb-4 w-full">
                                    <label>Телефон</label>
                                    <InputText v-model="inpostData.receiver.phone" class="w-full" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="w-3/12">
                                    <label>Місто</label>
                                    <InputText v-model="inpostData.receiver.address.city" class="w-full" />
                                </div>

                                <div class="w-2/12">
                                    <label>Зіп код</label>
                                    <InputText v-model="inpostData.receiver.address.post_code
                                        " class="w-full" />
                                </div>
                                <div class="w-full">
                                    <label>Адреса</label>
                                    <InputText v-model="inpostData.receiver.address.street" class="w-full" />
                                </div>
                                <div class="w-2/12">
                                    <label>Будинок</label>
                                    <InputText v-model="inpostData.receiver.address
                                        .building_number
                                        " class="w-full" />
                                </div>

                            </div>
                        </Fieldset>
                    </div>
                    <div class="mt-2">
                        <Fieldset legend="Налаштування Відправника" :toggleable="true" :collapsed="true">
                            <div class="flex gap-2">
                                <div class="mb-4 w-full">
                                    <label>Ім'я</label>
                                    <InputText v-model="inpostData.sender.first_name" class="w-full" />
                                </div>
                                <div class="mb-4 w-full">
                                    <label>Прізвище</label>
                                    <InputText v-model="inpostData.sender.last_name" class="w-full" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="mb-4 w-full">
                                    <label>Email</label>
                                    <InputText v-model="inpostData.sender.email" class="w-full" />
                                </div>
                                <div class="mb-4 w-full">
                                    <label>Телефон</label>
                                    <InputText v-model="inpostData.sender.phone" class="w-full" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="w-3/12">
                                    <label>Місто</label>
                                    <InputText v-model="inpostData.sender.address.city" class="w-full" />
                                </div>

                                <div class="w-2/12">
                                    <label>Зіп код</label>
                                    <InputText v-model="inpostData.sender.address.post_code
                                        " class="w-full" />
                                </div>
                                <div class="w-full">
                                    <label>Адреса</label>
                                    <InputText v-model="inpostData.sender.address.street" class="w-full" />
                                </div>
                                <div class="w-2/12">
                                    <label>Будинок</label>
                                    <InputText v-model="inpostData.sender.address
                                        .building_number
                                        " class="w-full" />
                                </div>

                            </div>
                        </Fieldset>
                    </div>
                    <div class="mt-2">
                        <Fieldset legend="Деталі посилки" :toggleable="true" :collapsed="false">
                            <div class="flex gap-2">
                                <div class="mb-4">
                                    <label>Вага (кг)</label>
                                    <InputText v-model="inpostData.parcels[0].weight.amount" class="w-full" />
                                </div>
                                <div class="mb-4">
                                    <label>Довжина (мм)</label>
                                    <InputText v-model="inpostData.parcels[0].dimensions.length" class="w-full" />
                                </div>
                                <div class="mb-4">
                                    <label>Ширина (мм)</label>
                                    <InputText v-model="inpostData.parcels[0].dimensions.width" class="w-full" />
                                </div>
                                <div class="mb-4">
                                    <label>Висота (мм)</label>
                                    <InputText v-model="inpostData.parcels[0].dimensions.height" class="w-full" />
                                </div>
                            </div>
                        </Fieldset>
                    </div>
                </div>

                <!-- Одержувач -->

                <div>
                    <!-- Фінансові умови -->
                    <div>
                        <Fieldset legend="Налаштування Ціни та Страховки" :toggleable="true" :collapsed="false">
                            <div class="flex gap-2">
                                <div class="mb-4 w-full">
                                    <label>Сума післяплати (PLN)</label>
                                    <InputText v-model="inpostData.cod.amount" class="w-full" />
                                </div>
                                <div class="mb-4 w-full">
                                    <label>Сума страховки (PLN)</label>
                                    <InputText v-model="inpostData.insurance.amount" class="w-full" />
                                </div>

                            </div>
                        </Fieldset>
                    </div>

                    <!-- Додаткові дані -->
                    <div class="mt-2">
                        <Fieldset legend="Додаткові поля" :toggleable="true" :collapsed="false">
                            <div class="mb-4 w-full">
                                <label :class="{'text-red-500': referenceLength >= 100}">Референс ({{ referenceLength }}/100)</label>
                                <InputText v-model="referenceText" maxlength="100"  class="w-full" />
                            </div>
                            <div class="mb-4 w-full flex gap-3">
                                <Button 
                                    v-if="!commentText.includes('|')" 
                                    size="small" 
                                    @click="commentText = order.comment + '|' + commentText" 
                                    class="mt-6">
                                    <MessageCirclePlus class="w-6 h-6" />
                                </Button>
                                <div class="w-full">
                                    <label :class="{'text-red-500': commentLength >= 100}">Коментар ({{ commentLength }}/100)</label>
                                    <InputText  v-model="commentText" maxlength="100"  class="w-full " />
                                </div>
                                
                            </div>
                        </Fieldset>
                    </div>


                    <Button class="mt-4 w-full" size="large" @click="sendToInpost">
                        <Truck class="w-6 h-6" /> Створити ТТН в Inpost
                    </Button>

                </div>




            </div>
        </Dialog>
    </Layout>
</template>
<style scoped>
/* Оверлей блокировки экрана */
.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

/* Контент оверлея */
.overlay-content {
    text-align: center;
    color: white;
    font-size: 1.5rem;
}

/* Анимация загрузки */
.spinner {
    border: 5px solid rgba(255, 255, 255, 0.3);
    border-top: 5px solid white;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}
</style>
