@extends('layouts.guest')

@section('htmltitle', 'Register')

@section('body_class', 'login')

@section('body_content')
    <div class="everything">
        <div class="logo"><a href="/">token<strong>pass</strong></a></div>
        <h2>Finish Civic registration</h2>
        <div class="form-wrapper">
            @include('partials.alerts')
            <form method="POST" action="/auth/register">
                {!! csrf_field() !!}
                <input type="text" name="username" placeholder="username" required>
                <input type="email" name="email" placeholder="email" value="{{ $civic_email }}" required>
                <div class="g-recaptcha" data-sitekey="{{ env('RECAPTCHA_PUBLIC') }}"></div>
                <button type="submit" class="login-btn">Register</button>
            </form>
        </div>
    </div>
@endsection

