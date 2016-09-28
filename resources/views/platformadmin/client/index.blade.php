@extends('platformAdmin::layouts.app')

@section('title_name') Clients @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Clients</h1>
    </div>
    <p>
        <strong># Clients:</strong> {{ count($models) }}
    </p>
    <table class="u-full-width">
      <thead>
        <tr>
          <th>Name</th>
          <th>Owner</th>
          <th>ID</th>
          <th>Connections</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($models as $model)
        <tr>
          <td>{{ $model['name'] }}</td>
          <td>
              <strong>
            @if($model['user_id'] == 0)
                Platform
            @else
                {{ $model->user()->username }}
            @endif
                </strong>
          </td>
          <td>{{ $model['id'] }}</td>
          <td><a href="{{ route('platform.admin.connectedapps.index', array('client_id' => $model['id'])) }}">{{ $model->countConnections() }}</a></td>
          <td>{{ $model->created_at->format('F j\, Y \a\t g:i A') }}</td>
          <td>
            <a class="button button-primary" href="{{ route('platform.admin.client.edit', ['id' => $model['id']]) }}">Edit</a>

            {{-- inline delete form --}}
            <form onsubmit="return confirm('Are you sure you want to delete this client?')" action="{{ route('platform.admin.client.destroy', ['id' => $model['id']]) }}" method="POST" style="margin-bottom: 0; display: inline;">
            {!! csrf_field() !!}
            <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="button-primary">Delete</button>
            </form>

          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="row">
      <a href="{{ route('platform.admin.client.create') }}" class="button button-primary">Create a New Client</a>
    </div>
</div>


@endsection

