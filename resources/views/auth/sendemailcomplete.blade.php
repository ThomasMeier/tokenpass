@extends('accounts.base')

@section('htmltitle', 'Email Sent')

@section('body_class', 'dashboard')

@section('accounts_content')

<section class="title">
  <span class="heading">Confirm My Tokenly Account Email</span>
</section>

<section>
<div class="spacer2"></div>

<p>A confirmation email was sent to {{ $model['email'] }}. Please click the link in that email to confirm your email address.</p>

<div class="spacer4"></div>
<p><a class="btn-border-small" href="/dashboard">Return to Dashboard</a></p>
</section>



@endsection
