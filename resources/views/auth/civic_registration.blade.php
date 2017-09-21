@extends('layouts.guest')

@section('htmltitle', 'Register')

@section('body_class', 'login')

@section('body_content')
    <div class="everything">
        <div class="logo"><a href="/">token<strong>pass</strong></a></div>
        <h1>Finish Civic registration</h1>
        <div class="form-wrapper">
            @include('partials.alerts')
            <form method="POST" action="/auth/register">
                {!! csrf_field() !!}
                <input type="text" name="name" placeholder="name" value="{{ old('name') }}">
                <input type="text" name="username" placeholder="username" value="{{ old('username') }}" required>
                <input type="email" name="email" placeholder="email" value="{{ old('email') }}" required>
                <input type="password" name="password" placeholder="password" required>
                <input type="password" name="password_confirmation" placeholder="password (again)" required>
                <div class="g-recaptcha" data-sitekey="{{ env('RECAPTCHA_PUBLIC') }}"></div>
                <button type="submit" class="login-btn">Register</button>
            </form>
        </div>
    </div>
@endsection

