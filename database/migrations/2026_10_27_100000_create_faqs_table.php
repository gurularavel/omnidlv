<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Public FAQ (/faq), edited by the platform admin. One row per question and language. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('az');
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['locale', 'is_active', 'sort']);
        });

        $faqs = [
            'az' => [
                ['OmniDLV nədir?', 'OmniDLV çatdırılma xidmətləri və mağazalar üçün platformadır: sifarişlərin qəbulu (panel, API, Excel/CSV), marşrut planlaması, kuryerlərin təyini, canlı izləmə, çatdırılma sübutu, müştəriyə SMS bildirişləri və hesabatlar bir yerdədir.'],
                ['Necə qoşula bilərəm?', 'Hesablar platforma administratoru tərəfindən açılır. Bizimlə əlaqə saxlayın: şirkətinizi yaradıb sahib hesabının giriş məlumatlarını sizə göndərəcəyik.'],
                ['Sınaq müddəti varmı?', 'Bəli. Yeni şirkətlərə adətən 14 günlük pulsuz sınaq verilir. Sınaq bitdikdən sonra seçdiyiniz plana uyğun abunə ilə davam edə bilərsiniz.'],
                ['Qiymət necə hesablanır?', 'Qiymət aktiv kuryer sayına görə aylıq hesablanır. İllik ödənişdə aylıq qiymət daha aşağıdır. Böyük həcmlər üçün fərdi şərtlər razılaşdırılır.'],
                ['Kuryerlər necə işləyir?', 'Kuryer telefon nömrəsi ilə əlavə olunur və tətbiqə SMS kodu ilə daxil olur. Tətbiqdə gündəlik marşrutunu görür, statusları dəyişir, foto və ya kodla çatdırılmanı təsdiqləyir.'],
                ['Mövcud saytımı və ya sistemimi qoşa bilərəmmi?', 'Bəli. İnteqrasiya API-si və webhook-lar vasitəsilə sifarişləri avtomatik göndərə və status dəyişikliklərini öz sisteminizdə ala bilərsiniz. Sifarişləri Excel/CSV faylı ilə də yükləmək olar.'],
                ['Müştəri sifarişini necə izləyir?', 'Hər sifarişin izləmə kodu olur. Müştəri SMS ilə gələn linkdən kuryerin yerini, təxmini gəliş vaxtını və statusu görür, çatdırılmadan sonra rəy bildirə bilir.'],
                ['Məlumatlarım təhlükəsizdirmi?', 'Hər şirkətin məlumatları digərlərindən ayrı saxlanılır, bütün əlaqə HTTPS ilə şifrələnir, giriş rollara görə məhdudlaşdırılır və məlumatların ehtiyat nüsxəsi müntəzəm çıxarılır.'],
            ],
            'en' => [
                ['What is OmniDLV?', 'OmniDLV is a platform for delivery services and shops: order intake (panel, API, Excel/CSV), route planning, courier assignment, live tracking, proof of delivery, recipient SMS updates and reports in one place.'],
                ['How do I get started?', 'Accounts are opened by the platform administrator. Contact us: we create your company and send you the owner account sign-in details.'],
                ['Is there a trial?', 'Yes. New companies usually get a free 14-day trial. After it ends you continue on the plan you choose.'],
                ['How is pricing calculated?', 'The price is per active courier per month. Paying yearly lowers the monthly price. Large volumes get custom terms.'],
                ['How do couriers work?', 'A courier is added by phone number and signs in to the app with an SMS code. The app shows the day’s route, status changes, and confirms delivery with a photo or code.'],
                ['Can I connect my website or system?', 'Yes. With the integration API and webhooks you can send orders automatically and receive status changes in your own system. Orders can also be uploaded from Excel/CSV.'],
                ['How does a recipient track an order?', 'Every order has a tracking code. From the SMS link the recipient sees the courier’s position, the estimated arrival time and the status, and can rate the delivery afterwards.'],
                ['Is my data safe?', 'Each company’s data is kept apart from the others, all traffic is encrypted with HTTPS, access is limited by role and data is backed up regularly.'],
            ],
        ];

        $now = now();
        foreach ($faqs as $locale => $items) {
            foreach ($items as $i => [$question, $answer]) {
                DB::table('faqs')->insert([
                    'locale' => $locale, 'question' => $question, 'answer' => $answer,
                    'sort' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
