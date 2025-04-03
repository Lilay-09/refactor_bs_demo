<?php

namespace Database\Seeders;

use App\Models\ScoringReward;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScoringRewardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        ScoringReward::upsert([
            'id' => 1,
            'code' => '#arzd',
            'message' => 'You will receive a reward of USD ??amount??, which will be transferred to your bank account at the beginning of next month.',
            'description' => 'The remaining points for this month will be carried forward to the end of the year, and we will recalculate your reward accordingly.Thank you for your hard work and commitment to achieving points each month.',
            'channel' => 'driver',
            'reward_type' => 'cashback',
            'create_uid' => 1,
            'update_uid' => 1,
            'branch_id' => 1,
            'company_id' => 1
        ],['id']);
    }
}
