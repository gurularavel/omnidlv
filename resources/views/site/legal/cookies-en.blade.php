@php $h = 't-title mt-4 text-[var(--text)]'; @endphp
<p>A cookie is a small text file stored in your browser. OmniDLV uses only the cookies the site needs to work. We use no advertising, analytics or cross-site tracking cookies and pass no data to third parties through cookies.</p>

<h2 class="{{ $h }}">Cookies we use</h2>
<div class="plate overflow-x-auto">
    <table class="tbl min-w-[560px]">
        <thead><tr><th>Cookie</th><th>Purpose</th><th>Lifetime</th></tr></thead>
        <tbody>
            <tr><td class="font-mono text-[13px]">{{ config('session.cookie') }}</td><td>Sign-in session: keeps you signed in to the panel and courier app and remembers your language.</td><td>{{ config('session.lifetime') }} minutes</td></tr>
            <tr><td class="font-mono text-[13px]">XSRF-TOKEN</td><td>Protects forms against cross-site request forgery (CSRF).</td><td>Same as the session</td></tr>
            <tr><td class="font-mono text-[13px]">remember_web_*</td><td>Keeps you signed in when you choose “Keep me signed in”.</td><td>Until you sign out</td></tr>
            <tr><td class="font-mono text-[13px]">cookie_consent</td><td>Remembers that you accepted this notice.</td><td>1 year</td></tr>
        </tbody>
    </table>
</div>

<h2 class="{{ $h }}">Maps</h2>
<p>Maps use OpenStreetMap tiles. Loading them sends an ordinary network request from your browser to the tile server; we set no cookie for it.</p>

<h2 class="{{ $h }}">Managing cookies</h2>
<p>You can delete or block cookies in your browser settings. With the necessary cookies blocked you cannot sign in to the panel or the courier app. The recipient tracking page works without cookies.</p>

<h2 class="{{ $h }}">Changes</h2>
<p>If we add a cookie we will update this page. For any cookie that is not strictly necessary we will ask for your consent first.</p>
