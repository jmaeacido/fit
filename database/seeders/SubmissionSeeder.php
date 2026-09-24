<?php

namespace Database\Seeders;

use App\Models\Submission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        if (Submission::query()->exists()) {
            return;
        }

        $samples = [
            ['name' => 'Alex Morgan', 'display_name' => 'MORGAN', 'display_number' => '07', 'size' => 'M', 'designs' => ['1', '2']],
            ['name' => 'Jordan Lee', 'display_name' => 'LEE', 'display_number' => '12', 'size' => 'L', 'designs' => ['2']],
            ['name' => 'Sam Rivera', 'display_name' => 'RIVERA', 'display_number' => '03', 'size' => 'S', 'designs' => ['1', '2']],
            ['name' => 'Casey Nguyen', 'display_name' => 'NGUYEN', 'display_number' => '21', 'size' => 'XL', 'designs' => ['2']],
            ['name' => 'Taylor Brooks', 'display_name' => 'BROOKS', 'display_number' => '44', 'size' => 'M', 'designs' => ['2']],
            ['name' => 'Riley Santos', 'display_name' => 'SANTOS', 'display_number' => '09', 'size' => 'XS', 'designs' => ['1', '2']],
            ['name' => 'Avery Cruz', 'display_name' => 'CRUZ', 'display_number' => '18', 'size' => '2XL', 'designs' => ['2']],
            ['name' => 'Quinn Patel', 'display_name' => 'PATEL', 'display_number' => '27', 'size' => 'L', 'designs' => ['1', '2']],
            ['name' => 'Morgan Diaz', 'display_name' => 'DIAZ', 'display_number' => '15', 'size' => 'S', 'designs' => ['2']],
            ['name' => 'Jamie Torres', 'display_name' => 'TORRES', 'display_number' => '33', 'size' => '3XL', 'designs' => ['2']],
            ['name' => 'Cameron Reyes', 'display_name' => 'REYES', 'display_number' => '01', 'size' => 'M', 'designs' => ['1', '2']],
            ['name' => 'Drew Castillo', 'display_name' => 'CASTILLO', 'display_number' => '88', 'size' => 'XL', 'designs' => ['1', '2']],
        ];

        foreach ($samples as $sample) {
            Submission::create([
                ...$sample,
                'request_key' => (string) Str::uuid(),
            ]);
        }
    }
}
