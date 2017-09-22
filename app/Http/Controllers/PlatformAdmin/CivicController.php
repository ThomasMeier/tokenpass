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
        ];
    }

    public function index(Request $request)
    {
        $users = $this->resourceRepository()->findAllWithCivicEnabled();
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
