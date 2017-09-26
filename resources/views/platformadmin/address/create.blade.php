@extends('platformAdmin::layouts.app')

@section('title_name') Create Pocket Address @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Create Pocket Address</h1>
    </div>

    @include('platformAdmin::includes.errors')


    {!! Form::open([
        'method' => 'POST',
        'route' => ['platform.admin.address.store'],
    ]) !!}

    <div class="row">

        <div class="eight columns">
            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" name="address" id="address"  class="form-control" />
            </div>
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="form-control">
                    <option name="btc">btc</option>
                    <option name="eth">eth</option>
                </select>
            </div>
            <div class="form-group">
                <label for="user_id">Owner</label>
                <select name="user_id" id="user_id" class="form-control">
                    @foreach(Tokenpass\Models\User::all() as $user)
                        <option value="{{ $user->id }}" >{{ $user->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="label">Label</label>
                <input type="text" name="label" id="label"  class="form-control" />
            </div>
            <div class="form-group">
                <label for="verified">Verified</label>
                <select name="verified" id="verified" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="active_toggle">Active</label>
                <select name="active_toggle" id="active_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="public">Public</label>
                <select name="public" id="public" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="login_toggle">Login Enabled</label>
                <select name="login_toggle" id="login_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="second_factor_toggle">2FA Enabled</label>
                <select name="second_factor_toggle" id="second_factor_toggle" class="form-control">
                    <option value="0">No</option>
                    <option value="1" >Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="from_api">From API (disables login and 2fa toggles)</label>
                <select name="from_api" id="from_api" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label for="xchain_address_id">XChain Address ID</label>
                <input type="text" name="xchain_address_id" id="xchain_address_id"  class="form-control" />
            </div>
            <div class="form-group">
                <label for="send_monitor_id">XChain Send Monitor</label>
                <input type="text" name="send_monitor_id" id="send_monitor_id"  class="form-control" />
            </div>
            <div class="form-group">
                <label for="receive_monitor_id">XChain Receive Monitor</label>
                <input type="text" name="receive_monitor_id" id="receive_monitor_id"  class="form-control" />
            </div>
        </div>

        <div class="six columns">
        </div>

        <div class="six columns">
        </div>
    </div>


    <div class="row" style="margin-top: 3%;">
        <div class="three columns">
            {!! Form::submit('Create', ['class' => 'button-primary u-full-width']) !!}
        </div>
        <div class="six columns">&nbsp;</div>
        <div class="three columns">
            <a class="button u-full-width" href="{{ route('platform.admin.address.index') }}">Cancel</a>
        </div>
    </div>

    {!! Form::close() !!}

</div>

@endsection
