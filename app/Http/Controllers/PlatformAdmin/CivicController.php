<?php

namespace Tokenpass\Http\Controllers\PlatformAdmin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Input;
use Tokenly\PlatformAdmin\Controllers\ResourceController;
use Tokenpass\Models\User;
use Tokenpass\Repositories\AddressRepository;
use Tokenpass\Repositories\UserRepository;

class CivicController extends ResourceController
{
    public function __construct()
    {
        $this->middleware('sign');

    }

    protected $view_prefix      = 'civic';
    protected $repository_class = UserRepository::class;

    protected function getValidationRules() {
        return [
            'user_id' => 'exists:users,id',
            'label' => 'max:255',
            'verified' => 'numeric',
            'primary'  => 'numeric',
            'active_toggle' => 'numeric',
            'second_factor_toggle' => 'numeric',
            'public' => 'numeric',
            'login_toggle' => 'numeric',
            'from_api' => 'numeric',
            'address' => 'max:255',
            'xchain_address_id' => 'max:255',
            'send_monitor_id' => 'max:255',
            'receive_monitor_id' => 'max:255',
        ];
    }

    public function index(Request $request)
    {
        $users = User::where('civic_enabled', 1)->get();
        return view('platformadmin.'.$this->view_prefix.'.index', $this->modifyViewData([
            'users' => $users,
        ], __FUNCTION__));
    }

    function destroy($id)
    {
        $user = $this->resourceRepository()->findById($id);
        $user->civic_enabled = 0;
        $user->save();

        Session::flash('message', 'Civic authentication disabled for the user!');
        Session::flash('message-class', 'alert-success');
        return redirect('/platform/admin/civic');
    }

}
