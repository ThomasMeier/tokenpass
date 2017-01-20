@extends('accounts.base')

@section('htmltitle', 'Developer Tools')

@section('body_class', 'dashboard client_apps')

@section('accounts_content')



<section class="title">
  <span class="heading">Developer Tools</span>
</section>

<section id="appsController">
    <h3>App Credit Group Accounts</h3>  
    <p>
        <a href="/auth/apps#app-credits">Go back</a>
    </p>    
	<div class="panel with-padding">
        <p>
            <strong>App Credit Group:</strong> {{ $credit_group->name }}<br>
            <strong>Credit Group Unique ID:</strong> {{ $credit_group->uuid }}<br>
            <strong>Credit Balance:</strong> @formatSatoshis($credit_balance)<br>
            <strong># Credit Accounts</strong> {{ $num_accounts }}
		</p>
	</div>
	<div class="panel with-padding">
		<table class="table table--responsive" v-cloak>
			<thead>
				<tr>
					<th>Name</th>
					<th>Balance</th>
					<th>Created</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="account in accounts">
					<td>
                        <span v-if="account.tokenpass_user">
                            <strong>@{{ account.tokenpass_user.username }}</strong>
                        </span>
                        <span v-else>
                            <strong>@{{ account.name }}</strong>
                        </span>
                    </td>
					<td><strong :class="{'text-success': account.balance > 0, 'text-danger': account.balance < 0, 'muted': account.balance == 0}">
            @{{ formatSatohis(account.balance) }}
          </strong></td>
					<td>@{{ formatDate(account.created_at) }}</td>
					<td>
						<a href="/auth/apps/credits/{{ $credit_group->uuid }}/history/@{{ account.uuid }}" ><i class="material-icons">history</i> History</a>
					</td>
				</tr>
			</tbody>
		</table>
	</div>


</section>

@endsection

@section('page-js')
<script>

var accounts = {!! json_encode($credit_accounts) !!};

var vm = new Vue({
  el: '#appsController',
  data: {
    accounts: accounts,
  },
  methods: {
    bindEvents: function(){
      $('form.js-auto-ajax').on('submit', this.submitFormAjax);
    },
    formatDate: function(dateString){
    	var options = {
			    year: "numeric", month: "short", day: "numeric"
			};
    	return new Date(dateString).toLocaleDateString('en-us', options);
    },
    formatSatohis: function(value) {
      var SATOSHI = 100000000;
      var formatted = window.numeral(value / SATOSHI).format('0,0[.]00000000');
      return formatted;
    }
  },
  ready:function(){
    this.bindEvents();
    $(this.el).find(['v-cloak']).slideDown();
  }
});

</script>
@endsection
