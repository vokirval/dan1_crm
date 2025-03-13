<script setup>
import { useForm, Head, usePage, router } from "@inertiajs/vue3";
import Layout from "../../Layout/App.vue";
import { useToast } from "primevue/usetoast";
import { Trash, Plus, Boxes, Box } from "lucide-vue-next";
import { useConfirm } from "primevue/useconfirm";

const confirm = useConfirm();
const toast = useToast();
const page = usePage();

const props = defineProps({
    product: Object,
    categories: Object,
});

const form = useForm({
    name: props.product.name,
    sku: props.product.sku,
    description: props.product.description,
    type: props.product.type,
    price: props.product.price,
    discounted_price: props.product.discounted_price,
    cost: props.product.cost,
    stock: props.product.stock,
    status: props.product.status,
    category_id: props.product.category_id,
    short_name: props.product.short_name,
    weight: props.product.weight,
    length: props.product.length,
    width: props.product.width,
    height: props.product.height,
});

const variationForm = useForm({
    sku: "",
    price: "",
    discounted_price: "",
    cost: "",
    stock: "",
    status: "",
    attributes: [
        { attribute_name: "", attribute_value: "" }, // По умолчанию один атрибут
    ],
});

function addAttribute() {
    variationForm.attributes.push({ attribute_name: "", attribute_value: "" });
}

function removeAttribute(index) {
    variationForm.attributes.splice(index, 1);
}

function formatAttributes(attributes) {
    if (!attributes || attributes.length === 0) {
        return "No attributes";
    }

    return attributes
        .map((attr) => `${attr.attribute_name}: ${attr.attribute_value}`)
        .join(", ");
}

function updateProduct() {
    form.put(`/products/${props.product.id}`, {
        preserveScroll: true,
        onSuccess: () => {
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
}

function addVariation() {
    variationForm.post(`/products/${props.product.id}/variations`, {
        onSuccess: () => {
            variationForm.reset();
            variationForm.attributes = [
                { attribute_name: "", attribute_value: "" },
            ];
            toast.add({
                severity: "success",
                summary: "Success",
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
}

const types = [
    { name: "Простий", value: "simple" },
    { name: "Варіативний", value: "variable" },
];
const statuses = [
    { name: "Активний", value: "active" },
    { name: "Неактивний", value: "inactive" },
];

const confirmDelete = (event, data) => {
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
            router.delete(
                `/products/${props.product.id}/variations/${data.id}`,
                {
                    onSuccess: () => {
                        toast.add({
                            severity: "info",
                            summary: "Confirmed",
                            detail: page.props.flash.success,
                            life: 3000,
                        });
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
                }
            );
        },
    });
};
</script>

<template>

    <Head :title="`${product.name}`" />
    <Layout>
        <Tabs value="0">
            <div class="flex justify-between items-center">
                <TabList>
                    <Tab value="0">
                        <Box class="w-5 h-5 inline-block" /> Налаштування
                        Товару
                    </Tab>
                    <Tab value="1" v-if="product?.type == 'variable'">
                        <Boxes class="w-5 h-5 inline-block" /> Налаштування
                        Варіацій
                    </Tab>
                </TabList>
            </div>
            <TabPanels>
                <TabPanel value="0">
                    <Card>
                        <template #content>
                            <form @submit.prevent="updateProduct">
                                <div class="grid md:grid-cols-2 w-full gap-5">
                                    <div>
                                        <IftaLabel>
                                            <label for="product_name">Назва товару</label>
                                            <InputText id="product_name" v-model="form.name" class="w-full" />
                                        </IftaLabel>
                                        <IftaLabel class="mt-5">
                                            <label for="product_short_name">Коротка назва</label>
                                            <InputText id="product_short_name" v-model="form.short_name"
                                                class="w-full" />
                                        </IftaLabel>
                                        <IftaLabel class="mt-5">
                                            <label for="product_sku">SKU</label>
                                            <InputText id="product_sku" v-model="form.sku" class="w-full" />
                                        </IftaLabel>
                                        <IftaLabel class="mt-5">
                                            <label for="product_description">Опис</label>
                                            <Textarea id="product_description" class="w-full min-h-[120px]"
                                                v-model="form.description" />
                                        </IftaLabel>

                                        <IftaLabel class="mt-5">
                                            <Select v-model="form.category_id" optionValue="id" :options="categories"
                                                optionLabel="name" placeholder="Оберіть категорію" class="w-full" />
                                            <label for="product_quantity">Категорія товару</label>
                                        </IftaLabel>

                                        <IftaLabel class="mt-5">
                                            <Select v-model="form.status" optionValue="value" :options="statuses"
                                                optionLabel="name" placeholder="Статус товару" class="w-full" />
                                            <label for="product_quantity">Статус товару</label>
                                        </IftaLabel>
                                    </div>
                                    <div>
                                        <IftaLabel>
                                            <Select v-model="form.type" optionValue="value" :options="types"
                                                optionLabel="name" placeholder="Тип товару" class="w-full" />
                                            <label for="product_quantity">Тип товару</label>
                                        </IftaLabel>

                                        <IftaLabel class="mt-5">
                                            <label for="product_quantity">Залишок</label>
                                            <InputText disabled id="product_quantity" v-model="form.stock"
                                                class="w-full" />
                                        </IftaLabel>
                                        <div class="grid md:grid-cols-2 w-full gap-5 mt-5">
                                            <IftaLabel class="mt-5">
                                                <InputNumber v-model="form.discounted_price
                                                    " id="product_discounted_price" locale="ua-UA"
                                                    :minFractionDigits="2" fluid />
                                                <label for="product_discounted_price">
                                                    Акційна ціна
                                                </label>
                                            </IftaLabel>

                                            <IftaLabel class="mt-5">
                                                <InputNumber v-model="form.price" id="product_price" locale="ua-UA"
                                                    :minFractionDigits="2" fluid />
                                                <label for="product_price">Стара Ціна</label>
                                            </IftaLabel>
                                        </div>
                                        <IftaLabel class="mt-5">
                                            <InputNumber v-model="form.cost" id="product_cost" locale="ua-UA"
                                                :minFractionDigits="2" fluid />
                                            <label for="product_cost">Собівартість</label>
                                        </IftaLabel>
                                        <div class="mt-2 mb-5">
                                            <Fieldset legend="Деталі посилки" :toggleable="true" :collapsed="false">
                                                <div class="flex gap-2">
                                                    <div class="mb-4">
                                                        <label>Вага (кг)</label>
                                                        <InputText v-model="form.weight" class="w-full" />
                                                    </div>
                                                    <div class="mb-4">
                                                        <label>Довжина (мм)</label>
                                                        <InputText v-model="form.length" class="w-full" />
                                                    </div>
                                                    <div class="mb-4">
                                                        <label>Ширина (мм)</label>
                                                        <InputText v-model="form.width" class="w-full" />
                                                    </div>
                                                    <div class="mb-4">
                                                        <label>Висота (мм)</label>
                                                        <InputText v-model="form.height" class="w-full" />
                                                    </div>
                                                </div>
                                            </Fieldset>
                                        </div>
                                    </div>
                                </div>
                                <Button type="submit" class="w-full mt-5">Оновити товар</Button>
                            </form>
                        </template>
                    </Card>
                </TabPanel>
                <TabPanel value="1" v-if="product?.type == 'variable'">
                    <Card>
                        <template #content>
                            <div class="grid md:grid-cols-2 w-full gap-5">
                                <form @submit.prevent="addVariation">
                                    <IftaLabel>
                                        <InputText v-model="variationForm.sku" id="varsku" class="w-full" />
                                        <label for="varsku">SKU</label>
                                    </IftaLabel>

                                    <IftaLabel class="mt-5">
                                        <InputNumber v-model="variationForm.price" id="varprice" class="w-full"
                                            locale="ua-UA" :minFractionDigits="2" />
                                        <label for="varprice">Ціна</label>
                                    </IftaLabel>
                                    <IftaLabel class="mt-5">
                                        <InputNumber v-model="variationForm.discounted_price
                                            " id="vardiscprice" class="w-full" locale="ua-UA" :minFractionDigits="2" />
                                        <label for="vardiscprice">Акційна ціна</label>
                                    </IftaLabel>
                                    <IftaLabel class="mt-5">
                                        <InputNumber v-model="variationForm.cost" id="varcost" class="w-full"
                                            locale="ua-UA" :minFractionDigits="2" />
                                        <label for="varcost">Собівартість</label>
                                    </IftaLabel>
                                    <IftaLabel class="mt-5">
                                        <InputNumber v-model="variationForm.stock" id="varstock" class="w-full" />
                                        <label for="varstock">Наявність</label>
                                    </IftaLabel>
                                    <IftaLabel class="w-full mt-3" variant="on">
                                        <Select v-model="variationForm.status" optionValue="value" :options="statuses"
                                            optionLabel="name" class="w-full" />
                                        <label for="product_quantity">Статус Варіації</label>
                                    </IftaLabel>

                                    <div v-for="(
attribute, index
                                        ) in variationForm.attributes" :key="index"
                                        class="grid grid-flow-col gap-2 mt-3">
                                        <InputText v-model="attribute.attribute_name" placeholder="Назва Атрибута"
                                            class="w-full" />
                                        <InputText v-model="attribute.attribute_value" placeholder="Значення атрибута"
                                            class="w-full" />
                                        <Button type="button" severity="secondary" @click="removeAttribute(index)"
                                            class="w-auto">
                                            <Trash class="h-4 w-4" />
                                        </Button>
                                    </div>

                                    <Button variant="text" type="button" @click="addAttribute" class="mt-3">
                                        <Plus class="w-4 h-4" />Додати
                                        Атрибут
                                    </Button>
                                    <Button type="submit" class="w-full mt-3">Створити Варіацію</Button>
                                </form>

                                <DataTable :value="product.variations" showGridlines :responsiveLayout="'scroll'">
                                    <Column field="id" header="ID"></Column>
                                    <Column field="sku" header="SKU"></Column>
                                    <Column field="price" header="Ціна"></Column>
                                    <Column field="stock" header="Наявність"></Column>
                                    <Column header="Атрибути">
                                        <template #body="slotProps">
                                            {{
                                                formatAttributes(
                                                    slotProps.data.attributes
                                                )
                                            }}
                                        </template>
                                    </Column>
                                    <Column class="">
                                        <template #body="{ data }">
                                            <Button severity="secondary" @click="
                                                confirmDelete($event, data)
                                                ">
                                                <Trash class="w-4 h-4" />
                                            </Button>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </template>
                    </Card>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </Layout>
</template>
