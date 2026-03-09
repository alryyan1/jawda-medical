<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Room;
use App\Models\Ward;
use Illuminate\Database\Seeder;

/**
 * Seeds wards and rooms as per bed map layout:
 * - Ward 1 Women (عنبر 1 حريمات): 1 room, 5 beds
 * - Ward 2 Men (عنبر 2 رجال): 1 room, 5 beds
 * - Ward 3 Children (عنبر 3 اطفال): 1 room, 6 beds
 * - Private/VIP: 2 rooms (غرفة خاصة 4, غرفة خاصة 5), 1 bed each
 */
class WomenMenChildrenVipRoomsSeeder extends Seeder
{
    public function run(): void
    {
        $wards = [
            [
                'name'        => 'عنبر 1 حريمات',
                'description' => 'قسم النساء',
                'rooms'       => [
                    [
                        'room_number'   => '1',
                        'room_type'     => 'normal',
                        'capacity'      => 5,
                        'price_per_day' => 50000,
                        'beds_count'    => 5,
                    ],
                ],
            ],
            [
                'name'        => 'عنبر 2 رجال',
                'description' => 'قسم الرجال',
                'rooms'       => [
                    [
                        'room_number'   => '1',
                        'room_type'     => 'normal',
                        'capacity'      => 5,
                        'price_per_day' => 50000,
                        'beds_count'    => 5,
                    ],
                ],
            ],
            [
                'name'        => 'عنبر 3 اطفال',
                'description' => 'قسم الأطفال',
                'rooms'       => [
                    [
                        'room_number'   => '1',
                        'room_type'     => 'normal',
                        'capacity'      => 6,
                        'price_per_day' => 50000,
                        'beds_count'    => 6,
                    ],
                ],
            ],
            [
                'name'        => 'غرف خاصة',
                'description' => 'غرف VIP خاصة',
                'rooms'       => [
                    [
                        'room_number'   => '4',
                        'room_type'     => 'vip',
                        'capacity'      => 1,
                        'price_per_day' => 150000,
                        'beds_count'    => 1,
                    ],
                    [
                        'room_number'   => '5',
                        'room_type'     => 'vip',
                        'capacity'      => 1,
                        'price_per_day' => 150000,
                        'beds_count'    => 1,
                    ],
                ],
            ],
        ];

        foreach ($wards as $wardData) {
            $roomConfigs = $wardData['rooms'];
            unset($wardData['rooms']);

            $ward = Ward::firstOrCreate(
                ['name' => $wardData['name']],
                [
                    'description' => $wardData['description'],
                    'status'      => true,
                ]
            );
            $this->command->info("Ward: {$ward->name} (id: {$ward->id})");

            foreach ($roomConfigs as $rc) {
                $bedsCount = $rc['beds_count'];
                unset($rc['beds_count']);

                $room = Room::firstOrCreate(
                    [
                        'ward_id'     => $ward->id,
                        'room_number' => $rc['room_number'],
                    ],
                    [
                        'room_type'     => $rc['room_type'],
                        'capacity'      => $rc['capacity'],
                        'price_per_day' => $rc['price_per_day'],
                        'status'       => true,
                    ]
                );
                $this->command->info("  Room: {$room->room_number} ({$room->room_type}, capacity {$room->capacity})");

                $existingBeds = $room->beds()->count();
                if ($existingBeds >= $bedsCount) {
                    $this->command->info("    Beds already exist ({$existingBeds}). Skipping.");
                    continue;
                }

                $start = $existingBeds + 1;
                for ($n = $start; $n <= $bedsCount; $n++) {
                    Bed::firstOrCreate(
                        [
                            'room_id'   => $room->id,
                            'bed_number' => (string) $n,
                        ],
                        ['status' => 'available']
                    );
                }
                $this->command->info("    Beds: {$existingBeds} existing, " . ($bedsCount - $existingBeds) . " created (total {$bedsCount}).");
            }
        }

        $this->command->info('Women/Men/Children/VIP rooms and beds seeded.');
    }
}
