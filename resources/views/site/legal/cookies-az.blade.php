@php $h = 't-title mt-4 text-[var(--text)]'; @endphp
<p>Kuki (cookie) brauzerinizdə saxlanılan kiçik mətn faylıdır. OmniDLV yalnız saytın işləməsi üçün zəruri olan kukilərdən istifadə edir. Reklam, analitika və ya sizi digər saytlarda izləyən kukilərdən istifadə etmirik və üçüncü tərəflərə kuki vasitəsilə məlumat ötürmürük.</p>

<h2 class="{{ $h }}">İstifadə etdiyimiz kukilər</h2>
<div class="plate overflow-x-auto">
    <table class="tbl min-w-[560px]">
        <thead><tr><th>Kuki</th><th>Nə üçündür</th><th>Müddət</th></tr></thead>
        <tbody>
            <tr><td class="font-mono text-[13px]">{{ config('session.cookie') }}</td><td>Giriş sessiyası: panelə və kuryer tətbiqinə daxil olduğunuzu yadda saxlayır, dil seçiminizi saxlayır.</td><td>{{ config('session.lifetime') }} dəqiqə</td></tr>
            <tr><td class="font-mono text-[13px]">XSRF-TOKEN</td><td>Formaların saxtalaşdırılmasından (CSRF) qoruma.</td><td>Sessiya ilə eyni</td></tr>
            <tr><td class="font-mono text-[13px]">remember_web_*</td><td>“Məni yadda saxla” seçildikdə girişi saxlayır.</td><td>Çıxış edənədək</td></tr>
            <tr><td class="font-mono text-[13px]">cookie_consent</td><td>Bu bildirişi qəbul etdiyinizi yadda saxlayır.</td><td>1 il</td></tr>
        </tbody>
    </table>
</div>

<h2 class="{{ $h }}">Xəritə</h2>
<p>Xəritələr OpenStreetMap plitələri ilə göstərilir. Plitələr yüklənərkən brauzeriniz xəritə serverinə adi şəbəkə sorğusu göndərir; bu zaman bizim tərəfimizdən kuki qoyulmur.</p>

<h2 class="{{ $h }}">Kukiləri necə idarə etmək olar</h2>
<p>Kukiləri brauzerinizin ayarlarından silə və ya bloklaya bilərsiniz. Zəruri kukilər bloklandıqda panelə və kuryer tətbiqinə daxil olmaq mümkün olmayacaq. Alıcının izləmə səhifəsi kukisiz də işləyir.</p>

<h2 class="{{ $h }}">Dəyişikliklər</h2>
<p>Yeni kuki əlavə etsək, bu səhifəni yeniləyəcəyik. Zəruri olmayan kukilər üçün əvvəlcə razılığınızı soruşacağıq.</p>
