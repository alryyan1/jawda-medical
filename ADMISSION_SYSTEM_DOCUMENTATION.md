# نظام التنويم (Admission System) - دليل شامل

## نظرة عامة

نظام التنويم هو نظام متكامل لإدارة إقامة المرضى في المستشفى، يتضمن إدارة الأقسام والغرف والأسرّة، بالإضافة إلى تتبع البيانات الطبية والمالية لكل مريض مقيم.

## المكونات الرئيسية

### 1. البنية الأساسية

- **الأقسام (Wards)**: الوحدات الرئيسية في المستشفى
- **الغرف (Rooms)**: الوحدات الفرعية داخل كل قسم
- **الأسرّة (Beds)**: الوحدات الفردية داخل كل غرفة
- **التنويمات (Admissions)**: سجلات إقامة المرضى

### 2. أنواع الحجز (Booking Types)

يدعم النظام نوعين من الحجز:

#### أ. الحجز عن طريق السرير (Bed-based Booking)
- يتطلب تحديد سرير محدد
- السرير يصبح "مشغول" (occupied) تلقائياً
- مناسب للمرضى الذين يحتاجون سريراً محدداً

#### ب. الحجز عن طريق الغرفة (Room-based Booking)
- لا يتطلب تحديد سرير محدد
- الحجز يكون على مستوى الغرفة فقط
- مناسب للحالات التي لا تحتاج سريراً محدداً

## آلية احتساب مدة الإقامة

تم تطوير نظام ذكي لاحتساب مدة الإقامة بناءً على وقت الدخول:

### الفترة الصباحية (7:00 ص - 12:00 ظ)
- **النظام**: احتساب بنظام 24 ساعة
- **الطريقة**: يتم احتساب الساعات الفعلية ثم تحويلها إلى أيام
- **مثال**: 
  - دخول: 8:00 ص
  - خروج: 10:00 ص (اليوم التالي)
  - المدة: 26 ساعة = 1.08 يوم ≈ 2 يوم

### الفترة المسائية (1:00 ظ - 6:00 ص اليوم التالي)
- **النظام**: احتساب يوم كامل عند الوصول لـ 12:00 ظ
- **الطريقة**: 
  - إذا كان الدخول قبل 12:00 ظ، يتم احتساب المدة من 12:00 ظ
  - إذا كان الدخول بعد 12:00 ظ، يتم احتساب المدة من وقت الدخول الفعلي
- **مثال**:
  - دخول: 2:00 ظ
  - خروج: 6:00 ص (اليوم التالي)
  - المدة: يوم كامل (حتى لو كانت المدة الفعلية 16 ساعة فقط)

### الفترة الافتراضية (6:00 ص - 7:00 ص)
- يتم احتساب المدة بالطريقة التقليدية (عدد الأيام + 1)

## ملف إقامة المريض (Patient File)

تم إضافة نظام شامل لإدارة ملف إقامة المريض يتضمن ثلاثة أقسام رئيسية:

### 1. بيانات العلاج (Treatment Data)

**الجدول**: `admission_treatments`

**الحقول**:
- `treatment_plan`: خطة العلاج
- `treatment_details`: تفاصيل العلاج
- `treatment_date`: تاريخ العلاج
- `treatment_time`: وقت العلاج
- `notes`: ملاحظات إضافية
- `user_id`: المستخدم الذي أضاف السجل

**الاستخدام**: لتسجيل وتتبع الخطط العلاجية المقررة للمريض

### 2. الجرعات (Doses)

**الجدول**: `admission_doses`

**الحقول**:
- `medication_name`: اسم الدواء (مطلوب)
- `dosage`: الجرعة
- `frequency`: التكرار (مثل: كل 8 ساعات)
- `route`: طريقة الإعطاء (فموي، وريدي، عضلي، إلخ)
- `start_date`: تاريخ البدء
- `end_date`: تاريخ الانتهاء
- `instructions`: تعليمات خاصة
- `doctor_id`: الطبيب الموصي
- `is_active`: حالة الجرعة (نشط/غير نشط)

**الاستخدام**: لتسجيل وتتبع الأدوية والجرعات المقررة للمريض

### 3. المهام التمريضية (Nursing Assignments)

**الجدول**: `admission_nursing_assignments`

**الحقول**:
- `assignment_description`: وصف المهمة (مطلوب)
- `priority`: الأولوية (منخفضة، متوسطة، عالية)
- `status`: الحالة (معلقة، قيد التنفيذ، مكتملة، ملغاة)
- `due_date`: تاريخ الاستحقاق
- `due_time`: وقت الاستحقاق
- `completed_date`: تاريخ الإنجاز
- `completed_time`: وقت الإنجاز
- `user_id`: الممرض/الممرضة المسؤول
- `assigned_by_user_id`: المستخدم الذي كلف بالمهمة
- `notes`: ملاحظات
- `completion_notes`: ملاحظات الإنجاز

**الاستخدام**: لتسجيل وتتبع المهام التمريضية المطلوبة للمريض

## التغييرات الأخيرة (Recent Changes)

### 1. إضافة نوع الحجز (Booking Type)

**التاريخ**: فبراير 2026

**التغييرات**:
- إضافة حقل `booking_type` إلى جدول `admissions`
- جعل حقل `bed_id` اختياري (nullable)
- تحديث validation rules في Controller
- تحديث واجهة المستخدم لدعم نوعين من الحجز

**الملفات المعدلة**:
- `database/migrations/2026_02_02_235325_add_booking_type_to_admissions_table.php`
- `app/Models/Admission.php`
- `app/Http/Controllers/Api/AdmissionController.php`
- `app/Http/Resources/AdmissionResource.php`
- `src/pages/admissions/AdmissionFormPage.tsx`
- `src/components/admissions/tabs/AdmissionOverviewTab.tsx`

### 2. تحسين آلية احتساب مدة الإقامة

**التاريخ**: فبراير 2026

**التغييرات**:
- تطوير دالة `calculateStayDays()` في Model `Admission`
- تطبيق منطق احتساب مختلف حسب وقت الدخول
- دعم الفترات الصباحية والمسائية

**الملفات المعدلة**:
- `app/Models/Admission.php`

### 3. إضافة ملف إقامة المريض

**التاريخ**: فبراير 2026

**التغييرات**:
- إنشاء ثلاثة جداول جديدة:
  - `admission_treatments`
  - `admission_doses`
  - `admission_nursing_assignments`
- إنشاء Models وControllers وResources جديدة
- إضافة API endpoints جديدة
- إنشاء واجهة مستخدم شاملة في Frontend

**الملفات الجديدة**:
- `database/migrations/2026_02_02_235347_create_admission_treatments_table.php`
- `database/migrations/2026_02_02_235355_create_admission_doses_table.php`
- `database/migrations/2026_02_02_235403_create_admission_nursing_assignments_table.php`
- `app/Models/AdmissionTreatment.php`
- `app/Models/AdmissionDose.php`
- `app/Models/AdmissionNursingAssignment.php`
- `app/Http/Controllers/Api/AdmissionTreatmentController.php`
- `app/Http/Controllers/Api/AdmissionDoseController.php`
- `app/Http/Controllers/Api/AdmissionNursingAssignmentController.php`
- `src/components/admissions/tabs/AdmissionPatientFileTab.tsx`
- `src/services/admissionTreatmentService.ts`
- `src/services/admissionDoseService.ts`
- `src/services/admissionNursingAssignmentService.ts`

## API Endpoints

### Admissions

```
GET    /api/admissions                    # قائمة التنويمات
POST   /api/admissions                    # إنشاء تنويم جديد
GET    /api/admissions/{id}               # تفاصيل تنويم
PUT    /api/admissions/{id}               # تحديث تنويم
PUT    /api/admissions/{id}/discharge     # إخراج مريض
PUT    /api/admissions/{id}/transfer      # نقل مريض
GET    /api/admissions/active             # التنويمات النشطة
```

### Treatments

```
GET    /api/admissions/{id}/treatments              # قائمة بيانات العلاج
POST   /api/admissions/{id}/treatments              # إضافة بيانات علاج
GET    /api/admissions/{id}/treatments/{treatment}  # تفاصيل بيانات علاج
PUT    /api/admissions/{id}/treatments/{treatment}  # تحديث بيانات علاج
DELETE /api/admissions/{id}/treatments/{treatment}  # حذف بيانات علاج
```

### Doses

```
GET    /api/admissions/{id}/doses           # قائمة الجرعات
POST   /api/admissions/{id}/doses           # إضافة جرعة
GET    /api/admissions/{id}/doses/{dose}    # تفاصيل جرعة
PUT    /api/admissions/{id}/doses/{dose}    # تحديث جرعة
DELETE /api/admissions/{id}/doses/{dose}    # حذف جرعة
```

### Nursing Assignments

```
GET    /api/admissions/{id}/nursing-assignments              # قائمة المهام
POST   /api/admissions/{id}/nursing-assignments              # إضافة مهمة
GET    /api/admissions/{id}/nursing-assignments/{assignment} # تفاصيل مهمة
PUT    /api/admissions/{id}/nursing-assignments/{assignment} # تحديث مهمة
DELETE /api/admissions/{id}/nursing-assignments/{assignment} # حذف مهمة
```

## كيفية الاستخدام

### إنشاء تنويم جديد

1. انتقل إلى صفحة "إضافة تنويم جديد"
2. اختر المريض
3. اختر القسم والغرفة
4. **اختر نوع الحجز**:
   - إذا اخترت "حجز عن طريق السرير": يجب اختيار سرير محدد
   - إذا اخترت "حجز عن طريق الغرفة": لا حاجة لاختيار سرير
5. أدخل بيانات التنويم (التاريخ، الوقت، التشخيص، إلخ)
6. احفظ التنويم

### إدارة ملف الإقامة

1. **الوصول إلى صفحة تفاصيل التنويم**:
   - من قائمة التنويمات (`/admissions/list`)، اضغط على أي تنويم أو اضغط على زر "عرض"
   - أو انتقل مباشرة إلى `/admissions/{id}` حيث `{id}` هو رقم التنويم

2. **الوصول إلى تبويب "ملف الإقامة"**:
   - في صفحة تفاصيل التنويم، ستجد عدة تبويبات في الأعلى:
     - نظرة عامة
     - العلامات الحيوية
     - الخدمات الطبية
     - كشف الحساب
     - العمليات
     - الوثائق
     - الفحوصات المختبرية
     - **ملف الإقامة** ← هذا هو التبويب الجديد
   - اضغط على تبويب "ملف الإقامة"

3. **اختر القسم المطلوب** داخل تبويب "ملف الإقامة":
   - **بيانات العلاج**: لإضافة وتعديل الخطط العلاجية
   - **الجرعات**: لإدارة الأدوية والجرعات
   - **المهام التمريضية**: لتتبع المهام التمريضية

### إضافة بيانات علاج

1. في تبويب "ملف الإقامة"، اختر "بيانات العلاج"
2. اضغط على "إضافة بيانات علاج"
3. أدخل:
   - تاريخ ووقت العلاج
   - خطة العلاج
   - تفاصيل العلاج
   - ملاحظات (اختياري)
4. احفظ

### إضافة جرعة

1. في تبويب "ملف الإقامة"، اختر "الجرعات"
2. اضغط على "إضافة جرعة"
3. أدخل:
   - اسم الدواء (مطلوب)
   - الجرعة والتكرار
   - طريقة الإعطاء
   - تاريخ البدء والانتهاء
   - الطبيب الموصي (اختياري)
   - تعليمات خاصة (اختياري)
4. احفظ

### إضافة مهمة تمريضية

1. في تبويب "ملف الإقامة"، اختر "المهام التمريضية"
2. اضغط على "إضافة مهمة تمريضية"
3. أدخل:
   - وصف المهمة (مطلوب)
   - الأولوية (منخفضة/متوسطة/عالية)
   - الحالة (معلقة/قيد التنفيذ/مكتملة/ملغاة)
   - تاريخ ووقت الاستحقاق
   - ملاحظات (اختياري)
4. احفظ

## قاعدة البيانات

### جدول admissions

```sql
- id (bigint, primary key)
- patient_id (bigint, foreign key)
- ward_id (bigint, foreign key)
- room_id (bigint, foreign key)
- bed_id (bigint, foreign key, nullable) -- NEW: أصبح nullable
- booking_type (enum: 'bed', 'room', default: 'bed') -- NEW
- admission_date (date)
- admission_time (time, nullable)
- discharge_date (date, nullable)
- discharge_time (time, nullable)
- status (enum: 'admitted', 'discharged', 'transferred')
- ... (حقول أخرى)
```

### جدول admission_treatments

```sql
- id (bigint, primary key)
- admission_id (bigint, foreign key)
- treatment_plan (text, nullable)
- treatment_details (text, nullable)
- treatment_date (date, nullable)
- treatment_time (time, nullable)
- notes (text, nullable)
- user_id (bigint, foreign key)
- created_at, updated_at
```

### جدول admission_doses

```sql
- id (bigint, primary key)
- admission_id (bigint, foreign key)
- medication_name (string, required)
- dosage (string, nullable)
- frequency (string, nullable)
- route (string, nullable)
- start_date (date, nullable)
- end_date (date, nullable)
- instructions (text, nullable)
- notes (text, nullable)
- doctor_id (bigint, foreign key, nullable)
- user_id (bigint, foreign key)
- is_active (boolean, default: true)
- created_at, updated_at
```

### جدول admission_nursing_assignments

```sql
- id (bigint, primary key)
- admission_id (bigint, foreign key)
- user_id (bigint, foreign key)
- assignment_description (text, required)
- priority (enum: 'low', 'medium', 'high', default: 'medium')
- status (enum: 'pending', 'in_progress', 'completed', 'cancelled', default: 'pending')
- due_date (date, nullable)
- due_time (time, nullable)
- completed_date (date, nullable)
- completed_time (time, nullable)
- notes (text, nullable)
- completion_notes (text, nullable)
- assigned_by_user_id (bigint, foreign key, nullable)
- created_at, updated_at
```

## ملاحظات مهمة

1. **البيانات الموجودة**: عند تطبيق migration، سيتم تعيين `booking_type` تلقائياً إلى `'bed'` لجميع السجلات الموجودة.

2. **التحقق من الصحة**: عند إنشاء تنويم بنوع حجز "سرير"، يجب التأكد من أن السرير متاح.

3. **احتساب الأيام**: يتم احتساب مدة الإقامة تلقائياً عند الوصول إلى خاصية `days_admitted` في Model `Admission`.

4. **الأمان**: جميع API endpoints محمية بـ authentication middleware.

5. **الصلاحيات**: تأكد من أن المستخدم لديه الصلاحيات المناسبة للوصول إلى هذه الميزات.

## الخطوات التالية (Future Enhancements)

1. إضافة تقارير شاملة لملف الإقامة
2. إضافة تنبيهات للمهام التمريضية المستحقة
3. إضافة إشعارات للجرعات المقررة
4. إضافة تصدير PDF لملف الإقامة الكامل
5. إضافة إحصائيات وتحليلات للإقامات

## الدعم الفني

لأي استفسارات أو مشاكل تقنية، يرجى التواصل مع فريق التطوير.

---

**آخر تحديث**: فبراير 2026
