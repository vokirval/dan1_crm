<script setup>
import Layout from '../../Layout/App.vue';
import { ref, reactive, watch } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { Trash } from 'lucide-vue-next';
import Select from 'primevue/dropdown';
import MultiSelect from 'primevue/multiselect';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const toast = useToast();

const props = defineProps({
    orderStatus: Object,
    rule: Object,
    availableFields: Array,
    availableOperators: Object,
    availableActions: Array,
});

// Инициализируем форму с данными правила
const form = useForm({
    name: props.rule.name,
    is_active: !!props.rule.is_active,
    conditions: props.rule.conditions.map(condition => ({
        field: condition.field,
        operator: condition.operator,
        value: ['входить в', 'не входить в'].includes(condition.operator)
            ? JSON.parse(condition.value)
            : condition.value,
        _prevOperator: condition.operator, // Для отслеживания изменений оператора
    })),
    actions: props.rule.actions.map(action => ({
        type: action.type,
        parameters: action.parameters || { message: '' },
    })),
});

const addCondition = () => {
    form.conditions.push({ field: null, operator: null, value: null });
};

const removeCondition = (index) => {
    form.conditions.splice(index, 1);
};

const addAction = () => {
    form.actions.push({ type: 'log', parameters: { message: '' } });
};

const removeAction = (index) => {
    form.actions.splice(index, 1);
};

const getOperators = (type) => props.availableOperators[type] || [];
const getField = (key) => props.availableFields.find((f) => f.value === key);
const getType = (field) => getField(field)?.type;
const getOptions = (field) => getField(field)?.options || [];

const submit = () => {
    const data = {
        ...form.data(),
        is_active: form.is_active ? 1 : 0, // Преобразуем true/false в 1/0
        conditions: form.conditions.map(condition => ({
            ...condition,
            value: Array.isArray(condition.value) ? JSON.stringify(condition.value) : condition.value,
        })),
    };
    router.put(`/auto-rules/${props.rule.id}`, data, {
        onSuccess: (page) => {
            toast.add({
                severity: 'success',
                summary: 'Успішно!',
                detail: page.props.flash?.success || 'Автоправило оновлено',
                life: 3000,
            });
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join("\n");
            toast.add({
                severity: 'error',
                summary: 'Помилка',
                detail: errorMessages,
                life: 5000,
            });
        },
    });
};

watch(form.conditions, () => {
    form.conditions.forEach((rule) => {
        if (rule._prevOperator !== rule.operator) {
            if (['входить в', 'не входить в'].includes(rule.operator)) {
                rule.value = [];
            } else if (rule.operator === 'дорівнює' && getType(rule.field) === 'boolean') {
                rule.value = '1'; // По умолчанию true для boolean
            } else {
                rule.value = '';
            }
            rule._prevOperator = rule.operator;
        }
    });
}, { deep: true });
</script>

<template>
    <Head :title="'Редагувати автоправило для ' + orderStatus.name" />
    <Layout>
        <div class="p-6 space-y-6">
            <div class="flex items-center">
                    <h1 class="text-2xl font-bold">Редагувати автоправило для статусу: </h1>
                    <div
                    class="rounded  p-2 text-white"
                    :style="{ backgroundColor: `#${orderStatus.color}` }"
                    > {{ orderStatus.name }}</div>
                </div>
            <form @submit.prevent="submit" class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex">
                    <div class="mb-4 border-r border-gray-300 pr-4 text-center">
                        <label class="block font-semibold">Активно</label>
                        <input v-model="form.is_active" type="checkbox" class="border rounded" />
                    </div>
                    <div class="ml-2 mb-4">
                        <label class="block font-semibold">Назва автоправила</label>
                        <InputText v-model="form.name" class="w-full border rounded px-2 py-1" required />
                    </div>
                </div>

                <h2 class="text-lg font-bold mb-3">Умови</h2>
                <div v-for="(condition, index) in form.conditions" :key="index" class="flex items-center gap-3 mb-3 bg-white p-3 rounded-md shadow-sm border border-gray-200">
                    <Select v-model="condition.field" :options="availableFields" optionLabel="label" optionValue="value" placeholder="Оберіть поле" class="w-60" />
                    <Select v-model="condition.operator" :options="getOperators(getType(condition.field))" placeholder="Оператор" class="w-48" />
                    <!-- Строковые поля -->
                    <InputText v-if="getType(condition.field) === 'string' && !['входить в', 'не входить в'].includes(condition.operator)" v-model="condition.value" class="w-60 border rounded px-2 py-1" placeholder="Значення" />
                    <!-- Числовые поля -->
                    <InputText v-if="getType(condition.field) === 'number'" v-model="condition.value" type="number" class="w-60 border rounded px-2 py-1" placeholder="Число" />
                    <!-- Поля выбора -->
                    <Select v-if="getType(condition.field) === 'select' && !['входить в', 'не входить в'].includes(condition.operator)" v-model="condition.value" :options="getOptions(condition.field)" optionLabel="name" optionValue="id" class="w-60" />
                    <MultiSelect v-if="getType(condition.field) === 'select' && ['входить в', 'не входить в'].includes(condition.operator)" v-model="condition.value" :options="getOptions(condition.field)" optionLabel="name" optionValue="id" class="w-60" placeholder="Оберіть значення" />
                    <!-- Поля даты -->
                    <InputText v-if="getType(condition.field) === 'date' && !['є значення', 'немає значення'].includes(condition.operator)" v-model="condition.value" type="date" class="w-60 border rounded px-2 py-1" placeholder="Дата" />
                    <!-- Булевы поля -->
                    <Select v-if="getType(condition.field) === 'boolean'" v-model="condition.value" :options="[{ label: 'Так', value: '1' }, { label: 'Ні', value: '0' }]" optionLabel="label" optionValue="value" class="w-60" />
                    <button type="button" @click="removeCondition(index)" class="text-red-500 hover:text-red-700">
                        <Trash class="w-5 h-5" />
                    </button>
                </div>
                <Button type="button" @click="addCondition" label="+ Додати умову" variant="link" />

                <h2 class="text-lg font-bold mb-3 mt-6">Дії</h2>
                <div v-for="(action, index) in form.actions" :key="index" class="flex items-center gap-3 mb-3 bg-white p-3 rounded-md shadow-sm border border-gray-200">
                    <Select v-model="action.type" :options="availableActions" optionLabel="label" optionValue="value" placeholder="Тип дії" class="w-60" />
                    <div v-if="action.type === 'log'" class="flex-1">
                        <InputText v-model="action.parameters.message" class="w-full border rounded px-2 py-1" placeholder="Повідомлення для логу" />
                    </div>
                    <div v-if="action.type === 'send_email'" class="flex-1 space-y-2">
                        <div>
                            <label class="block text-sm">Email отримувача</label>
                            <InputText v-model="action.parameters.recipient" class="w-full border rounded px-2 py-1" placeholder="Email отримувача" />
                        </div>
                        <div>
                            <label class="block text-sm">Тема листа</label>
                            <InputText v-model="action.parameters.subject" class="w-full border rounded px-2 py-1" placeholder="Тема листа" />
                        </div>
                        <div>
                            <label class="block text-sm">Текст листа</label>
                            <Textarea v-model="action.parameters.body" class="w-full border rounded px-2 py-1" placeholder="Текст листа" rows="4" />
                        </div>
                    </div>
                    <button type="button" @click="removeAction(index)" class="text-red-500 hover:text-red-700">
                        <Trash class="w-5 h-5" />
                    </button>
                </div>
                <Button type="button" @click="addAction" label="+ Додати дію" variant="link" />

                <div class="mt-6">
                    <Button type="submit" label="Зберегти" :disabled="form.processing" />
                </div>
            </form>
        </div>
    </Layout>
</template>