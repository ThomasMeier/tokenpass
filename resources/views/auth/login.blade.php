@extends('layouts.guest')

@section('htmltitle', 'Login')

@section('body_class', 'login')

@section('body_content')
<div class="everything">
	<div class="logo"><a href="/">token<strong>pass</strong></a></div>
    <div class="row">@include('partials.alerts')</div>
    @if(Tokenpass\Models\OAuthClient::getOAuthClientIDFromIntended())
        <div>
            <p class="alert-info">
                You are about to sign into 
                <strong><a href="{{\Tokenpass\Models\OAuthClient::getOAuthClientDetailsFromIntended()["app_link"]}}" target="_blank">{{\Tokenpass\Models\OAuthClient::getOAuthClientDetailsFromIntended()['name']}}</a></strong>
            </p>
        </div>
    @endif    
	<div class="logins-wrapper">
		<div class="login-with-email">
			<h1 class="login-heading">Login with Password</h1>
			<div class="form-wrapper">
				<form method="POST" action="/auth/login">
					{!! csrf_field() !!}
					<input class="with-forgot" id="Username" name="username" type="text" placeholder="username" value="{{ old('username') }}">
					<input class="with-forgot" id="Password" name="password" type="password" placeholder="password">
					<button type="submit" class="login-btn">Login</button>
				</form>
			</div>
			<div class="login-subtext">
				<span>
					Don't have an account?
					<a href="/auth/register"><strong>Register</strong></a>
				</span>
				<br/>
				<span>
					Forgot your password?
					<a href="/password/email"><strong>Reset password</strong></a>
				</span>
				<br/>
			</div>
		</div> <!-- END LOGIN WITH EMAIL -->
	  <div class="login-or-divider-module">
	    <div class="divider">.</div>
	    <span class="or">or</span>
	    <div class="divider">.</div>
	  </div>
		<div class="login-with-bitcoin">
	    <div class="form-wrapper">
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
	          <a class="signature__cts" href="{{ env('POCKETS_URI') }}:sign?message={{ str_replace('+', '%20', urlencode($sigval)) }}&label={{ str_replace('+', '%20', urlencode('Sign in to Tokenpass (2FA)')) }}&callback={{ urlencode(route('auth.login', array('msg_hash' => $msg_hash))) }}">
                <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
	            Click To Sign
	          </a>
	        </div>
	        <button type="submit" class="login-btn" id="login-btc">Login</button>

	      </form>
	    </div>
	    <div class="login-subtext">
	      <span>
	        Don't have Pockets?
	        <a href="http://pockets.tokenly.com" target="_blank"><strong>Download</strong></a>
	      </span>
	    </div>
		</div> <!-- END LOGIN WITH BITCOIN -->
	</div>
</div>

@endsection

@section('page-js')
<script type="text/javascript">
    window.checkSigInterval = setInterval(function(){
        $.get('{{ route("auth.login.check-sig") }}', function(data){
           if(typeof data.signature != 'undefined' && data.signature != null){
               $('textarea[name="signed_message"]').val(data.signature);
               $('#login-btc').click();
           }
        });
    }, 2000);
</script>
@endsection
