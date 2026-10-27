@php $h = 't-title mt-4 text-[var(--text)]'; @endphp
<p>Bu qaydalar OmniDLV platformasından (bundan sonra “Platforma”) istifadə şərtlərini müəyyən edir. Platforma çatdırılma əməliyyatlarının idarə edilməsi üçün onlayn xidmətdir: idarəetmə paneli, kuryer tətbiqi, inteqrasiya API-si və müştəri izləmə səhifəsi. Platformadan istifadə etməklə siz bu qaydaları qəbul etmiş olursunuz.</p>

<h2 class="{{ $h }}">1. Anlayışlar</h2>
<ul class="flex list-disc flex-col gap-1.5 pl-5">
    <li><strong class="text-[var(--text)]">Şirkət</strong> — Platformada hesabı olan və onun xidmətlərindən istifadə edən hüquqi şəxs və ya fərdi sahibkar.</li>
    <li><strong class="text-[var(--text)]">İstifadəçi</strong> — Şirkətin adından Platformaya daxil olan şəxs: sahib, administrator, dispetçer və ya kuryer.</li>
    <li><strong class="text-[var(--text)]">Alıcı</strong> — Şirkətin sifarişini qəbul edən və izləmə səhifəsindən istifadə edən şəxs.</li>
    <li><strong class="text-[var(--text)]">Plan</strong> — Şirkətə təqdim olunan funksiyaları, limitləri və qiyməti müəyyən edən abunə növü.</li>
</ul>

<h2 class="{{ $h }}">2. Hesabın açılması</h2>
<p>Şirkət hesabları Platformanın administratoru tərəfindən açılır. Şirkət təqdim etdiyi məlumatların doğruluğuna cavabdehdir. Şirkətin sahibi öz işçilərini və kuryerlərini Platformaya əlavə edir və onların hərəkətlərinə görə məsuliyyət daşıyır.</p>

<h2 class="{{ $h }}">3. Giriş məlumatlarının təhlükəsizliyi</h2>
<p>İstifadəçi şifrəsini, SMS kodlarını və API açarlarını üçüncü şəxslərə vermir. Hesabdan icazəsiz istifadədən şübhələndikdə dərhal şifrəni dəyişməli və Platformanın administratoruna xəbər verməlidir. Bir hesabdan bir neçə nəfərin istifadəsi qadağandır.</p>

<h2 class="{{ $h }}">4. Abunə, sınaq və ödəniş</h2>
<p>Xidmətlər seçilmiş plana uyğun göstərilir. Yeni Şirkətə pulsuz sınaq müddəti verilə bilər. Sınaq və ya ödənilmiş müddət bitdikdə panelə və API-yə giriş məhdudlaşdırıla bilər. Qiymətlər və plan şərtləri Platforma tərəfindən dəyişdirilə bilər; dəyişiklik ödənilmiş cari dövrə şamil edilmir.</p>

<h2 class="{{ $h }}">5. İcazə verilən istifadə</h2>
<p>Platformadan yalnız qanuni çatdırılma fəaliyyəti üçün istifadə etmək olar. Qadağandır:</p>
<ul class="flex list-disc flex-col gap-1.5 pl-5">
    <li>qanunla qadağan olunmuş malların çatdırılmasının təşkili;</li>
    <li>alıcılara onların razılığı olmadan reklam xarakterli SMS göndərilməsi;</li>
    <li>Platformanın təhlükəsizliyini yoxlamaq, onu sındırmaq və ya işini pozmaq cəhdləri;</li>
    <li>API-yə həddindən artıq yük verilməsi və məhdudiyyətlərdən yayınma;</li>
    <li>digər şirkətlərin məlumatlarına giriş cəhdləri.</li>
</ul>
<p>Bu qaydaların pozulması hesabın xəbərdarlıqsız dayandırılmasına səbəb ola bilər.</p>

<h2 class="{{ $h }}">6. Məlumatlar və məxfilik</h2>
<p>Şirkətin Platformaya daxil etdiyi məlumatlar (sifarişlər, ünvanlar, alıcıların adı və telefonu, kuryerlərin yeri, çatdırılma fotoları) Şirkətə məxsusdur. Platforma bu məlumatları yalnız xidmət göstərmək üçün emal edir və üçüncü şəxslərə satmır. Alıcıların fərdi məlumatlarını toplamaq üçün qanuni əsasın olması Şirkətin məsuliyyətidir. Fərdi məlumatlar Azərbaycan Respublikasının “Fərdi məlumatlar haqqında” Qanununa uyğun emal olunur.</p>
<p>Kuryerin yeri yalnız növbə (iş vaxtı) ərzində toplanır. Yer tarixçəsi və çatdırılma fotoları saxlanma müddəti bitdikdən sonra avtomatik silinir.</p>

<h2 class="{{ $h }}">7. Xidmətin əlçatanlığı</h2>
<p>Platforma xidmətin fasiləsiz işləməsi üçün ağlabatan səy göstərir, lakin texniki işlər, üçüncü tərəf xidmətlərindəki (SMS, xəritə, hostinq) nasazlıqlar və ya fors-major hallar səbəbindən fasilələr ola bilər. Planlı texniki işlər barədə mümkün olduqca əvvəlcədən məlumat verilir.</p>

<h2 class="{{ $h }}">8. Məsuliyyətin məhdudlaşdırılması</h2>
<p>Platforma çatdırılmanın özünü həyata keçirmir və malların itməsi, zədələnməsi və ya gecikməsinə görə cavabdeh deyil. Marşrut və gəliş vaxtı hesablamaları təxminidir. Platformanın istənilən iddia üzrə ümumi məsuliyyəti Şirkətin son üç ayda ödədiyi abunə haqqı ilə məhdudlaşır.</p>

<h2 class="{{ $h }}">9. Hesabın dayandırılması və bağlanması</h2>
<p>Şirkət istənilən vaxt hesabının bağlanmasını tələb edə bilər. Platforma ödənişin edilmədiyi və ya bu qaydaların pozulduğu hallarda hesabı dayandıra bilər. Hesab bağlandıqdan sonra Şirkət 30 gün ərzində məlumatlarının ixracını tələb edə bilər; bu müddətdən sonra məlumatlar silinə bilər.</p>

<h2 class="{{ $h }}">10. Qaydaların dəyişdirilməsi</h2>
<p>Bu qaydalar yenilənə bilər. Yenilənmə tarixi səhifənin yuxarısında göstərilir. Əhəmiyyətli dəyişikliklər barədə Şirkətlərə əvvəlcədən məlumat verilir. Dəyişiklikdən sonra Platformadan istifadənin davam etdirilməsi yeni qaydaların qəbulu deməkdir.</p>

<h2 class="{{ $h }}">11. Tətbiq olunan hüquq</h2>
<p>Bu qaydalar Azərbaycan Respublikasının qanunvericiliyi ilə tənzimlənir. Mübahisələr danışıqlar yolu ilə, razılıq əldə olunmadıqda isə Azərbaycan Respublikasının məhkəmələrində həll edilir.</p>

@if (config('services.support_email'))
    <h2 class="{{ $h }}">12. Əlaqə</h2>
    <p>Qaydalarla bağlı suallar üçün: <a href="mailto:{{ config('services.support_email') }}" class="text-[var(--text)] underline">{{ config('services.support_email') }}</a></p>
@endif
