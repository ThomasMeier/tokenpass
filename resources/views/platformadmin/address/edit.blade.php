@extends('platformAdmin::layouts.app')

@section('title_name') Edit Pocket Address @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Edit Pocket Address</h1>
    </div>

    @include('platformAdmin::includes.errors')


    {!! Form::model($model, [
        'method' => 'PATCH',
        'route' => ['platform.admin.address.update', $model['id']],
    ]) !!}

    <div class="row">
        <div class="eight columns">
            <h5><strong><a href="https://blocktrail.com/BTC/address/{{ $model['address'] }}" target="_blank">{{ $model['address'] }}</a></strong></h5>
            <p>
               (<a href="https://blockscan.com/address/{{ $model['address'] }}" target="_blank">Blockscan</a>)<br>
               <strong>Type:</strong> {{ $model['type'] }}<br>
               <strong>UUID:</strong> {{ $model['uuid'] }}<br>
               <strong>Created at:</strong> {{ $model->created_at->format('F j\, Y \a\t g:i A') }}<br>
               <strong>Updated at:</strong> {{ $model->updated_at->format('F j\, Y \a\t g:i A') }}<br>
               <strong>XChain Address ID:</strong> {{ $model['xchain_address_id'] }}<br>
               <strong>XChain Send Monitor:</strong> {{ $model['send_monitor_id'] }}<br>
               <strong>XChain Receive Monitor:</strong>{{ $model['receive_monitor_id'] }}<br>
            </p>
            <div class="form-group">
                <label for="user_id">Owner</label>
                <select name="user_id" id="user_id" class="form-control">
                    @foreach(Tokenpass\Models\User::all() as $user)
                        <option value="{{ $user->id }}" @if($user->id == $model['user_id']) selected @endif >{{ $user->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="label">Label</label>
                <input type="text" name="label" id="label" value="{{ $model['label'] }}" class="form-control" />
            </div>
            <div class="form-group">
                <label for="verified">Verified</label>
                <select name="verified" id="verified" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['verified'] == 1) selected @endif >Yes</option>
                </select>
            </div>            
            <div class="form-group">
                <label for="active_toggle">Active</label>
                <select name="active_toggle" id="active_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['active_toggle'] == 1) selected @endif >Yes</option>
                </select>
            </div>
            <p>
            </p>
            <div class="form-group">
                <label for="primary_toggle">Primary</label>
                <select name="primary" id="primary_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['primary'] == 1) selected @endif >Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="public">Public</label>
                <select name="public" id="public" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['public'] == 1) selected @endif >Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="login_toggle">Login Enabled</label>
                <select name="login_toggle" id="login_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['login_toggle'] == 1) selected @endif >Yes</option>
                </select>
            </div>  
            <div class="form-group">
                <label for="second_factor_toggle">2FA Enabled</label>
                <select name="second_factor_toggle" id="second_factor_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['second_factor_toggle'] == 1) selected @endif >Yes</option>
                </select>
            </div>       
            <div class="form-group">
                <label for="from_api">From API (disables login and 2fa toggles)</label>
                <select name="from_api" id="from_api" class="form-control">
                    <option value="0">No</option>
                    <option value="1" @if($model['from_api'] == 1) selected @endif >Yes</option>
                </select>
            </div>                   
            <input type="hidden" name="address" value="{{ $model['address'] }}" />
        </div>
        <div class="four columns">
            <h5>Balances:</h5>
            <ul>
                @foreach($model->balances() as $asset => $amount)
                    <li><strong>{{ $asset }}:</strong> {{ number_format($amount/100000000, 8) }}</li>
                @endforeach
            </ul>
            <h5>Promises:</h5>
            <ul>
                @foreach($model->promises() as $promise)
                    <li><strong>{{ $promise->asset }}:</strong> {{ number_format($promise->quantity/100000000, 8) }}
                        <br><small>(Client ID {{ $promise->client_id }})</small>
                        </li>
                @endforeach
            </ul>            
        </div>
    </div>




    <div class="row" style="margin-top: 3%;">
        <div class="three columns">
            {!! Form::submit('Update', ['class' => 'button-primary u-full-width']) !!}
        </div>
        <div class="six columns">&nbsp;</div>
        <div class="three columns">
            <a class="button u-full-width" href="{{ route('platform.admin.address.index') }}">Cancel</a>
        </div>
    </div>

    {!! Form::close() !!}

</div>

@endsection

