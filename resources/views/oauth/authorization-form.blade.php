@extends('layouts.authorize')

@section('htmltitle', 'Authorization Requested')


@section('body_content')

@include('partials.alerts')


<p>The application <span class="application-name">{{{ $client->getName() }}}</span> has requested the following privileges:</p>
<ul class="list-unstyled grant-list">
    @foreach ($scopes as $scope)
    <li><strong>@if(trim($scope->label) != ''){{{ $scope->label }}}@else{{{ $scope->id }}}@endif</strong><br>
        {{{ $scope->description }}} 
    </li>
    @endforeach
</ul>

<p>What would you like to do?</p>

<form method="POST" action="{{ route('oauth.authorize.post', $params) }}">

    {!! csrf_field() !!}

    <input type="hidden" name="client_id" value="{{$params['client_id']}}" />
    <input type="hidden" name="redirect_uri" value="{{$params['redirect_uri']}}" />
    <input type="hidden" name="response_type" value="{{$params['response_type']}}" />
    <input type="hidden" name="scopes" value="{{$params['scopes']}}" />
    <input type="hidden" name="state" value="{{$params['state']}}" />

    <div class="spacer1"></div>

    <div>
        <button type="submit" name="approve" value="1" class="btn btn-success">Grant Access</button>
        <button type="submit" name="deny" value="1" class="btn btn-secondary">Deny Access</button>
    </div>

</form>


@endsection

