@php $h = 't-title mt-4 text-[var(--text)]'; @endphp
<p>These terms govern the use of the OmniDLV platform (the “Platform”), an online service for running delivery operations: the management panel, the courier app, the integration API and the recipient tracking page. By using the Platform you accept these terms.</p>

<h2 class="{{ $h }}">1. Definitions</h2>
<ul class="flex list-disc flex-col gap-1.5 pl-5">
    <li><strong class="text-[var(--text)]">Company</strong>: a business with an account on the Platform that uses its services.</li>
    <li><strong class="text-[var(--text)]">User</strong>: a person who signs in on behalf of a Company: owner, administrator, dispatcher or courier.</li>
    <li><strong class="text-[var(--text)]">Recipient</strong>: a person who receives a Company’s order and uses the tracking page.</li>
    <li><strong class="text-[var(--text)]">Plan</strong>: the subscription that sets a Company’s features, limits and price.</li>
</ul>

<h2 class="{{ $h }}">2. Accounts</h2>
<p>Company accounts are opened by the Platform administrator. The Company is responsible for the accuracy of the information it provides. The Company owner adds staff and couriers and is responsible for their actions.</p>

<h2 class="{{ $h }}">3. Keeping access secure</h2>
<p>Users do not share passwords, SMS codes or API keys. If unauthorised use is suspected, the User changes the password at once and tells the Platform administrator. One account must not be shared by several people.</p>

<h2 class="{{ $h }}">4. Subscription, trial and payment</h2>
<p>Services are provided according to the chosen plan. A new Company may receive a free trial. When the trial or paid period ends, access to the panel and API may be limited. The Platform may change prices and plan terms; changes do not apply to a period already paid.</p>

<h2 class="{{ $h }}">5. Acceptable use</h2>
<p>The Platform may be used only for lawful delivery operations. It is not allowed to:</p>
<ul class="flex list-disc flex-col gap-1.5 pl-5">
    <li>arrange delivery of goods prohibited by law;</li>
    <li>send recipients promotional SMS without their consent;</li>
    <li>probe, break into or disrupt the Platform;</li>
    <li>overload the API or work around its limits;</li>
    <li>try to access other companies’ data.</li>
</ul>
<p>Breaking these rules may lead to suspension of the account without notice.</p>

<h2 class="{{ $h }}">6. Data and privacy</h2>
<p>Data the Company enters (orders, addresses, recipient names and phones, courier locations, delivery photos) belongs to the Company. The Platform processes it only to provide the service and does not sell it. The Company is responsible for having a lawful basis to collect recipients’ personal data. Personal data is processed under the Law of the Republic of Azerbaijan “On Personal Data”.</p>
<p>A courier’s location is collected only during a shift. Location history and delivery photos are deleted automatically after their retention period.</p>

<h2 class="{{ $h }}">7. Availability</h2>
<p>The Platform makes reasonable efforts to keep the service running, but interruptions may occur because of maintenance, failures of third-party services (SMS, maps, hosting) or force majeure. Planned maintenance is announced in advance where possible.</p>

<h2 class="{{ $h }}">8. Limitation of liability</h2>
<p>The Platform does not carry out deliveries and is not liable for loss, damage or delay of goods. Route and arrival time calculations are estimates. The Platform’s total liability for any claim is limited to the subscription fees the Company paid in the last three months.</p>

<h2 class="{{ $h }}">9. Suspension and closure</h2>
<p>A Company may ask to close its account at any time. The Platform may suspend an account for non-payment or breach of these terms. After closure the Company may request an export of its data within 30 days; after that the data may be deleted.</p>

<h2 class="{{ $h }}">10. Changes to these terms</h2>
<p>These terms may be updated; the date at the top shows the latest version. Companies are told about significant changes in advance. Continuing to use the Platform after a change means accepting the new terms.</p>

<h2 class="{{ $h }}">11. Governing law</h2>
<p>These terms are governed by the laws of the Republic of Azerbaijan. Disputes are settled by negotiation, and failing that by the courts of the Republic of Azerbaijan.</p>

@if (config('services.support_email'))
    <h2 class="{{ $h }}">12. Contact</h2>
    <p>Questions about these terms: <a href="mailto:{{ config('services.support_email') }}" class="text-[var(--text)] underline">{{ config('services.support_email') }}</a></p>
@endif
