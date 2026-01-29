<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomAndBedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 3 default wards if none exist
        $wards = \App\Models\Ward::all();

        if ($wards->isEmpty()) {
            $this->command->info('No wards found. Creating 3 default wards...');

            $wardData = [
                ['name' => 'الباطنية', 'description' => 'قسم الأمراض الباطنية'],
                ['name' => 'الجراحة', 'description' => 'قسم الجراحة العامة'],
                ['name' => 'العناية المركزة', 'description' => 'وحدة العناية المركزة'],
            ];

            foreach ($wardData as $data) {
                $ward = \App\Models\Ward::create($data);
                $this->command->info("Created Ward: {$ward->name}");
            }

            // Reload wards
            $wards = \App\Models\Ward::all();
        }

        $this->command->info('Creating 10 rooms with 10 beds each...');

        // Create 10 rooms
        for ($i = 1; $i <= 10; $i++) {
            // Distribute rooms across wards
            $ward = $wards->random();

            // Determine room type
            $roomType = $i <= 2 ? 'vip' : 'normal';
            $pricePerDay = $roomType === 'vip' ? 150000 : 50000;

            $room = \App\Models\Room::create([
                'ward_id' => $ward->id,
                'room_number' => 'R-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'room_type' => $roomType,
                'capacity' => 10,
                'status' => true,
                'price_per_day' => $pricePerDay,
            ]);

            $this->command->info("Created Room: {$room->room_number} in {$ward->name} ({$roomType})");

            // Create 10 beds for this room
            for ($j = 1; $j <= 10; $j++) {
                $bed = \App\Models\Bed::create([
                    'room_id' => $room->id,
                    'bed_number' => 'B-' . str_pad($j, 2, '0', STR_PAD_LEFT),
                    'status' => 'available',
                ]);
            }

            $this->command->info("  Created 10 beds for {$room->room_number}");
        }

        $this->command->info('Successfully created 10 rooms with 100 total beds!');
    }
}
