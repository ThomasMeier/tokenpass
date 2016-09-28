@extends('platformAdmin::layouts.app')

@section('title_name') Connections @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Connections</h1>
    </div>
    <p>
        <strong>Total # Connections:</strong> {{ number_format(count($models)) }}
    </p>
    <table class="u-full-width">
      <thead>
        <tr>
          <th>Client</th>
          <th>User</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($models as $model)
        <tr>
          <td>{{ $model->client['name'] }}</td>
          <td>{{ $model->user['username'] }}</td>
          <td>
            <a class="button button-primary" href="{{ route('platform.admin.connectedapps.edit', ['id' => $model['id']]) }}">Edit</a>

            {{-- inline delete form --}}
            <form onsubmit="return confirm('Are you sure you want to delete this connection?')" action="{{ route('platform.admin.connectedapps.destroy', ['id' => $model['id']]) }}" method="POST" style="margin-bottom: 0; display: inline;">
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
      <a href="{{ route('platform.admin.connectedapps.create') }}" class="button button-primary">Create a New Connection</a>
    </div>
</div>


@endsection

