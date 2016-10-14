@extends('platformAdmin::layouts.app')

@section('title_name') Edit Client @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Edit Client {{ $model['name'] }}</h1>
    </div>

    @include('platformAdmin::includes.errors')


    {!! Form::model($model, [
        'method' => 'PATCH',
        'route' => ['platform.admin.client.update', $model['id']],
    ]) !!}

    <div class="row">
        <div class="six columns">
            
            {!! Form::label('id', 'Client ID') !!}
            {{ $model['id'] }}
            <br><br>
            {!! Form::label('secret', 'Client Secret') !!}
            {{ $model['secret'] }}            
        </div>

        <div class="six columns">
            {!! Form::label('name', 'Client Name') !!}
            {!! Form::text('name', $model['name'], ['class' => 'u-full-width']) !!}
            <br><br>
            {!! Form::label('endpoints', 'OAuth Endpoints') !!}
            {!! Form::textarea('endpoints', $model->endpointsText(), ['class' => 'u-full-width']) !!}        
            <br><br>
            {!! Form::label('app_link', 'App URL') !!}
            {!! Form::text('app_link', $model['app_link'], ['class' => 'u-full-width']) !!}    
            <br><br>
            {!! Form::label('privileges', 'Extra API Privileges') !!}
            {!! Form::textarea('privileges', $model->privileges, ['class' => 'u-full-width']) !!}                   
        </div>

        <div class="six columns">
        </div>
    </div>




    <div class="row" style="margin-top: 3%;">
        <div class="three columns">
            {!! Form::submit('Update', ['class' => 'button-primary u-full-width']) !!}
        </div>
        <div class="six columns">&nbsp;</div>
        <div class="three columns">
            <a class="button u-full-width" href="{{ route('platform.admin.client.index') }}">Cancel</a>
        </div>
    </div>

    {!! Form::close() !!}

</div>

@endsection

