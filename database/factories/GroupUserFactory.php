<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use App\Models\GroupUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupUserFactory extends Factory
{
    protected $model = GroupUser::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
        ];
    }
}
