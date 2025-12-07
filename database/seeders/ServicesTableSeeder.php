<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ServicesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('services')->delete();
        
        \DB::table('services')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'عمومي',
                'service_group_id' => 1,
                'price' => 3000.0,
                'activate' => 0,
                'created_at' => '2024-12-03 00:55:58',
                'updated_at' => '2024-12-19 15:15:42',
            ),
            1 => 
            array (
                'id' => 3,
                'name' => 'خلع',
                'service_group_id' => 11,
                'price' => 27000.0,
                'activate' => 0,
                'created_at' => '2024-12-04 20:49:23',
                'updated_at' => '2025-01-22 21:52:34',
            ),
            2 => 
            array (
                'id' => 4,
                'name' => 'نظافة',
                'service_group_id' => 10,
                'price' => 62000.0,
                'activate' => 0,
                'created_at' => '2024-12-04 21:53:03',
                'updated_at' => '2025-01-23 15:49:17',
            ),
            3 => 
            array (
                'id' => 5,
                'name' => 'علاج جزو',
                'service_group_id' => 17,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-04 23:09:53',
                'updated_at' => '2025-01-23 12:56:17',
            ),
            4 => 
            array (
                'id' => 6,
                'name' => 'كشف اخصائى',
                'service_group_id' => 1,
                'price' => 5000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:11:35',
                'updated_at' => '2025-01-23 12:53:53',
            ),
            5 => 
            array (
                'id' => 7,
                'name' => 'علاج جزور  جلسه',
                'service_group_id' => 17,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:12:15',
                'updated_at' => '2025-01-23 12:53:40',
            ),
            6 => 
            array (
                'id' => 8,
                'name' => 'خلع جراجي',
                'service_group_id' => 11,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:13:08',
                'updated_at' => '2025-01-23 12:53:35',
            ),
            7 => 
            array (
                'id' => 9,
                'name' => 'علاج جزور كامل اطفال',
                'service_group_id' => 12,
                'price' => 57000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:15:35',
                'updated_at' => '2025-01-23 12:53:32',
            ),
            8 => 
            array (
                'id' => 10,
                'name' => 'علاج  جزور كامل',
                'service_group_id' => 17,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:16:23',
                'updated_at' => '2025-01-23 12:53:25',
            ),
            9 => 
            array (
                'id' => 11,
                'name' => 'تلبيسه معدينه الاطفال',
                'service_group_id' => 12,
                'price' => 62000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:19:43',
                'updated_at' => '2025-01-23 12:52:53',
            ),
            10 => 
            array (
                'id' => 12,
                'name' => 'فلورايد',
                'service_group_id' => 12,
                'price' => 25000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:20:01',
                'updated_at' => '2025-01-23 12:52:47',
            ),
            11 => 
            array (
                'id' => 13,
                'name' => 'خلع ضرس عقل عادي',
                'service_group_id' => 11,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 16:48:27',
                'updated_at' => '2025-01-23 12:52:34',
            ),
            12 => 
            array (
                'id' => 14,
                'name' => 'عمليه جراحيه كبيره',
                'service_group_id' => 8,
                'price' => 202000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:10:36',
                'updated_at' => '2025-01-23 12:52:28',
            ),
            13 => 
            array (
                'id' => 15,
                'name' => 'عمليه جراحيه متوسطه',
                'service_group_id' => 8,
                'price' => 152000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:11:11',
                'updated_at' => '2025-01-23 12:52:26',
            ),
            14 => 
            array (
                'id' => 16,
                'name' => 'عمليه جراحيه صغيره',
                'service_group_id' => 8,
                'price' => 122000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:11:43',
                'updated_at' => '2025-01-23 12:52:20',
            ),
            15 => 
            array (
                'id' => 17,
                'name' => 'فك خيط',
                'service_group_id' => 16,
                'price' => 20000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:12:13',
                'updated_at' => '2025-01-23 12:51:36',
            ),
            16 => 
            array (
                'id' => 18,
                'name' => 'فتح خراج',
                'service_group_id' => 16,
                'price' => 82000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:13:08',
                'updated_at' => '2025-01-23 12:51:25',
            ),
            17 => 
            array (
                'id' => 19,
                'name' => 'نظافه dry socket',
                'service_group_id' => 16,
                'price' => 464000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:23:15',
                'updated_at' => '2025-01-23 12:52:14',
            ),
            18 => 
            array (
                'id' => 20,
                'name' => 'اظهار نائب جراحي',
                'service_group_id' => 16,
                'price' => 122000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:26:00',
                'updated_at' => '2025-01-23 12:51:59',
            ),
            19 => 
            array (
                'id' => 21,
                'name' => 'مسمار',
                'service_group_id' => 14,
                'price' => 62000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:26:19',
                'updated_at' => '2025-01-23 12:47:54',
            ),
            20 => 
            array (
                'id' => 22,
                'name' => 'تثبيت تركيب للسن',
                'service_group_id' => 14,
                'price' => 42000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:27:14',
                'updated_at' => '2025-01-23 12:47:41',
            ),
            21 => 
            array (
                'id' => 23,
                'name' => 'تلبيسه سيراميك',
                'service_group_id' => 14,
                'price' => 100000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:30:38',
                'updated_at' => '2025-01-23 12:47:30',
            ),
            22 => 
            array (
                'id' => 24,
                'name' => 'تلبيسه زركونيوم',
                'service_group_id' => 14,
                'price' => 132000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:35:09',
                'updated_at' => '2025-01-23 12:44:22',
            ),
            23 => 
            array (
                'id' => 25,
                'name' => 'تلبيسه Emax',
                'service_group_id' => 12,
                'price' => 172000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:36:14',
                'updated_at' => '2025-01-23 12:44:12',
            ),
            24 => 
            array (
                'id' => 26,
                'name' => 'علاج جزور كلي للاطفال للجلسه',
                'service_group_id' => 12,
                'price' => 32000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:40:20',
                'updated_at' => '2025-01-23 12:40:51',
            ),
            25 => 
            array (
                'id' => 27,
                'name' => 'نظافه لثه كامله',
                'service_group_id' => 10,
                'price' => 62000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:41:13',
                'updated_at' => '2025-01-23 12:40:44',
            ),
            26 => 
            array (
                'id' => 28,
                'name' => 'نظافه لثه للفك الواحد',
                'service_group_id' => 10,
                'price' => 42000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:41:48',
                'updated_at' => '2025-01-23 12:22:14',
            ),
            27 => 
            array (
                'id' => 29,
                'name' => 'تلميع',
                'service_group_id' => 10,
                'price' => 32000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:42:47',
                'updated_at' => '2025-01-23 12:21:58',
            ),
            28 => 
            array (
                'id' => 30,
                'name' => 'نظافه جيب لثوي',
                'service_group_id' => 10,
                'price' => 22000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:43:59',
                'updated_at' => '2025-01-23 12:21:49',
            ),
            29 => 
            array (
                'id' => 31,
                'name' => 'ترقيع لثه',
                'service_group_id' => 10,
                'price' => 32000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:44:22',
                'updated_at' => '2025-01-23 12:20:20',
            ),
            30 => 
            array (
                'id' => 32,
                'name' => 'تطويل تاج',
                'service_group_id' => 10,
                'price' => 152000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:45:31',
                'updated_at' => '2025-01-23 12:20:02',
            ),
            31 => 
            array (
                'id' => 33,
                'name' => 'حشوه ضوئيه',
                'service_group_id' => 15,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:46:12',
                'updated_at' => '2025-01-23 12:19:49',
            ),
            32 => 
            array (
                'id' => 34,
                'name' => 'حشوه مؤقته',
                'service_group_id' => 15,
                'price' => 22000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:47:21',
                'updated_at' => '2025-01-23 12:19:41',
            ),
            33 => 
            array (
                'id' => 35,
                'name' => 'حشوه علاجيه',
                'service_group_id' => 15,
                'price' => 52000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:48:00',
                'updated_at' => '2025-01-23 12:19:26',
            ),
            34 => 
            array (
                'id' => 36,
                'name' => 'حشوه تجميليه',
                'service_group_id' => 15,
                'price' => 52000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:48:33',
                'updated_at' => '2025-01-23 12:19:12',
            ),
            35 => 
            array (
                'id' => 37,
                'name' => 'حشوه زجاجيه',
                'service_group_id' => 15,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:49:11',
                'updated_at' => '2025-01-23 12:19:05',
            ),
            36 => 
            array (
                'id' => 38,
                'name' => 'جلسه علاج جزور',
                'service_group_id' => 17,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:54:34',
                'updated_at' => '2025-01-23 12:18:55',
            ),
            37 => 
            array (
                'id' => 39,
                'name' => 'اعاده علاج جذور  للجلسه',
                'service_group_id' => 17,
                'price' => 47000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:55:48',
                'updated_at' => '2025-01-23 12:18:50',
            ),
            38 => 
            array (
                'id' => 40,
                'name' => 'اشعه صغيره',
                'service_group_id' => 4,
                'price' => 5000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:57:13',
                'updated_at' => '2025-01-15 19:30:28',
            ),
            39 => 
            array (
                'id' => 41,
                'name' => 'اشعه خارجيه',
                'service_group_id' => 4,
                'price' => 6000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:57:45',
                'updated_at' => '2025-01-15 19:30:25',
            ),
            40 => 
            array (
                'id' => 42,
                'name' => 'خلع اخصائي',
                'service_group_id' => 16,
                'price' => 42000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:58:15',
                'updated_at' => '2025-01-23 12:18:39',
            ),
            41 => 
            array (
                'id' => 43,
                'name' => 'خلع ضرس عقل عادي اخصائي',
                'service_group_id' => 16,
                'price' => 45000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 17:58:54',
                'updated_at' => '2025-01-23 12:18:31',
            ),
            42 => 
            array (
                'id' => 44,
                'name' => 'حافظ مسافه اطفال صغير',
                'service_group_id' => 12,
                'price' => 102000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:01:44',
                'updated_at' => '2025-01-23 12:16:28',
            ),
            43 => 
            array (
                'id' => 45,
                'name' => 'حافظ مسافه اطفال كبير',
                'service_group_id' => 12,
                'price' => 122000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:02:54',
                'updated_at' => '2025-01-23 12:16:14',
            ),
            44 => 
            array (
                'id' => 46,
                'name' => 'خلع جراحي',
                'service_group_id' => 11,
                'price' => 37000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:03:30',
                'updated_at' => '2025-01-23 12:16:06',
            ),
            45 => 
            array (
                'id' => 47,
                'name' => 'OPG',
                'service_group_id' => 4,
                'price' => 10000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:05:53',
                'updated_at' => '2025-01-23 12:05:46',
            ),
            46 => 
            array (
                'id' => 48,
                'name' => 'section OPG',
                'service_group_id' => 4,
                'price' => 6000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:06:53',
                'updated_at' => '2025-01-23 12:05:39',
            ),
            47 => 
            array (
                'id' => 49,
                'name' => 'MTA',
                'service_group_id' => 12,
                'price' => 82000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:07:30',
                'updated_at' => '2025-01-23 12:02:23',
            ),
            48 => 
            array (
                'id' => 50,
                'name' => 'حارس ليلي للفلك الواحد',
                'service_group_id' => 14,
                'price' => 35000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:09:24',
                'updated_at' => '2025-01-23 12:02:18',
            ),
            49 => 
            array (
                'id' => 51,
                'name' => 'خلع جراحي بسيط',
                'service_group_id' => 16,
                'price' => 42000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:13:17',
                'updated_at' => '2025-01-23 12:01:56',
            ),
            50 => 
            array (
                'id' => 52,
                'name' => 'خلع جراحي متوسط',
                'service_group_id' => 16,
                'price' => 82000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:13:45',
                'updated_at' => '2025-01-23 12:01:52',
            ),
            51 => 
            array (
                'id' => 53,
                'name' => 'خلع جراحي معقد',
                'service_group_id' => 16,
                'price' => 92000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:14:17',
                'updated_at' => '2025-01-23 12:01:45',
            ),
            52 => 
            array (
                'id' => 54,
                'name' => 'OPG خارجيه',
                'service_group_id' => 4,
                'price' => 15000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:15:48',
                'updated_at' => '2025-01-23 12:01:33',
            ),
            53 => 
            array (
                'id' => 55,
                'name' => 'خلع جراحي اخصائي',
                'service_group_id' => 16,
                'price' => 52000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:16:40',
                'updated_at' => '2025-01-23 12:01:18',
            ),
            54 => 
            array (
                'id' => 56,
                'name' => 'خلع عادي اخصائي',
                'service_group_id' => 16,
                'price' => 42000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:17:06',
                'updated_at' => '2025-01-23 12:01:05',
            ),
            55 => 
            array (
                'id' => 57,
                'name' => 'خلع جراجي ضرس عقل اخصائي',
                'service_group_id' => 16,
                'price' => 62000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:19:35',
                'updated_at' => '2025-01-23 12:00:57',
            ),
            56 => 
            array (
                'id' => 58,
                'name' => 'خلع بعمليه اخصائي',
                'service_group_id' => 16,
                'price' => 202000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:20:05',
                'updated_at' => '2025-01-23 12:00:10',
            ),
            57 => 
            array (
                'id' => 59,
                'name' => 'خياطه جراح اخصائي',
                'service_group_id' => 16,
                'price' => 52000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:20:38',
                'updated_at' => '2025-01-23 12:00:02',
            ),
            58 => 
            array (
                'id' => 60,
                'name' => 'خراج صغيره اخصائي',
                'service_group_id' => 8,
                'price' => 42000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:21:24',
                'updated_at' => '2025-01-08 19:45:28',
            ),
            59 => 
            array (
                'id' => 61,
                'name' => 'اللتهاب اغشيه محيطه اخصائي',
                'service_group_id' => 16,
                'price' => 70000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:24:15',
                'updated_at' => '2025-01-23 11:59:20',
            ),
            60 => 
            array (
                'id' => 62,
                'name' => 'عده اغشيه محيطه',
                'service_group_id' => 16,
                'price' => 152000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:24:47',
                'updated_at' => '2025-01-23 11:59:03',
            ),
            61 => 
            array (
                'id' => 63,
                'name' => 'غيار خراج اخصائي',
                'service_group_id' => 16,
                'price' => 22000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:25:23',
                'updated_at' => '2025-01-23 11:58:57',
            ),
            62 => 
            array (
                'id' => 64,
                'name' => 'ازالة اغشيه ميته اخصائي',
                'service_group_id' => 16,
                'price' => 300000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:27:42',
                'updated_at' => '2025-01-23 11:58:49',
            ),
            63 => 
            array (
                'id' => 65,
                'name' => 'غيار اغشيه اخصائي',
                'service_group_id' => 16,
                'price' => 52000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:28:20',
                'updated_at' => '2025-01-23 11:58:41',
            ),
            64 => 
            array (
                'id' => 66,
                'name' => 'عينه جزئيه انسجه اخصائي',
                'service_group_id' => 16,
                'price' => 77000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:31:41',
                'updated_at' => '2025-01-23 11:58:16',
            ),
            65 => 
            array (
                'id' => 67,
                'name' => 'عينه جزئيه عضميه اخصائي',
                'service_group_id' => 16,
                'price' => 102000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:32:35',
                'updated_at' => '2025-01-23 11:58:11',
            ),
            66 => 
            array (
                'id' => 68,
                'name' => 'عينه كامله انسجه اخصائي',
                'service_group_id' => 16,
                'price' => 102000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:34:45',
                'updated_at' => '2025-01-23 11:58:07',
            ),
            67 => 
            array (
                'id' => 69,
                'name' => 'عينه كامله عضميه اخصائي',
                'service_group_id' => 16,
                'price' => 122000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:35:41',
                'updated_at' => '2025-01-23 11:58:02',
            ),
            68 => 
            array (
                'id' => 70,
                'name' => 'تثبيت كسر سن واحد بواسطه الحشوه الضوئيه',
                'service_group_id' => 16,
                'price' => 122000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:37:24',
                'updated_at' => '2025-01-23 11:57:50',
            ),
            69 => 
            array (
                'id' => 71,
                'name' => 'جبيره جزئيه',
                'service_group_id' => 16,
                'price' => 172000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:38:13',
                'updated_at' => '2025-01-23 11:57:43',
            ),
            70 => 
            array (
                'id' => 72,
                'name' => 'جبيره كاملة',
                'service_group_id' => 16,
                'price' => 272000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:38:35',
                'updated_at' => '2025-01-23 11:57:36',
            ),
            71 => 
            array (
                'id' => 73,
                'name' => 'فك جبيره كامله',
                'service_group_id' => 16,
                'price' => 72000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:38:58',
                'updated_at' => '2025-01-23 11:57:30',
            ),
            72 => 
            array (
                'id' => 74,
                'name' => 'فك سلك',
                'service_group_id' => 16,
                'price' => 32000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:39:27',
                'updated_at' => '2025-01-23 11:57:25',
            ),
            73 => 
            array (
                'id' => 75,
                'name' => 'ازاله رصاصه او شظايا  تحت البنج الموضعي',
                'service_group_id' => 16,
                'price' => 252000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:40:32',
                'updated_at' => '2025-01-23 11:57:19',
            ),
            74 => 
            array (
                'id' => 76,
                'name' => 'خياطه جرح بسيط',
                'service_group_id' => 16,
                'price' => 27000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:41:18',
                'updated_at' => '2025-01-23 11:57:12',
            ),
            75 => 
            array (
                'id' => 77,
                'name' => 'خلع عقل جراحي',
                'service_group_id' => 11,
                'price' => 52000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:42:26',
                'updated_at' => '2025-01-23 11:57:03',
            ),
            76 => 
            array (
                'id' => 78,
                'name' => 'تمارين',
                'service_group_id' => 16,
                'price' => 12000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:43:21',
                'updated_at' => '2025-01-23 11:56:55',
            ),
            77 => 
            array (
                'id' => 79,
                'name' => 'عضه ليليه حارس اخصائي',
                'service_group_id' => 10,
                'price' => 122000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:44:47',
                'updated_at' => '2025-01-23 11:56:49',
            ),
            78 => 
            array (
                'id' => 80,
                'name' => 'المركز',
                'service_group_id' => 1,
                'price' => 200000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:45:36',
                'updated_at' => '2025-01-23 12:57:29',
            ),
            79 => 
            array (
                'id' => 81,
                'name' => 'ارجاع فك',
                'service_group_id' => 16,
                'price' => 17000.0,
                'activate' => 0,
                'created_at' => '2024-12-19 18:49:28',
                'updated_at' => '2025-01-23 11:55:38',
            ),
            80 => 
            array (
                'id' => 82,
                'name' => 'زراعه',
                'service_group_id' => 14,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:22:27',
                'updated_at' => '2025-01-23 11:55:28',
            ),
            81 => 
            array (
                'id' => 83,
                'name' => 'رفع جيب',
                'service_group_id' => 10,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:22:56',
                'updated_at' => '2025-01-23 11:55:18',
            ),
            82 => 
            array (
                'id' => 84,
                'name' => 'ترقيع عظم ذاتي',
                'service_group_id' => 16,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:23:38',
                'updated_at' => '2025-01-23 11:55:13',
            ),
            83 => 
            array (
                'id' => 85,
                'name' => 'ترقيع عظم صناعي',
                'service_group_id' => 16,
                'price' => 2.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:24:03',
                'updated_at' => '2025-01-23 11:55:06',
            ),
            84 => 
            array (
                'id' => 86,
                'name' => 'ترقيع عظم صناعي وذاتي',
                'service_group_id' => 16,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:24:37',
                'updated_at' => '2025-01-23 11:55:01',
            ),
            85 => 
            array (
                'id' => 87,
                'name' => 'تقويم',
                'service_group_id' => 13,
                'price' => 700000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:25:01',
                'updated_at' => '2025-01-13 19:24:22',
            ),
            86 => 
            array (
                'id' => 88,
                'name' => 'تقويم اطغال',
                'service_group_id' => 13,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:25:18',
                'updated_at' => '2025-01-13 19:25:45',
            ),
            87 => 
            array (
                'id' => 89,
                'name' => 'شد تقويم شهري',
                'service_group_id' => 13,
                'price' => 47000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:25:40',
                'updated_at' => '2025-01-13 19:25:13',
            ),
            88 => 
            array (
                'id' => 90,
                'name' => 'فك تقويم',
                'service_group_id' => 13,
                'price' => 350000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:25:59',
                'updated_at' => '2025-01-13 19:26:38',
            ),
            89 => 
            array (
                'id' => 91,
                'name' => 'تغير لون لثه',
                'service_group_id' => 10,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:26:39',
                'updated_at' => '2025-01-14 16:46:38',
            ),
            90 => 
            array (
                'id' => 92,
                'name' => 'جلسة علاج جزور اخصائي',
                'service_group_id' => 17,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:27:37',
                'updated_at' => '2025-01-14 16:46:26',
            ),
            91 => 
            array (
                'id' => 93,
                'name' => 'اعاده علاج جزور اخصائي',
                'service_group_id' => 17,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:28:06',
                'updated_at' => '2025-01-14 16:46:17',
            ),
            92 => 
            array (
                'id' => 94,
                'name' => 'عمليه',
                'service_group_id' => 8,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:28:27',
                'updated_at' => '2025-01-14 16:45:20',
            ),
            93 => 
            array (
                'id' => 95,
                'name' => 'التخدير',
                'service_group_id' => 8,
                'price' => 150000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:28:38',
                'updated_at' => '2025-01-25 16:32:00',
            ),
            94 => 
            array (
                'id' => 96,
                'name' => 'علاج جزور جزئي',
                'service_group_id' => 12,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:29:31',
                'updated_at' => '2025-01-13 19:45:59',
            ),
            95 => 
            array (
                'id' => 97,
                'name' => 'كسر تقويم',
                'service_group_id' => 13,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:30:12',
                'updated_at' => '2025-01-13 19:26:00',
            ),
            96 => 
            array (
                'id' => 98,
                'name' => 'شد تقويم فك واحد',
                'service_group_id' => 13,
                'price' => 23500.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:30:50',
                'updated_at' => '2025-01-22 18:53:42',
            ),
            97 => 
            array (
                'id' => 99,
                'name' => 'عمليه صغري',
                'service_group_id' => 8,
                'price' => 0.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:42:15',
                'updated_at' => '2025-01-22 18:49:37',
            ),
            98 => 
            array (
                'id' => 100,
                'name' => 'عمليه كبيره',
                'service_group_id' => 8,
                'price' => 202000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:42:29',
                'updated_at' => '2025-01-13 19:44:52',
            ),
            99 => 
            array (
                'id' => 101,
                'name' => 'عمليه متوسطه',
                'service_group_id' => 8,
                'price' => 40000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:42:43',
                'updated_at' => '2025-01-14 16:44:59',
            ),
            100 => 
            array (
                'id' => 102,
                'name' => 'تبيض فك واحد',
                'service_group_id' => 10,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:43:21',
                'updated_at' => '2025-01-14 16:44:07',
            ),
            101 => 
            array (
                'id' => 103,
                'name' => 'تبيض',
                'service_group_id' => 10,
                'price' => 1.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:43:31',
                'updated_at' => '2025-01-12 21:22:19',
            ),
            102 => 
            array (
                'id' => 104,
                'name' => 'تركيب متحرك',
                'service_group_id' => 14,
                'price' => 70000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:45:31',
                'updated_at' => '2025-01-25 17:45:52',
            ),
            103 => 
            array (
                'id' => 105,
                'name' => 'تركيب متحرك فك واح',
                'service_group_id' => 14,
                'price' => 300000.0,
                'activate' => 0,
                'created_at' => '2024-12-22 13:46:05',
                'updated_at' => '2025-01-23 16:50:33',
            ),
            104 => 
            array (
                'id' => 107,
                'name' => 'تركيب متحرك جزئي',
                'service_group_id' => 14,
                'price' => 150000.0,
                'activate' => 0,
                'created_at' => '2025-01-23 16:53:16',
                'updated_at' => '2025-01-23 16:53:16',
            ),
            105 => 
            array (
                'id' => 108,
                'name' => 'تفريغ كيس لعابى',
                'service_group_id' => 16,
                'price' => 102000.0,
                'activate' => 0,
                'created_at' => '2025-01-25 18:52:11',
                'updated_at' => '2025-01-25 18:52:11',
            ),
            106 => 
            array (
                'id' => 109,
                'name' => 'تفريغ كيس لعابى',
                'service_group_id' => 16,
                'price' => 102000.0,
                'activate' => 0,
                'created_at' => '2025-01-25 18:52:15',
                'updated_at' => '2025-01-25 18:52:15',
            ),
        ));
        
        
    }
}