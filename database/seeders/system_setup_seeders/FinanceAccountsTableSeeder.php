<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FinanceAccountsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('finance_accounts')->delete();
        
        \DB::table('finance_accounts')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'الاصول المتداوله',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:24:12',
                'updated_at' => '2025-02-04 09:24:12',
                'code' => '1',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'الصندوق',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:26:08',
                'updated_at' => '2025-02-04 09:26:08',
                'code' => '1.1',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'البنك',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:29:06',
                'updated_at' => '2025-02-04 09:29:06',
                'code' => '1.2',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'مديونون',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:29:27',
                'updated_at' => '2025-02-04 09:29:27',
                'code' => '1.3',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'الاصول الثابته',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:30:03',
                'updated_at' => '2025-02-04 09:33:50',
                'code' => '2',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'ايجار مقدم',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:30:19',
                'updated_at' => '2025-02-04 09:30:19',
                'code' => '1.5',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'الخصوم المتداوله',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:34:09',
                'updated_at' => '2025-02-04 09:34:09',
                'code' => '3',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'حقوق الملكيه',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:34:20',
                'updated_at' => '2025-02-04 09:34:20',
                'code' => '4',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'اثاث',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:34:45',
                'updated_at' => '2025-02-04 09:34:45',
                'code' => '2.1',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'سيارات',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:34:57',
                'updated_at' => '2025-02-04 09:34:57',
                'code' => '2.2',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'دائنون',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:36:25',
                'updated_at' => '2025-02-04 09:36:25',
                'code' => '3.1',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'ايرادات محصله مقدما',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:36:43',
                'updated_at' => '2025-02-04 09:36:43',
                'code' => '3.2',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'راس المال',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 09:37:12',
                'updated_at' => '2025-02-04 09:37:12',
                'code' => '4.1',
            ),
            13 => 
            array (
                'id' => 15,
                'name' => 'ايراد ايجار مباني',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-04 20:49:57',
                'updated_at' => '2025-02-04 20:49:57',
                'code' => '4.2',
            ),
            14 => 
            array (
                'id' => 17,
                'name' => 'مصروف ايجار',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:19:18',
                'updated_at' => '2025-02-05 10:19:18',
                'code' => '1.6',
            ),
            15 => 
            array (
                'id' => 19,
                'name' => 'شركات التامين',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:20:56',
                'updated_at' => '2025-02-05 10:20:56',
                'code' => '1.1.3.1',
            ),
            16 => 
            array (
                'id' => 20,
                'name' => 'المتخصصه',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:21:20',
                'updated_at' => '2025-02-05 10:21:20',
                'code' => '1.1.3.1.1',
            ),
            17 => 
            array (
                'id' => 21,
                'name' => 'برايم هيلث',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:21:40',
                'updated_at' => '2025-02-05 10:21:40',
                'code' => '1.1.3.1.2',
            ),
            18 => 
            array (
                'id' => 22,
                'name' => 'ديوان الزكاه',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:22:03',
                'updated_at' => '2025-02-05 10:22:03',
                'code' => '1.1.3.1.3',
            ),
            19 => 
            array (
                'id' => 23,
                'name' => 'الجمارك',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:22:51',
                'updated_at' => '2025-02-05 10:22:51',
                'code' => '1.1.3.1.4',
            ),
            20 => 
            array (
                'id' => 24,
                'name' => 'التعاونيه',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:23:10',
                'updated_at' => '2025-02-05 10:23:10',
                'code' => '1.1.3.1.5',
            ),
            21 => 
            array (
                'id' => 25,
                'name' => 'الكهرباء',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:24:38',
                'updated_at' => '2025-02-05 10:24:38',
                'code' => '1.1.3.1.6',
            ),
            22 => 
            array (
                'id' => 26,
                'name' => 'بترولاينز لخدمات البترول',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:26:27',
                'updated_at' => '2025-02-05 10:26:27',
                'code' => '1.1.3.1.7',
            ),
            23 => 
            array (
                'id' => 27,
                'name' => 'السلطة القضائية',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:26:51',
                'updated_at' => '2025-02-05 10:26:51',
                'code' => '1.1.3.1.8',
            ),
            24 => 
            array (
                'id' => 28,
                'name' => 'سوداتيل',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:27:08',
                'updated_at' => '2025-02-05 10:27:08',
                'code' => '1.1.3.1.9',
            ),
            25 => 
            array (
                'id' => 29,
                'name' => 'بنك امدرمان الوطني',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:27:35',
                'updated_at' => '2025-02-05 10:27:35',
                'code' => '1.1.3.1.10',
            ),
            26 => 
            array (
                'id' => 30,
                'name' => 'البركة للتأمين',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:27:52',
                'updated_at' => '2025-02-05 10:27:52',
                'code' => '1.1.3.1.11',
            ),
            27 => 
            array (
                'id' => 31,
                'name' => 'مصفاة الخرطوم',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:28:21',
                'updated_at' => '2025-02-05 10:28:21',
                'code' => '1.1.3.1.12',
            ),
            28 => 
            array (
                'id' => 32,
                'name' => 'وزراة الطاقة والنفط',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:28:59',
                'updated_at' => '2025-02-05 10:28:59',
                'code' => '1.1.3.1.13',
            ),
            29 => 
            array (
                'id' => 33,
                'name' => 'الحزمة الاضافية',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:29:26',
                'updated_at' => '2025-02-05 10:29:26',
                'code' => '1.1.3.1.14',
            ),
            30 => 
            array (
                'id' => 34,
                'name' => 'شركه مندري',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => 'تحاليل واجهزه مختبر',
                'created_at' => '2025-02-05 10:30:17',
                'updated_at' => '2025-02-05 10:30:17',
                'code' => '1.3.1.1',
            ),
            31 => 
            array (
                'id' => 35,
                'name' => 'الاسماعيلي',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => 'مستهلكات',
                'created_at' => '2025-02-05 10:32:38',
                'updated_at' => '2025-02-05 10:32:38',
                'code' => '1.3.3.2',
            ),
            32 => 
            array (
                'id' => 36,
                'name' => 'اعمال تست',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => 'مستهلكات مختبر',
                'created_at' => '2025-02-05 10:33:58',
                'updated_at' => '2025-02-05 10:33:58',
                'code' => '1.3.3.4',
            ),
            33 => 
            array (
                'id' => 37,
                'name' => 'مدثر',
                'account_category_id' => 0,
                'debit' => 0,
            'description' => 'مليون (البيت الفيه التحصيل والاشعه)',
                'created_at' => '2025-02-05 10:38:21',
                'updated_at' => '2025-02-05 10:38:21',
                'code' => '1.1.4.1',
            ),
            34 => 
            array (
                'id' => 39,
                'name' => 'انور',
                'account_category_id' => 0,
                'debit' => 0,
            'description' => 'العماره (الرومي)',
                'created_at' => '2025-02-05 10:42:05',
                'updated_at' => '2025-02-05 10:42:05',
                'code' => '1.1.4.1.3',
            ),
            35 => 
            array (
                'id' => 41,
                'name' => 'بيت خليل',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:43:00',
                'updated_at' => '2025-02-05 10:43:00',
                'code' => '1.1.4.1.2',
            ),
            36 => 
            array (
                'id' => 42,
                'name' => 'ايجارات خارجيه',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:44:28',
                'updated_at' => '2025-02-05 10:44:28',
                'code' => '1.1.6',
            ),
            37 => 
            array (
                'id' => 43,
            'name' => 'عمر الصديق (الكرونا)',
                'account_category_id' => 0,
                'debit' => 0,
                'description' => NULL,
                'created_at' => '2025-02-05 10:45:33',
                'updated_at' => '2025-02-05 10:45:33',
                'code' => '1.1.6.1',
            ),
            38 => 
            array (
                'id' => 44,
                'name' => '( قصي المامون (المستشفي الشمالي',
                    'account_category_id' => 0,
                    'debit' => 0,
                    'description' => NULL,
                    'created_at' => '2025-02-05 10:46:56',
                    'updated_at' => '2025-02-05 10:46:56',
                    'code' => '1.1.6.2',
                ),
                39 => 
                array (
                    'id' => 45,
                    'name' => '( ابراهيم فضل الله (الكافتيريا',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-05 10:48:04',
                        'updated_at' => '2025-02-05 10:48:04',
                        'code' => '1.1.6.3',
                    ),
                    40 => 
                    array (
                        'id' => 46,
                        'name' => 'مصروفات عموميه واداريه',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-05 10:50:45',
                        'updated_at' => '2025-02-05 10:50:45',
                        'code' => '1.1.7',
                    ),
                    41 => 
                    array (
                        'id' => 47,
                        'name' => 'مصروف ايجارات',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-05 10:52:52',
                        'updated_at' => '2025-02-05 10:52:52',
                        'code' => '1.1.7.1',
                    ),
                    42 => 
                    array (
                        'id' => 48,
                        'name' => 'مرتبات الاداره',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 10:50:31',
                        'updated_at' => '2025-02-06 10:50:31',
                        'code' => '1.6.2',
                    ),
                    43 => 
                    array (
                        'id' => 49,
                        'name' => 'فطور',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 10:51:30',
                        'updated_at' => '2025-02-06 10:51:30',
                        'code' => '1.6.3',
                    ),
                    44 => 
                    array (
                        'id' => 50,
                        'name' => 'مساهمات',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 10:51:49',
                        'updated_at' => '2025-02-06 10:51:49',
                        'code' => '1.6.4',
                    ),
                    45 => 
                    array (
                        'id' => 51,
                        'name' => 'مسحوبات الشركاء',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 10:55:29',
                        'updated_at' => '2025-02-06 10:55:29',
                        'code' => '1.4.3',
                    ),
                    46 => 
                    array (
                        'id' => 52,
                        'name' => 'د وليد',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 10:55:45',
                        'updated_at' => '2025-02-06 10:55:45',
                        'code' => '1.4.3.1',
                    ),
                    47 => 
                    array (
                        'id' => 53,
                        'name' => 'د حافظ',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 10:55:56',
                        'updated_at' => '2025-02-06 10:55:56',
                        'code' => '1.4.3.2',
                    ),
                    48 => 
                    array (
                        'id' => 54,
                        'name' => 'ايرادات',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 11:09:51',
                        'updated_at' => '2025-02-06 11:09:51',
                        'code' => '1.4.4',
                    ),
                    49 => 
                    array (
                        'id' => 55,
                        'name' => 'بنك الخرطوم',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 11:52:32',
                        'updated_at' => '2025-02-06 11:52:32',
                        'code' => '1.1.2.1',
                    ),
                    50 => 
                    array (
                        'id' => 56,
                        'name' => 'بنك البركه',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 11:52:53',
                        'updated_at' => '2025-02-06 11:52:53',
                        'code' => '1.1.2.2',
                    ),
                    51 => 
                    array (
                        'id' => 57,
                        'name' => 'بنك النيل',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => NULL,
                        'created_at' => '2025-02-06 11:53:31',
                        'updated_at' => '2025-02-06 11:53:31',
                        'code' => '1.1.2.3',
                    ),
                    52 => 
                    array (
                        'id' => 58,
                        'name' => 'الات',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => 'الات',
                        'created_at' => '2025-02-09 10:01:02',
                        'updated_at' => '2025-02-09 10:01:02',
                        'code' => '1.2.3',
                    ),
                    53 => 
                    array (
                        'id' => 60,
                        'name' => 'مواد',
                        'account_category_id' => 0,
                        'debit' => 0,
                        'description' => 'مواد',
                        'created_at' => '2025-02-09 10:05:19',
                        'updated_at' => '2025-02-09 10:05:19',
                        'code' => '1.1.8',
                    ),
                ));
        
        
    }
}