<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeedbackFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        DB::table('feedback_forms')->upsert([
            'id' => 1,
            'name_en' => 'Driver Feedback Form',
            'channel' => 'driver',
            'create_uid' => 1,
            'update_uid' => 1,
            'branch_id' => 1,
            'company_id' => 1
        ],['id']);


        DB::table('feedback_questions')->insert([
            [
                'question_en' => 'ក្រុមហ៊ុនមានគោលការណ៏ច្បាស់លាស់?',
                'create_uid' => 1,
                'form_id' => 1,
                'update_uid' => 1,
                'branch_id' => 1,
                'company_id' => 1
            ],
            [
                'question_en' => 'ក្រុមហ៊ុនយកចិត្តទុកដាក់ល្អចំពោះបុគ្គលិក',
                'create_uid' => 1,
                'form_id' => 1,
                'update_uid' => 1,
                'branch_id' => 1,
                'company_id' => 1
            ]
        ]);
    }
}
