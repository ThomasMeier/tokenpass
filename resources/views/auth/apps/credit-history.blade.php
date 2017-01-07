@extends('accounts.base')

@section('htmltitle', 'Developer Tools')

@section('body_class', 'dashboard client_apps')

@section('accounts_content')



<section class="title">
  <span class="heading">Developer Tools</span>
</section>

<section id="appsController">
    <h3>App Credit Group Transaction History</h3>  
    <p>
        <a href="/auth/apps#app-credits">Go back</a>
    </p>    
	<div class="panel with-padding">
        <p>
            <strong>App Credit Group:</strong> {{ $credit_group->name }}<br>
            <strong>Credit Group Unique ID:</strong> {{ $credit_group->uuid }}<br>
            <strong>Credit Balance:</strong> {{ $credit_balance }}<br>
            <strong># Credit Accounts</strong> {{ $num_accounts }}
            @if($credit_account)
                <br><br>
                <strong class="text-info">Currently viewing history for account
                <em>
                @if($credit_account->tokenpass_user)
                    {{ $credit_account->tokenpass_user['username'] }}
                @else
                    {{ $credit_account->name }}
                @endif
                </em>
                </strong>
            @endif
		</p>
	</div>
	<div class="panel with-padding">
        <p>
            Showing {{ $tx_showing }} of {{ $tx_count }} credit transactions.
        </p>
		<table class="table table--responsive" v-cloak>
			<thead>
				<tr>
					<th>Account</th>
					<th>Amount</th>
					<th>Created</th>
					<th>Unique ID</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="tx in credit_txs">
					<td>
                        <span v-if="tx.account.tokenpass_user">
                            <strong>@{{ tx.account.tokenpass_user.username }}</strong>
                        </span>
                        <span v-else>
                            <strong>@{{ tx.account.name }}</strong>
                        </span>
                    </td>                    
					<td>
                        <span v-if="tx.amount >= 0">
                            <strong class="text-success">+@{{ tx.amount }}</strong>
                        </span>
                        <span v-else>
                            <strong class="text-danger">@{{ tx.amount }}</strong>
                        </span>
                    </td>
					<td>@{{ formatDate(tx.created_at) }}</td>
					<td>
                        @{{ tx.uuid }}
                    </td>                    
					<td>
                        <button class="reveal-modal" data-modal="viewRefModal" v-on:click="setCurrentTx(tx)" >View Ref Data</button>
					</td>
				</tr>
			</tbody>
		</table>
        {!! $paginator->render() !!} 
	</div>
<!-- VIEW REF MODAL -->
<div class="modal-container" id="viewRefModal">
    <div class="modal-bg"></div>
    <div class="modal-content">
        <h3>Credit Transaction Reference Data</h3>
        <div class="modal-x close-modal">
            <i class="material-icons">clear</i>
        </div>

        <div class="input-group">
            <label>Transaction ID:</label>
            <div class="name">
                @{{ currentTx.uuid }}
            </div>
        </div>
        
        <div class="input-group">
            <label>Account:</label>
            <div class="name">
                <span v-if="currentTx.account.tokenpass_user">
                    <strong>@{{ currentTx.account.tokenpass_user.username }}</strong>
                </span>
                <span v-else>
                    <strong>@{{ currentTx.account.name }}</strong>
                </span>
            </div>
        </div>    
        
        <div class="input-group">
            <label>Amount:</label>
            <div class="name">
                <span v-if="currentTx.amount >= 0">
                    <strong class="text-success">+@{{ currentTx.amount }}</strong>
                </span>
                <span v-else>
                    <strong class="text-danger">@{{ currentTx.amount }}</strong>
                </span>
            </div>
        </div>        
        
        <div class="input-group">
            <label>TX Created:</label>
            @{{ formatDate(currentTx.created_at) }}
        </div>        
        
        <div class="input-group">
            <label>Reference Data:</label>
            <textarea readonly>@{{ currentTx.ref }}</textarea>
        </div>

        <hr> 
    </div>
</div> <!-- END VIEW REF MODAL -->

</section>




@endsection

@section('page-js')
<script>

var credit_txs = {!! json_encode($credit_txs) !!};

var vm = new Vue({
  el: '#appsController',
  data: {
    credit_txs: credit_txs,
    currentTx: {}
  },
  methods: {
    bindEvents: function(){
      $('form.js-auto-ajax').on('submit', this.submitFormAjax);
    },
    setCurrentTx: function(tx){
      this.currentTx = tx;
    },    
    formatDate: function(dateString){
    	var options = {
			    year: "numeric", month: "short", day: "numeric"
			};
    	return new Date(dateString).toLocaleDateString('en-us', options);
    },
  },
  ready:function(){
    this.bindEvents();
    $(this.el).find(['v-cloak']).slideDown();
  }
});

// Initialize view app modal
var viewRefModal = new Modal();
viewRefModal.init(document.getElementById('viewRefModal'));


</script>
@endsection
