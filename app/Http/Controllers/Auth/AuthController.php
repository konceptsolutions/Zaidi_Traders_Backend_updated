<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use JWTAuth;

class AuthController extends Controller
{
    /**
     * Registring user
     *
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request email
     * @param \Illuminate\Http\Request password
     * @param \Illuminate\Http\Request role_id
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function register(Request $req)
    {
        $rules = array(
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:5',
            'role_id' => 'required'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $user = array([
            'name' => $req->name,
            'email' => $req->email,
            'password' => Hash::make($req->password),
            'role_id' => $req->role_id,
        ]);
        DB::table("users")->insert($user);
        return ['status' => 'ok', 'message' => 'Account created Successfully'];
    }

    /**
     *This is a login function
     * Return success or error message
     * @param  \Illuminate\Http\Request  $email
     * @param  \Illuminate\Http\Request  $password
     * @return string $result
     */
    public function login(Request $request)
    {

        $credentials = $request->only('email', 'password');

        //valid credential
        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|string|min:5|max:50'
        ]);

        //Send failed response if request is not valid
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        //Request is validated
        //Crean token
        try {
            // this authenticates the user details with the database and generates a token
            if (!$token = JWTAuth::attempt($credentials)) {
                return ['status' => 'error', 'message' => 'Invalid login credentials'];
            }
            $usersdata = User::with('role')->where('email', $request->email)->first();
            $users = ['role_id' => $usersdata->role_id, 'role_name' => $usersdata->role->role, 'name' => $usersdata->name];
        } catch (JWTException $e) {
            return $this->sendError([], $e->getMessage(), 500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        return ['status' => 'ok', 'token' => $token, 'role' => $users];

        // $request->validate([
        //     'email' => 'required|email',
        //     'password' => 'required'
        // ]);
        // $user = User::where('email', $request->email)->first();

        // if ($user && Hash::check($request->password, $user->password) && $user->is_active == 1) {
        //     $user->auto_logout = 0;
        //     $user->save();
        //     return ['status' => 'ok', 'user_id' => $user->id, 'email' => $user->email, 'name' => $user->name];
        // } else {
        //     $result = "Wrong Credentials";
        //     return ['result' => $result];
        // }
    }


    /**
     * getting users list
     * @param Illuminate\Http\Request records
     * @param Illuminate\Http\Request pageNo
     * @param Illuminate\Http\Request colName
     * @param Illuminate\Http\Request sort
     * @return string $users
     */
    public function getUsers(Request $req)
    {
        $users = User::orderBy($req->colName, $req->sort)->with('role')->paginate($req->records, ['*'], 'page', $req->pageNo);
        return ['users' => $users];
    }


    /**
     * deleting user
     * @param Illuminate\Http\Request id
     * @return string result
     */
    public function deleteUser(Request $req)
    {
        if ($req->id == 58) {
            $result = "You Cannot Delete Admin";
        } else {
            $deleteUser = User::find($req->id);
            if ($deleteUser->delete()) {
                $result = "User Deleted successfully";
            } else {
                $result = "There is some error";
            }
        }
        return ['result' => $result];
    }

    /**
     * edit User
     * @param Illuminate\Http\Request id
     * @return string result
     */
    public function editUser(Request $req)
    {
        if ($req->id == 58) {
            $result = "You Cannot Edit Admin";

            return ['status' => 'error', 'message' => $result];
        } else {
            $result = User::with('role')->where('id', $req->id)->first();
            return ['status' => 'ok', 'user' => $result];
        }
    }

    /**
     * edit User
     * @param Illuminate\Http\Request id
     * @return string result
     */
    public function updateUser(Request $req)
    {
        $rules = array(
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $req->id,
            'role_id' => 'required'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        if ($req->id == 58) {
            $result = "You Cannot Edit Admin";

            return ['status' => 'error', 'message' => $result];
        } else {
            $user = User::find($req->id);
            $user->name = $req->name;
            $user->email = $req->email;
            $user->role_id = $req->role_id;

            $user->save();
        }

        return ['status' => 'ok', 'message' => 'Account Update Successfully'];
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $email
     * @param  int  $password
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:users,id',
            'password' => 'required|min:5',
            'confirm_password' => 'required',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $user = User::where('id', $request->id)->first();
        if ($request->password == $request->confirm_password) {
            $user->password = Hash::make($request->password);
            $user->save();
            return ['status' => 'ok', 'message' => 'Password changed successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Password & Confirm Password Must be Equal'];
        }
    }


    /**
     * check autologout for user
     * @param Illuminate\Http\Request user_id
     * @return string result
     */
    public function checkUserAutoLogout(Request $req)
    {
        $rules = array(
            'user_id' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $user = User::find($req->user_id);
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found', 'autoLogout' => 1];
        }
        return ['status' => 'ok', 'autoLogout' => $user->auto_logout];
    }

    /**
     * check autologout for user
     * @param Illuminate\Http\Request user_id
     * @return string result
     */
    public function togalSystemUser(Request $req)
    {
        $rules = array(
            'user_id' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $user = User::find($req->user_id);
        if ($user->id == 58) {
            return ['status' => 'ok', 'message' => 'Admin Cannot be Deactivated'];
        } else {
            if ($user->is_active == 1) {
                $user->is_active = 0;
                $user->auto_logout = 1;
                $user->save();
                return ['status' => 'ok', 'message' => 'User Deactivated successfully'];
            } else {
                $user->is_active = 1;
                $user->save();
                return ['status' => 'ok', 'message' => 'User Activated successfully'];
            }
        }
    }
    public function getRoles()
    {
        $roles = Role::orderBy('role')

            ->get();
        return ['status' => 'ok', 'roles' => $roles];
    }
    public function logout(Request $request)
    {
        // $validator = Validator::make($request->only('token'), [
        //     'token' => 'required'
        // ]);
        // if ($validator->fails()) {
        //     return ['status' => 'error', 'message' => $validator->errors()->first()];
        // }

        try {
            // JWTAuth::invalidate($request->token);
            JWTAuth::parseToken()->invalidate(true);

            return ['status' => 'ok', 'message' => 'You logged out successfully'];
        } catch (JWTException $e) {
            return ['status' => 'ok', 'message' => 'You logged out successfully'];
        }
    }
}
