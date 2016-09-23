@extends('layouts.guest')

@section('htmltitle', 'Confirm Email')

@section('body_class', 'dashboard')

@section('body_content')

@if ($errors and count($errors) > 0)

<section class="title">
    <span class="heading">Email Confirmation Failed</span>
</section>
<section>
    @include('partials.alerts')

    <div class="spacer2"></div>

    <p><a class="btn-border-small" href="{{ route('auth.sendemail') }}">Send Email Again</a></p>

</section>

@else

<section class="title">
    <span class="heading">Tokenly Account Email Confirmed</span>
</section>
<section>
    <div class="spacer2"></div>

    <p>Thank you for confirming your email address.  Your email address is now confirmed.</p>

    <div class="spacer2"></div>

    <p><a class="btn-border-small" href="/dashboard">Return to Dashboard or Login</a></p>
</section>

@endif

@endsection

