@extends('layouts.public')

@section('title', 'Terms of Service')
@section('robots', 'index, follow')
@section('headerLabel', 'Legal')

@include('legal._styles')

@section('content')
<article class="legal">
    <h1>Terms of Service</h1>
    <p class="legal-meta">Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y') }}</p>

    <p>These terms are an agreement between you and {{ config('legal.operator_name') }}, who runs Certicode Labs ("Certicode", "we"). By creating an account or using Certicode, you agree to them and to our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>

    <h2 id="eligibility">1. Who can use Certicode</h2>
    <p>You must be 18 or older to sign up on your own. A student under 18 may use Certicode only if their school enrolled them and has consent from their parent or guardian.</p>

    <h2 id="accounts">2. Your account</h2>
    <p>Give accurate information and keep your password private. You are responsible for what happens under your account. Tell us right away if you think someone else has used it. Instructor access needs an admin's approval.</p>

    <h2 id="instructors">3. Instructors and schools</h2>
    <p>If you run a class on Certicode, you agree to:</p>
    <ul>
        <li>have your school's permission to use Certicode with your students</li>
        <li>tell students about camera and activity monitoring before they start a lab</li>
        <li>get parent or guardian consent for students under 18</li>
        <li>offer another way to be assessed where your school's rules or the law require one</li>
        <li>use student data only to teach and assess your class</li>
        <li>review flags and AI grades with care, and not treat a flag as proof of cheating by itself</li>
    </ul>

    <h2 id="monitoring">4. Lab monitoring</h2>
    <p>Labs use camera checks and activity monitoring, as described in the <a href="{{ route('legal.privacy') }}#camera">Privacy Policy</a>. We ask for your consent before your first lab. You can withdraw it, but labs can't start without it.</p>

    <h2 id="use">5. Acceptable use</h2>
    <p>You agree not to:</p>
    <ul>
        <li>cheat, or help someone else cheat, including by having someone else take your lab</li>
        <li>tamper with, fake or block monitoring, grading or telemetry</li>
        <li>break into accounts or systems, or test them for weaknesses without our written permission</li>
        <li>use code runners to attack anything, mine cryptocurrency or reach outside the lab sandbox</li>
        <li>overload the service, or scrape it</li>
        <li>upload malware, or content that is illegal, hateful or harassing</li>
        <li>share another student's work, personal data or snapshots outside the class</li>
    </ul>

    <h2 id="work">6. Your work</h2>
    <p>You keep the rights to the code you write. You give us permission to store, run, grade and compare it, and to show it to your instructor and teammates, as needed to provide Certicode. Instructors keep the rights to their lab materials and give us the same permission for them.</p>

    <h2 id="grades">7. Grades and certificates</h2>
    <p>Grades may be produced with AI and can be wrong. Your instructor makes the final call and can override them. Certificates record what was done in a Certicode class. They aren't a degree, a licence or an accreditation, and we don't promise any school or employer will accept them. Anyone with a certificate's verification code can see the name, class and date on it.</p>

    <h2 id="suspension">8. Suspension and closing accounts</h2>
    <p>We may suspend or remove an account that breaks these terms or puts the service or other people at risk. You can delete your account at any time in Settings. What happens to your data then is explained in the <a href="{{ route('legal.privacy') }}#rights">Privacy Policy</a>.</p>

    <h2 id="service">9. The service</h2>
    <p>We work to keep Certicode running, but it is provided "as is". It may have errors or downtime, and we may change or stop features. Keep your own copy of work that matters to you.</p>

    <h2 id="liability">10. Liability</h2>
    <p>To the extent the law allows, we aren't liable for indirect or consequential losses, such as a missed deadline or a lost grade caused by an outage. Nothing in these terms limits a right or remedy that the law doesn't allow us to limit, including your rights under the Data Privacy Act.</p>

    <h2 id="changes">11. Changes</h2>
    <p>We may update these terms. If a change affects what you agreed to, we will ask you to accept the new version when you next sign in.</p>

    <h2 id="law">12. Governing law</h2>
    <p>These terms are governed by the laws of the Republic of the Philippines. Disputes go to the proper courts of the Philippines.</p>

    <h2 id="contact">13. Contact</h2>
    <p>Questions about these terms: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
</article>
@endsection
