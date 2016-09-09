@extends('layouts.guest')

@section('htmltitle', 'Login With Bitcoin')

@section('body_class', 'login')

@section('body_content')

<div class="everything">
  <div class="logo"><a href="/">token<strong>pass</strong></a></div>
    <div class="form-wrapper">
      @include('partials.alerts')
      @if(TKAccounts\Models\OAuthClient::getOAuthClientIDFromIntended())
          <div>
              <p class="alert-info">
                  You are about to sign into 
                  <strong><a href="{{\TKAccounts\Models\OAuthClient::getOAuthClientDetailsFromIntended()["app_link"]}}" target="_blank">{{\TKAccounts\Models\OAuthClient::getOAuthClientDetailsFromIntended()['name']}}</a></strong>
              </p>
          </div>
      @endif

      <h1 class="login-heading">Login with Bitcoin</h1>
      <form method="POST" action="/auth/bitcoin">
        {!! csrf_field() !!}

        <div class="tooltip-wrapper" data-tooltip="Login quickly and securely by signing todays Word of the Day with a previously verified address.">
          <i class="help-icon material-icons">help_outline</i>
        </div>
        <input name="btc-wotd" type="text" placeholder="btc-wotd" value="{{ $sigval }}" onclick="this.select();" readonly>

        <div class="tooltip-wrapper" data-tooltip="Paste your signed Word of the Day into this window, then click login.">
          <i class="help-icon material-icons">help_outline</i>
        </div>
        <div class="signature__wrapper">
          <textarea name="signed_message" placeholder="cryptographic signature" rows="4"></textarea>
          <a class="signature__cts" href="{{ env('POCKETS_URI') }}:sign?message={{ str_replace('+', '%20', urlencode($sigval)) }}&label={{ str_replace('+', '%20', urlencode('Sign in to Tokenpass')) }}&callback={{ urlencode(route('auth.bitcoin')) }}">
            <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
            Click To Sign
          </a>
        </div>
        <button type="submit" class="login-btn">Login</button>

      </form>
    </div>
    <div class="login-subtext">
      <span>
        Don't have Pockets?
        <a href="http://pockets.tokenly.com" target="_blank"><strong>Download</strong></a>
      </span>
    </div>
    <div class="login-or-divider-module">
      <div class="divider">.</div>
      <span class="or">or</span>
      <div class="divider">.</div>
    </div>
    <a class="signin-with-btc-btn" href="/auth/login">Sign In With Username</a>
    <div class="login-subtext">
      <span>
        Don't have an account?
        <a href="/auth/register"><strong>Register</strong></a>
      </span>
    </div>
</div>

@endsection
