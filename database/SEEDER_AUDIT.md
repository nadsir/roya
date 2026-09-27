# گزارش بازسازی داده‌های پایه CAR — 2026-09-17

اتصال مؤثر Laravel: MySQL، میزبان `127.0.0.1`، پورت `3500`، دیتابیس `car`؛ تنظیمات cache نشده بود. هیچ مقدار رمز اتصال در این گزارش ثبت نشده است.

تمام بررسی‌های داده Local در تراکنش `READ ONLY` انجام شدند و با rollback پایان یافتند. هیچ Migration یا Seeder روی Local اجرا نشد. هیچ Migration، مدل یا جدول تغییر نکرد. تغییرات قبلی کاربر خارج از database دست‌نخورده ماندند. git add، commit و push انجام نشد.

## A، B، C — جدول‌ها و پوشش اولیه

این فهرست فقط متعلق به دیتابیس `car` است. همه جدول‌های دارای داده در آن آمده‌اند.

| جدول | تعداد Local | پوشش پیش از تغییر | تصمیم |
|---|---:|---|---|
| categories | 46 | CategorySeeder، کامل | بدون تغییر |
| attributes | 16 | AttributeSeeder، کامل | بدون تغییر |
| attribute_values | 47 | AttributeValueSeeder، کامل | بدون تغییر |
| category_attributes | 26 | CategoryAttributeSeeder، کامل | بدون تغییر |
| products | 25 | ProductSeeder، کامل | بدون تغییر |
| product_categories | 25 | ProductSeeder، کامل | بدون تغییر |
| product_attribute_values | 117 | ProductSeeder، کامل | بدون تغییر |
| product_custom_attribute_values | 69 | ProductSeeder، کامل | بدون تغییر |
| product_variants | 4 | ProductVariantSeeder، کامل | بدون تغییر |
| variant_attribute_values | 6 | ProductVariantSeeder، کامل | بدون تغییر |
| product_images | 2 | ProductImageSeeder وجود داشت ولی خالی بود | تصاویر نامرتبط عمداً منتقل نشدند |
| vehicle_brands | 1 | ندارد | VehicleReferenceSeeder |
| vehicle_models | 1 | ندارد | VehicleReferenceSeeder |
| vehicle_generations | 1 | ندارد | VehicleReferenceSeeder |
| vehicle_trims | 1 | ندارد | VehicleReferenceSeeder |
| vehicle_engines | 1 | ندارد | VehicleReferenceSeeder |
| product_vehicle_compat | 1 | ندارد | ProductVehicleCompatibilitySeeder |
| users | 2 | UserSeeder فقط حساب مدیر ثابت می‌ساخت | اصلاح provisioning؛ کاربران Local کپی نشدند |
| orders | 1 | ندارد | داده تراکنشی؛ مستثنا |
| order_items | 1 | ندارد | داده تراکنشی؛ مستثنا |
| wishlist_items | 1 | ندارد | داده شخصی؛ مستثنا |
| personal_access_tokens | 1 | ندارد | حساس؛ مستثنا |
| sessions | 1 | ندارد | حساس؛ مستثنا |
| cache | 6 | ندارد | داده موقت؛ مستثنا |
| migrations | 30 | به‌وسیله Laravel مدیریت می‌شود | Seeder نیاز ندارد |

جدول‌های موجود ولی خالی و بدون Seeder: `cache_locks`، `failed_jobs`، `jobs`، `job_batches`، `password_reset_tokens` و `phone_verifications`.

جدول `vehicles` در Local و Migrationها وجود ندارد؛ خودرو به پنج جدول مرجع تفکیک شده است. جدول `payment_attempts` هنوز در Local وجود ندارد. این دو Migration موجود هنوز روی Local اعمال نشده‌اند و در این کار نیز اجرا نشدند:

- `2026_09_16_000000_add_payment_and_cancellation_to_orders_table`
- `2026_09_16_100000_create_payment_attempts_table`

## D، H — Seederهای جدید و داده‌هایشان

`VehicleReferenceSeeder` زنجیره زیر را در یک تراکنش ایجاد می‌کند. lookup با slug یکتا است؛ FKها از مدل ساخته‌شده/موجود خوانده می‌شوند و هیچ ID ثابتی استفاده نمی‌شود. `firstOrCreate` اجرای مجدد را بدون duplicate ممکن می‌کند و اصلاحات بعدی داده‌های موجود را حفظ می‌کند.

| جدول | slug | مقادیر اصلی Local |
|---|---|---|
| vehicle_brands | بنز | نام: بنز |
| vehicle_models | می باخ | نام: می باخ، برند: بنز |
| vehicle_generations | نسل 4 | نام: 4، سال شروع: 1990، پایان: 1991 |
| vehicle_trims | تیپ3 | نام: تیپ3 |
| vehicle_engines | سیبل | نام: سیل، displacement: 100.0، fuel_type: للب، horsepower: 1000 |

همه این رکوردها در Local فعال بودند. مقادیر فنی موتور ظاهراً آزمایشی‌اند؛ Seeder آن‌ها را عین Local نگه می‌دارد و تأیید فنی آن‌ها محسوب نمی‌شود. پیش از انتشار روی Server باید صحت این کاتالوگ بررسی شود؛ در این کار مقدار حدسی جایگزین نشده است.

`ProductVehicleCompatibilitySeeder` رابطه موجود بین محصول با slug برابر `air-filter-peugeot-pars` و موتور با slug برابر `سیبل` را ایجاد می‌کند. `firstOrFail` نبود dependency را آشکار می‌کند و `syncWithoutDetaching` روابط دیگر را حذف نمی‌کند. این ارتباط فیلتر پژو با زنجیره برند بنز نیز فقط بازسازی Local است، نه تأیید سازگاری واقعی قطعه.

## E — Seeder موجود اصلاح‌شده

فقط `UserSeeder` اصلاح شد؛ ایمیل/نام شخصی ثابت و رمز پیش‌فرض حذف شدند. حساب جدید تنها با `CAR_ADMIN_EMAIL` معتبر و `CAR_ADMIN_PASSWORD` حداقل ۱۲ کاراکتری ساخته می‌شود. `CAR_ADMIN_NAME` اختیاری است. بدون ایمیل، ساخت مدیر با پیام مشخص رد می‌شود ولی کاتالوگ seed می‌شود. با ایمیل جدید و رمز نامعتبر، Seeder خطا می‌دهد.

حساب موجود بر اساس ایمیل حفظ می‌شود: رمز، نقش، نام، فعال‌بودن و وضعیت تأیید آن تغییر نمی‌کند؛ حساب مشتری موجود به مدیر تبدیل نمی‌شود. برای اجرای Server، متغیرها باید هنگام اجرای فرمان در دسترس process باشند؛ به‌خصوص با config cache، آن‌ها را در محیط اجرای فرمان تنظیم کنید. هیچ credential واقعی در مخزن قرار نگرفت.

Seederهای دیگر برای پوشش داده نیازی به بازنویسی نداشتند. رفتار قبلی `updateOrCreate` و `sync` آن‌ها حفظ شده است؛ اجرای مجدد آن‌ها ممکن است مقادیر کاتالوگ، قیمت، موجودی و روابط تحت مدیریتشان را به مقادیر fixture برگرداند. تست عدم duplicate به معنای عدم بازنویسی تمام داده‌های تجاری نیست. هدف این کار بازسازی یک Server تازه است.

## F — فایل‌های جدید

- `database/seeders/VehicleReferenceSeeder.php`
- `database/seeders/ProductVehicleCompatibilitySeeder.php`
- `database/tests/verify-seeders.php`
- `database/SEEDER_AUDIT.md`

فایل‌های ویرایش‌شده: `database/seeders/UserSeeder.php` و `database/seeders/DatabaseSeeder.php`.

## G — ترتیب DatabaseSeeder

ترتیب قبلی حفظ شد: User، Category، Attribute، AttributeValue، CategoryAttribute، Product، ProductVariant و ProductImage. سپس `VehicleReferenceSeeder` و در انتها `ProductVehicleCompatibilitySeeder` اضافه شدند. در نتیجه محصول و موتور پیش از ثبت رابطه موجودند.

## I — داده‌های عمداً مستثناشده

کاربران واقعی، password/hash واقعی، mobile/email شخصی، token، session، OTP، cache، سفارش، اقلام سفارش و علاقه‌مندی‌ها به fixture تبدیل نشدند. از جدول‌های شخصی فقط تعداد/ساختار خوانده شد، نه محتوای رکوردها.

دو رکورد `product_images` هر دو متعلق به `air-filter-peugeot-pars` هستند، اما فایل‌های WebP آن‌ها SHA-256 یکسان دارند و بررسی بصری نشان داد تصویر یک کلاه‌اند. فایل‌ها در Local موجودند، ولی داده معتبر تصویر قطعه خودرو نیستند؛ هیچ مسیر شکسته، تصویر نامرتبط یا تصویر ساختگی به Seeder اضافه نشد. `ProductImageSeeder` عمداً بدون تغییر و خالی باقی ماند. تصاویر معتبر آینده باید همراه فایل واقعی قابل انتشار اضافه شوند؛ صرف رکورد دیتابیس، فایل تصویر را بازسازی نمی‌کند.

## J — اعتبارسنجی

فرمان قابل تکرار و مستقل:

```sh
php database/tests/verify-seeders.php
```

اسکریپت تمام connectionهای پیکربندی‌شده را برای همان process با یک اتصال SQLite `:memory:` جایگزین می‌کند، نوع/نام دیتابیس و فعال‌بودن FKها را کنترل می‌کند و از دیتابیس Local استفاده نمی‌کند. credential تست تصادفی و فقط در حافظه است.

نتایج موفق:

- اجرای تمام ۳۲ Migration موجود روی SQLite خالی.
- اجرای دو مرتبه DatabaseSeeder؛ تعداد، هویت و مقادیر کاتالوگ ثابت ماندند، با کنارگذاشتن timestampهای فنی و published_at پویا.
- شروع sequence هر جدول از اعداد متفاوت و بزرگ‌تر از ۱۰۰؛ وابستگی به IDهای Local وجود نداشت.
- یک رابطه محصول-موتور با زنجیره صحیح برند، مدل، نسل و تیپ.
- صحت combination_key برای Variantها پس از تغییر IDها.
- `PRAGMA foreign_key_check` بدون خطا.
- اصلاح اطلاعات موتور و اجرای مجدد VehicleReferenceSeeder؛ اصلاح حفظ شد.
- نبود credential، عدم ایجاد کاربر پیش‌فرض؛ رمز ناقص برای ایمیل جدید، خطا؛ ساخت مدیر با رمز تصادفی، موفق.
- اجرای مجدد UserSeeder با رمز جدید روی حساب موجود؛ بدون تغییر رمز، بدون duplicate و بدون ارتقای نقش یا فعال‌کردن حساب.
- مقایسه جداگانه داده Local با SQLite تازه در ۱۶ جدول کاتالوگ و روابط: صفر اختلاف، پس از یکسان‌سازی نمایش اعداد اعشاری و کنارگذاشتن زمان‌های ایجاد/ویرایش/انتشار.

انتظار تست: همان تعداد Local برای ۱۶ جدول کاتالوگ پوشش‌داده‌شده؛ تصاویر صفر، کاربران صفر مگر provisioning صریح، و داده‌های شخصی/تراکنشی صفر. بنابراین خروجی، کپی کامل دیتابیس Local نیست. تست نوشتن روی MySQL/Server انجام نشده است.

پس از گزارش، هیچ فرمان نصب، migration یا seed روی Local/Server اجرا نشده و ادامه عملیات انتشار منتظر تأیید کاربر است.
