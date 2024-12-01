<script setup>
import { ref } from "vue";
import {  useForm, Link, Head } from "@inertiajs/vue3";

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: ''
});

const errors = ref({});

const submit = () => {
    form.post('/signup', {
        onError:(error) => {
            errors.value = error;
        }
    })
}
</script>

<template>
  <Head title="Реєстрація" />
  <div class="w-full lg:grid lg:min-h-[600px] lg:grid-cols-2 xl:min-h-[800px]">
    <div class="flex items-center justify-center py-12">
      <div class="mx-auto grid w-[350px] gap-6">
        <div class="grid gap-2 text-center">
          <h1 class="text-3xl font-bold">
            Реєстрація аккаунта
          </h1>
          <p class="text-balance text-muted-foreground">
            Якась інформація для клієнтів
          </p>
        </div>
        
        <form @submit.prevent="submit">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <label for="name">Ім'я</label>
                    <InputText
                    id="name"
                    v-model="form.name"
                    :class="{'is-invalid': errors.name}"
                    type="text"
                    placeholder="Ваше Ім'я"
                    required
                    autocomplete="false"
                    autofocus
                    />
                    <span v-if="errors.name">{{ errors.name }}</span>
            </div>
            <div class="grid gap-2">
                <label for="email">Email</label>
                <InputText
                id="email"
                v-model="form.email"
                :class="{'is-invalid': errors.email}"
                type="email"
                placeholder="m@example.com"
                required
                />
                <span v-if="errors.email">{{ errors.email }}</span>
            </div>
            <div class="grid gap-2">
                <div class="flex items-center">
                    <label for="password">Пароль</label>
                </div>
                <InputText 
                id="password" 
                v-model="form.password"
                :class="{'is-invalid': errors.password}"
                type="password" 
                placeholder="**********" 
                required />
                <span v-if="errors.password">{{ errors.password }}</span>
            </div>
            <div class="grid gap-2">
                <div class="flex items-center">
                    <label for="password_confirmation">Підтвердження пароля</label>
                </div>
                <InputText 
                id="password_confirmation" 
                v-model="form.password_confirmation"
                :class="{'is-invalid': errors.password_confirmation}"
                type="password" 
                placeholder="**********" 
                required />
                <span v-if="errors.password_confirmation">{{ errors.password_confirmation }}</span>
            </div>
            <Button type="submit" class="w-full">
                Зареєструватися
            </Button>
            </div>
        </form>
        <div class="mt-4 text-center text-sm">
          Вже є аккант?
          <Link href="/login" class="underline">
            Увійти!
          </Link>
        </div>
      </div>
    </div>
    <div class="hidden bg-muted lg:block">
      <img
        src="https://spiresafety.com.au/wp-content/uploads/2024/06/Storage-and-handling-best-practices-In-Australia.jpg"
        alt="Image"
 
        class="h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
      >
    </div>
  </div>
</template>