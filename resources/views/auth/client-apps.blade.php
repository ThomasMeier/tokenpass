@extends('accounts.base')

@section('htmltitle', 'My Applications')

@section('body_class', 'dashboard client_apps')

@section('accounts_content')

<section class="title">
  <span class="heading">My Applications</span>
  <button data-modal="addAppModal" class="btn-dash-title add-app-btn reveal-modal">+ Add Application</button>
</section>

<section id="appsController">
	<div class="panel with-padding">
		<p>
			Here you can register new client Applications and obtain a pair of API keys for integration of Tokenpass
			in your own website or service. 
			Once you have your API keys, the <a href="https://github.com/tokenly/tokenpass-client" target="_blank">TokenpassClient</a>
			PHP class can be used to integrate into your application. 
		</p>
		<p>
			<strong><a href="http://apidocs.tokenly.com/tokenpass/" target="_blank">View API Documentation</a></strong>
		</p>
	</div>
	<div class="panel with-padding">
		<table class="table table--responsive" v-cloak>
			<thead>
				<tr>
					<th>Name</th>
					<th># Users</th>
					<th>Register Date</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="app in apps">
					<td><strong>@{{ app.name }}</strong></td>
					<td>@{{ app.user_count }}</td>
					<td>@{{ formatDate(app.created_at) }}</td>
					<td>
						<button class="reveal-modal" data-modal="viewAppModal" v-on:click="setCurrentApp(app)" ><i class="material-icons">open_in_browser</i> Keys</button>
					
						<button class="reveal-modal" data-modal="editAppModal" v-on:click="setCurrentApp(app)" ><i class="material-icons">edit</i> Edit</button>

						<a href="/auth/apps/@{{ app.id }}/delete" onclick="return confirm('Are you sure you want to delete this API key?')"><i class="material-icons">delete</i> Delete</a>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<!-- NEW APP MODAL -->
	<div class="modal-container" id="addAppModal">
		<div class="modal-bg"></div>
		<div class="modal-content">
			<h3>Register Client Application</h3>
			<div class="modal-x close-modal">
				<i class="material-icons">clear</i>
			</div>

		  <form class="js-auto-ajax" action="/auth/apps/new" method="POST">

		        <div class="error-placeholder panel-danger"></div>

				<label for="client-name">Client Name:</label>
				<input type="text" name="name" id="client-name" required/>
                
                <label for="app_link">App Homepage URL:</label>
                <input type="text" name="app_link" id="app_link" value="@{{ currentApp.app_link }}" />                  

				<label for="endpoints">Client Callback Endpoints:</label>
				<textarea name="endpoints" id="endpoints" placeholder="(one per line)" rows="4"></textarea>

				<button type="submit" class="">Submit</button>

		  </form>
		</div>
	</div> <!-- END NEW APP MODAL -->

	<!-- VIEW APP MODAL -->
	<div class="modal-container" id="viewAppModal">
		<div class="modal-bg"></div>
		<div class="modal-content">
			<h3>Client App API Keys</h3>
			<div class="modal-x close-modal">
				<i class="material-icons">clear</i>
			</div>

			<div class="input-group">
				<label>App:</label>
				<div class="name">
					@{{ currentApp.name }}
				</div>
			</div>

			<div class="input-group">
				<label>Client ID:</label>
				<div class="client-id">
					@{{ currentApp.id }}
				</div>
			</div>

			<div class="input-group">
				<label>API Secret:</label>
				<div class="api-secret">
					@{{ currentApp.secret }}
				</div>
			</div>

 			<hr> 
      <div class="input-group">
		  <form class="js-auto-ajax" action="/auth/apps/@{{ currentApp.id }}/regen" method="PATCH">
			  <button type="submit">Regenerate Keys</button>
		  </form>
      </div>
		</div>
	</div> <!-- END VIEW APP MODAL -->

	<!-- EDIT APP MODAL -->
	<div class="modal-container" id="editAppModal">
		<div class="modal-bg"></div>
		<div class="modal-content">
			<h3>Update Client Application</h3>
			<div class="modal-x close-modal">
				<i class="material-icons">clear</i>
			</div>

		  <form class="js-auto-ajax" action="/auth/apps/@{{ currentApp.id }}/edit" method="POST">

					<div class="error-placeholder panel-danger"></div>

					<label for="client-name">Client Name:</label>
					<input type="text" name="name" id="client-name" value="@{{ currentApp.name }}" required />
                    
					<label for="app_link">App Homepage URL:</label>
					<input type="text" name="app_link" id="app_link" value="@{{ currentApp.app_link }}" />                    

					<label for="endpoints">Client Callback Endpoints:</label>
					<textarea name="endpoints" id="endpoints" placeholder="(one per line)" rows="4">@{{ currentApp.endpoints }}</textarea>

					<button type="submit">Save</button>
		  </form>


		</div>

	</div> <!-- END EDIT APP MODAL -->
</section>

@endsection

@section('page-js')
<script>

var apps = {!! json_encode($client_apps) !!};

var vm = new Vue({
  el: '#appsController',
  data: {
    apps: apps,
    currentApp: {}
  },
  methods: {
    bindEvents: function(){
      $('form.js-auto-ajax').on('submit', this.submitFormAjax);
    },
    setCurrentApp: function(app){
      this.currentApp = app;
    },
    formatDate: function(dateString){
    	var options = {
			    year: "numeric", month: "short", day: "numeric"
			};
    	return new Date(dateString).toLocaleDateString('en-us', options);
    },
    submitFormAjax: function(e){
      e.preventDefault();
      var $form = $(e.target);
      var formUrl = $form.attr('action');
      var formMethod = $form.attr('method');
      var formString = $form.serialize();
      console.log(formUrl);
      console.log(formMethod);
      console.log(formString);
      var errorTimeout = null;
      // clear the error
      $('.error-placeholder', $form).empty();
      if (errorTimeout) { clearTimeout(errorTimeout); }

      $.ajax({
        type: formMethod,
        url: formUrl,
        data: formString,
        dataType: 'json'
      }).done(function(data) {
        console.log(data);
        // success - redirect
        if (data.redirectUrl != null) {
          window.location = data.redirectUrl;
        }
      }).fail(function(data, status, error) {
        console.log(data);
        console.log(status);
        console.log(error);
        // failure - show an error.
        var errorMsg = '';
        if (data.responseJSON != null && data.responseJSON.error != null) {
          errorMsg = data.responseJSON.error;
        } else {
          errorMsg = 'There was an unknown error';
        }

        // show the error
        $('.error-placeholder', $form).html(errorMsg);
        errorTimeout = setTimeout(function() {
          $('.error-placeholder', $form).empty();
          errorTimeout = null;
        }, 10000);
      });
    }
  },
  ready:function(){
    this.bindEvents();
    $(this.el).find(['v-cloak']).slideDown();
  }
});

// Initialize new app modal
var addAppModal = new Modal();
addAppModal.init(document.getElementById('addAppModal'));

// Initialize view app modal
var viewAppModal = new Modal();
viewAppModal.init(document.getElementById('viewAppModal'));

// Initialize edit app modal
var editAppModal = new Modal();
editAppModal.init(document.getElementById('editAppModal'));

</script>
@endsection
