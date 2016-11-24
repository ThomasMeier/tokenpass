@extends('accounts.base')

@section('htmltitle', 'Token Chats')

@section('body_class', 'dashboard tokenchats')

@section('accounts_content')

<section class="title">
  <span class="heading">Token Chats</span>
  <button data-modal="addChatModal" class="btn-dash-title add-chat-btn reveal-modal">+ Add Token Chat</button>
</section>

<section id="chatsController">
	<div class="panel with-padding">
		<p>
			Token Chats are chat rooms accessible only by users who hold a token.
		</p>
	</div>

    <h3>My Token Chats</h3>
	<div class="panel with-padding">
		<table class="table table--responsive" v-cloak>
			<thead>
				<tr>
					<th>Name</th>
					<th>Required Tokens</th>
                    <th>Status</th>
					<th>Created</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(index, chat) in chats">
					<td><strong>@{{ chat.name }}</strong></td>
					<td>@{{ formatSatoshis(chat.tca_rules[0]['amount']) }} @{{ chat.tca_rules[0]['asset'] }}</td>
					<td><span :class="{ active: chat.active, inactive: !chat.active }">@{{ chat.active ? 'Active' : 'Inactive' }}</span></td>
                    <td>@{{ formatDate(chat.created_at) }}</td>
					<td>
						<button class="reveal-modal" data-modal="viewChatModal" v-on:click="setCurrentChat(chat)" ><i class="material-icons">open_in_browser</i> More Details</button>
					
						<button class="reveal-modal" data-modal="editChatModal" v-on:click="setCurrentChat(chat)" ><i class="material-icons">edit</i> Edit</button>

                        <form :id="'DeleteTokenChat_'+index" class="inline" action="/tokenchats/delete/@{{ chat.uuid }}" method="POST">
                            {!! csrf_field() !!}
                            <input type="hidden" name="_method" value="DELETE">
                            <a href="#" v-on:click="submitDeleteForm('DeleteTokenChat_'+index)"><i class="material-icons">delete</i> Delete</a>
                        </form>
						
					</td>
				</tr>
			</tbody>
		</table>
	</div>


	<!-- NEW CHAT MODAL -->
	<div class="modal-container" id="addChatModal">
		<div class="modal-bg"></div>
		<div class="modal-content">
			<h3>Register Token Chat</h3>
			<div class="modal-x close-modal">
				<i class="material-icons">clear</i>
			</div>

		  <form class="js-auto-ajax" action="/tokenchats/new" method="POST">
                {!! csrf_field() !!}

		        <div class="error-placeholder panel-danger"></div>

				<label for="chat_name">Chat Name:</label>
				<input type="text" name="name" id="chat_name" placeholder="{{ date("F") }} AMA" required/>
                
                <label for="token">Token Required:</label>
                <input type="text" name="token" id="token" value="@{{ currentChat.token }}" placeholder="MYCOIN" />

                <label for="quantity">Quantity Required:</label>
                <input type="text" name="quantity" id="quantity" value="@{{ currentChat.quantity }}"  placeholder="10" />

				<button type="submit" class="">Submit</button>

		  </form>
		</div>
	</div> <!-- END NEW CHAT MODAL -->

	<!-- VIEW CHAT MODAL -->
	<div class="modal-container" id="viewChatModal">
		<div class="modal-bg"></div>
		<div class="modal-content">
			<h3>Token Chat Details</h3>
			<div class="modal-x close-modal">
				<i class="material-icons">clear</i>
			</div>

			<div class="input-group">
				<label>Chat Name:</label>
				<div class="name">
					@{{ currentChat.name }}
				</div>
			</div>

            <div class="input-group">
                <label>Tokens Required:</label>
                <div class="token">
                    @{{ formatSatoshis(currentChat.tca_rules[0]['amount']) }} @{{ currentChat.tca_rules[0]['asset'] }}
                </div>
            </div>

		</div>
	</div> <!-- END VIEW CHAT MODAL -->

	<!-- EDIT CHAT MODAL -->
	<div class="modal-container" id="editChatModal">
		<div class="modal-bg"></div>
		<div class="modal-content">
			<h3>Update Token Chat</h3>
			<div class="modal-x close-modal">
				<i class="material-icons">clear</i>
			</div>

		  <form class="js-auto-ajax" action="/tokenchats/edit/@{{ currentChat.uuid }}" method="POST">
              {!! csrf_field() !!}
				<div class="error-placeholder panel-danger"></div>

				<label for="chat_name">Client Name:</label>
				<input type="text" name="name" id="chat_name" value="@{{ currentChat.name }}" required />
                    
                <label for="token">Token Required:</label>
                <input type="text" name="token" id="token" value="@{{ currentChat.tca_rules[0]['asset'] }}" />

                <label for="quantity">Quantity Required:</label>
                <input type="text" name="quantity" id="quantity" value="@{{ formatSatoshis(currentChat.tca_rules[0]['amount']) }}"  />

                <label for="active">Active</label>
                <input type="hidden" name="active" value="0">
                <input type="checkbox" id="active" name="active" value="1" v-bind:checked="currentChat.active == 1">

    			<button type="submit">Save</button>
		  </form>


		</div>

	</div> <!-- END EDIT CHAT MODAL -->
</section>

@endsection

@section('page-js')
<script>

var chats = {!! json_encode($chats) !!};

var vm = new Vue({
  el: '#chatsController',
  data: {
    chats: chats,
    currentChat: {}
  },
  methods: {
    bindEvents: function(){
      $('form.js-auto-ajax').on('submit', this.submitFormAjax);
    },
    setCurrentChat: function(chat){
      this.currentChat = chat;
    },
    formatDate: function(dateString){
    	var options = {
			    year: "numeric", month: "short", day: "numeric"
			};
    	return new Date(dateString).toLocaleDateString('en-us', options);
    },
    formatNumber: function(x) {
        return Math.round(x*100000000) / 100000000
    },
    formatSatoshis: function(x) {
        return Math.round(x) / 100000000
    },
    submitDeleteForm: function(formId) {
        if (confirm('Are you sure you want to delete this Chat?')) {
            document.getElementById(formId).submit();
        }
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

// Initialize new chat modal
var addChatModal = new Modal();
addChatModal.init(document.getElementById('addChatModal'));

// Initialize view chat modal
var viewChatModal = new Modal();
viewChatModal.init(document.getElementById('viewChatModal'));

// Initialize edit chat modal
var editChatModal = new Modal();
editChatModal.init(document.getElementById('editChatModal'));

</script>
@endsection
