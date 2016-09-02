@extends('accounts.base')

@section('htmltitle', 'Dashboard')

@section('body_class', 'dashboard')

@section('accounts_content')

<section class="title">
  <span class="heading">Dashboard</span>
  <a href="/auth/update" class="btn-dash-title">Update your Profile</a>
</section>

<section>
  <div class="panel with-padding">
    <p>Welcome, {{$user['name']}}</p>

    <p>You are logged in as user <strong>{{ $user['username']}}</strong>.</p>

    @if (!$user->emailIsConfirmed())
        <div role="alert">
            <p><strong>Note</strong>: You must confirm your email address by clicking the link in the email you received before you can use your account.</p>
            <p><a href="/auth/sendemail">Send the email confirmation again</a>.</p>
        </div>
    @endif
  </div>
</section>

@endsection