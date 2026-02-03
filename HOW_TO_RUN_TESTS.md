# كيفية تشغيل اختبارات نظام التنويم

## 🚀 البدء السريع

### الاختبار اليدوي السريع

```bash
cd c:\xampp\htdocs\jawda-medical
php test-admission-system.php
```

هذا الاختبار يتحقق من:
- ✅ الاتصال بقاعدة البيانات
- ✅ وجود الجداول المطلوبة
- ✅ احتساب مدة الإقامة
- ✅ حساب الرصيد
- ✅ احتساب رسوم الإقامة

---

## 🧪 اختبارات PHPUnit

### المتطلبات

- PHP 8.1+
- PHPUnit (مثبت تلقائياً مع Laravel)
- قاعدة بيانات للاختبار

### تشغيل جميع الاختبارات

```bash
php artisan test
```

### تشغيل اختبارات نظام التنويم فقط

```bash
php artisan test --filter AdmissionSystemTest
```

### تشغيل اختبار محدد

```bash
# اختبار إنشاء تنويم بحجز السرير
php artisan test --filter test_create_bed_based_admission

# اختبار احتساب مدة الإقامة
php artisan test --filter test_stay_days_calculation

# اختبار كشف الحساب
php artisan test --filter test_ledger_balance_calculation
```

---

## 📋 قائمة الاختبارات المتوفرة

### اختبارات الإنشاء

- `test_create_bed_based_admission` - إنشاء تنويم بحجز السرير
- `test_create_room_based_admission` - إنشاء تنويم بحجز الغرفة
- `test_bed_id_required_for_bed_booking` - التحقق من ضرورة السرير

### اختبارات احتساب مدة الإقامة

- `test_stay_days_calculation_morning_period` - الفترة الصباحية
- `test_stay_days_calculation_evening_period` - الفترة المسائية
- `test_stay_days_calculation_default_period` - الفترة الافتراضية

### اختبارات كشف الحساب

- `test_add_debit_transaction` - إضافة رسوم
- `test_add_credit_transaction` - إضافة دفعة
- `test_ledger_balance_calculation` - حساب الرصيد
- `test_room_charges_calculation` - احتساب رسوم الإقامة

### اختبارات العمليات

- `test_transfer_patient` - نقل المريض
- `test_discharge_patient` - إخراج المريض
- `test_cannot_add_transaction_for_discharged_patient` - منع المعاملات للمريض المخرج

### اختبارات أخرى

- `test_room_fully_occupied_status` - حالة الغرفة المحجوزة بالكامل
- `test_admission_list_filtering` - فلترة قائمة التنويمات

---

## 🔧 إعداد بيئة الاختبار

### 1. إعداد قاعدة البيانات

أنشئ ملف `.env.testing`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jawda_medical_test
DB_USERNAME=root
DB_PASSWORD=
```

### 2. تشغيل Migrations

```bash
php artisan migrate --env=testing
```

### 3. تشغيل Seeders (اختياري)

```bash
php artisan db:seed --env=testing
```

---

## 📊 عرض النتائج

### عرض النتائج بالتفصيل

```bash
php artisan test --filter AdmissionSystemTest --verbose
```

### حفظ النتائج في ملف

```bash
php artisan test --filter AdmissionSystemTest > test-results.txt
```

---

## 🐛 حل المشاكل

### المشكلة: Database connection failed

**الحل**:
1. تأكد من إعدادات قاعدة البيانات في `.env`
2. تأكد من أن MySQL/MariaDB يعمل
3. تأكد من وجود قاعدة البيانات

### المشكلة: Table doesn't exist

**الحل**:
```bash
php artisan migrate
```

### المشكلة: No test data

**الحل**:
```bash
php artisan db:seed
```

### المشكلة: PHPUnit not found

**الحل**:
```bash
composer install
```

---

## 📝 ملاحظات

- ✅ جميع الاختبارات تستخدم قاعدة بيانات منفصلة للاختبار
- ✅ البيانات التجريبية تُنشأ تلقائياً في كل اختبار
- ✅ البيانات تُحذف تلقائياً بعد كل اختبار (RefreshDatabase)
- ✅ يمكنك تشغيل الاختبارات بدون التأثير على البيانات الحقيقية

---

## 📞 الدعم

إذا واجهت أي مشكلة في تشغيل الاختبارات:
- راجع ملف `ADMISSION_SYSTEM_TEST_RESULTS.md` لمعرفة النتائج
- راجع ملف `ADMISSION_SYSTEM_TESTING_GUIDE.md` للدليل الشامل

---

**آخر تحديث**: فبراير 2026
