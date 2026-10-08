<?php

function renderPrivacyPolicyHtml(): string {
	$legal = legalConfig();
	$app = htmlspecialchars(appDisplayName());
	$controller = htmlspecialchars($legal['controller_name']);
	$email = htmlspecialchars($legal['email']);
	$address = htmlspecialchars($legal['address']);
	$country = htmlspecialchars($legal['country']);
	$registry = trim($legal['registry_code']);
	$hosting = htmlspecialchars($legal['hosting_provider']);
	$smtp = htmlspecialchars($legal['smtp_provider']);
	$registryLine = $registry !== ''
		? '<p>Registry code: ' . htmlspecialchars($registry) . '</p>'
		: '';

	ob_start();
	?>
	<p><strong>Draft notice:</strong> This policy describes how <?php echo $app; ?> processes data today. Have a qualified lawyer review it before a public launch.</p>

	<h2>1. Who we are</h2>
	<p>Data controller: <?php echo $controller; ?></p>
	<?php echo $registryLine; ?>
	<p>Address: <?php echo $address; ?></p>
	<p>Contact: <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></p>
	<p>Country: <?php echo $country; ?></p>
	<p>Supervisory authority (Estonia): Andmekaitse Inspektsioon (AKI) — <a href="https://www.aki.ee" rel="noopener noreferrer">aki.ee</a></p>

	<h2>2. What this app is</h2>
	<p><?php echo $app; ?> is a personal finance and habit tracker. It is <strong>not</strong> a bank, credit institution, payment institution, or investment adviser. We help you record income, expenses, savings goals, and daily habits.</p>

	<h2>3. Data we collect</h2>
	<ul>
		<li><strong>Account data:</strong> name, username, email, password (stored hashed), optional guaranteed monthly income, onboarding timestamps.</li>
		<li><strong>Finance entries:</strong> date, category, amount, optional note (up to 150 characters), payment method, and import reference for synced transactions.</li>
		<li><strong>Derived statistics:</strong> monthly goals, actuals, net worth snapshots, and missing-goal calculations computed from your entries.</li>
		<li><strong>Bank connection metadata:</strong> bank name, country, IBAN, connection validity, sync timestamps, and an Enable Banking session identifier (not exported in data downloads).</li>
		<li><strong>Habits:</strong> habit names and your daily done or failed marks.</li>
		<li><strong>Security tokens:</strong> hashed password-reset and email-verification tokens with expiry.</li>
		<li><strong>Consent records:</strong> which legal documents you accepted, version, timestamp, IP address, and browser user-agent snippet.</li>
		<li><strong>Server logs:</strong> technical events such as failed logins or sync errors. Passwords are never logged.</li>
	</ul>
	<p>We do <strong>not</strong> store full raw bank API responses long-term. Imported transactions are normalised into your entry list.</p>

	<h2 id="bank-data">4. Bank account data (PSD2 / AIS)</h2>
	<p>If you connect LHV via Enable Banking, you authenticate directly with your bank. Enable Banking provides read-only account information access on our behalf.</p>
	<ul>
		<li>We request transaction data for budgeting and import it into <?php echo $app; ?>.</li>
		<li>We store your IBAN and connection expiry (<code>valid_until</code>) so you know when to reconnect.</li>
		<li>Automatic sync is limited to at most four successful pulls per 24 hours per connection (PSD2 guidance).</li>
		<li>Bank access requires your consent in-app and through your bank&apos;s authorisation flow.</li>
		<li>You can disconnect a bank in Settings. You may keep or delete previously imported transactions.</li>
	</ul>

	<h2>5. Why we process data (legal bases)</h2>
	<ul>
		<li><strong>Contract (GDPR Art 6(1)(b)):</strong> running your account, storing entries, goals, and habits.</li>
		<li><strong>Consent (GDPR Art 6(1)(a)):</strong> connecting your bank account via AIS.</li>
		<li><strong>Legitimate interest (GDPR Art 6(1)(f)):</strong> security logging, abuse prevention, and service reliability.</li>
	</ul>

	<h2>6. Retention</h2>
	<ul>
		<li>Account and entry data: until you delete your account.</li>
		<li>Password reset tokens: 1 hour after issue, then eligible for cleanup.</li>
		<li>Email verification tokens: 24 hours after issue, then eligible for cleanup.</li>
		<li>Bank connection metadata: until you disconnect or delete your account.</li>
		<li>Server logs: rotated after approximately 90 days in production.</li>
	</ul>

	<h2>7. Processors and sharing</h2>
	<ul>
		<li><strong>Enable Banking</strong> — PSD2 account information services (<a href="https://enablebanking.com" rel="noopener noreferrer">enablebanking.com</a>).</li>
		<li><strong>Hosting:</strong> <?php echo $hosting; ?></li>
		<li><strong>Email delivery:</strong> <?php echo $smtp; ?></li>
		<li><strong>LHV Pank</strong> — you authenticate with your bank; we do not receive your bank login credentials.</li>
	</ul>
	<p>We do not sell your personal data.</p>

	<h2>8. International transfers</h2>
	<p>If a processor stores data outside the European Economic Area, we rely on appropriate safeguards such as Standard Contractual Clauses. Update this section once your hosting and email vendors are final.</p>

	<h2>9. Your rights</h2>
	<p>Under GDPR you may request access, rectification, erasure, restriction, portability, or object to certain processing. You may lodge a complaint with AKI.</p>
	<p>In the app: Settings &rarr; <strong>Download my data</strong> (JSON export) or <strong>Delete my account</strong>. You may also email <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>.</p>

	<h2 id="cookies">10. Cookies</h2>
	<p>We use one strictly necessary session cookie for login state. It is HttpOnly and, in production over HTTPS, Secure. We do not use analytics or advertising cookies, so no cookie consent banner is required for the current app.</p>

	<h2>11. Children</h2>
	<p><?php echo $app; ?> is not intended for users under 16.</p>

	<h2>12. Changes</h2>
	<p>We may update this policy. Material changes will require renewed acceptance in the app. The version and date at the top of this page show the current text.</p>
	<?php
	return (string) ob_get_clean();
}

function renderTermsOfUseHtml(): string {
	$legal = legalConfig();
	$app = htmlspecialchars(appDisplayName());
	$email = htmlspecialchars($legal['email']);

	ob_start();
	?>
	<p><strong>Draft notice:</strong> These terms govern use of <?php echo $app; ?>. Have a qualified lawyer review them before a public launch.</p>

	<h2>1. Service description</h2>
	<p><?php echo $app; ?> lets you track daily habits, record income and spending, set allocation goals, and optionally import LHV transactions through Enable Banking.</p>

	<h2>2. Eligibility</h2>
	<p>You must be at least 16 years old and able to enter a binding agreement. You are responsible for the accuracy of information you provide.</p>

	<h2>3. Your account</h2>
	<ul>
		<li>Keep your password confidential.</li>
		<li>One account per person unless we agree otherwise in writing.</li>
		<li>Do not misuse the service, attempt unauthorised access, or interfere with other users.</li>
	</ul>

	<h2>4. Not financial advice</h2>
	<p><strong><?php echo $app; ?> is not a bank and does not provide financial, investment, tax, or legal advice.</strong> Goals such as &ldquo;Should save&rdquo;, projections, and net worth summaries are informational calculations based on data you enter or import. You remain solely responsible for financial decisions.</p>
	<p>We are not a credit institution, payment institution, or account information service provider. Regulated bank access is provided by Enable Banking; we consume their API as a technical client.</p>

	<h2>5. Bank connection</h2>
	<ul>
		<li>Connecting a bank is optional.</li>
		<li>You authorise access through your bank and our Enable Banking integration.</li>
		<li>Imported transactions may be incomplete or misclassified; review and edit entries as needed.</li>
		<li>Connections expire and must be renewed periodically (typically up to 90 days under PSD2).</li>
		<li>Sync frequency is limited (at most four successful automatic pulls per 24 hours per connection).</li>
		<li>You may disconnect at any time in Settings.</li>
	</ul>

	<h2>6. Acceptable use</h2>
	<p>You may not scrape, reverse engineer, overload, or use the service for unlawful purposes.</p>

	<h2>7. Availability</h2>
	<p>The service is provided on a best-effort basis. We do not guarantee uninterrupted availability or error-free calculations.</p>

	<h2>8. Limitation of liability</h2>
	<p>To the maximum extent permitted by applicable law, <?php echo $app; ?> and its operator are not liable for indirect or consequential damages arising from use of the service. Where liability cannot be excluded, it is limited to the amount you paid us in the twelve months before the claim (typically zero for the free service).</p>

	<h2>9. Termination</h2>
	<p>You may delete your account in Settings at any time. We may suspend or terminate access for abuse or legal reasons.</p>

	<h2>10. Governing law</h2>
	<p>These terms are governed by the laws of Estonia, without regard to conflict-of-law rules.</p>

	<h2>11. Contact</h2>
	<p>Questions: <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>. See also our <a href="/privacy">Privacy Policy</a>.</p>
	<?php
	return (string) ob_get_clean();
}
