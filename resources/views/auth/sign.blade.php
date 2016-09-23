@extends('layouts.guest')

@section('htmltitle', 'Login With Bitcoin')

@section('body_class', 'login')

@section('body_content')

    <div class="everything">
        <div class="logo"><a href="/">token<strong>pass</strong></a></div>
        <h1 class="login-heading">BTC Two Factor Authentication</h1>
        <div class="form-wrapper">
            @include('partials.alerts')
			@if(Tokenpass\Models\OAuthClient::getOAuthClientIDFromIntended())
				<div>
                    <p class="alert-info">
                        You are about to sign into 
                        <strong><a href="{{\Tokenpass\Models\OAuthClient::getOAuthClientDetailsFromIntended()["app_link"]}}" target="_blank">{{\Tokenpass\Models\OAuthClient::getOAuthClientDetailsFromIntended()['name']}}</a></strong>
                    </p>
				</div>
			@endif
            <form method="POST" action="/auth/signed">
                {!! csrf_field() !!}

                <div class="tooltip-wrapper" data-tooltip="Sign this message with a verified bitcoin address which has 2FA enabled, this is for your security">
                    <i class="help-icon material-icons">help_outline</i>
                </div>
                <input name="btc-wotd" type="text" placeholder="btc-wotd" value="{{ $sigval }}" onclick="this.select();" readonly>
                <input type="hidden" name="redirect" value="{{ $redirect }}">
                <div class="tooltip-wrapper" data-tooltip="Paste your signed message into this window, then click authenticate.">
                    <i class="help-icon material-icons">help_outline</i>
                </div>
                <textarea name="signed_message" placeholder="cryptographic signature" rows="5"></textarea>
                <div class="signature__wrapper">
                    <a class="signature__cts" href="{{ env('POCKETS_URI') }}:sign?message={{ str_replace('+', '%20', urlencode($sigval)) }}&label={{ str_replace('+', '%20', urlencode('Sign in to Tokenpass')) }}&callback={{ urlencode(route('auth.signed', array('msg_hash' => $msg_hash))) }}">
                        <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
                        Click To Sign
                    </a>                 
                </div>
                <button type="submit" class="login-btn" id="auth-btc">Authenticate</button>
            </form>
        </div>
    </div>

@endsection

@section('page-js')
<script type="text/javascript">
    window.checkSigInterval = setInterval(function(){
        $.get('{{ route("auth.login.check-sig", array("2fa" => 1)) }}', function(data){
           if(typeof data.signature != 'undefined' && data.signature != null){
               $('textarea[name="signed_message"]').val(data.signature);
               $('#auth-btc').click();
           }
        });
    }, 2000);
</script>
@endsection
